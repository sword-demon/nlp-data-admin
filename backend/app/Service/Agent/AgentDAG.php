<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Constants\Code;
use App\Contract\AgentInterface;
use App\Exception\BusinessException;
use App\Service\Agent\Outcome\AgentOutcome;
use Hyperf\Coroutine\Parallel;
use Hyperf\Contract\StdoutLoggerInterface;

/**
 * Agent DAG 编排器
 *
 * 支持:
 * - addNode(): 添加节点
 * - addDependency(): 添加依赖关系 (from -> to)
 * - execute(): DFS 环检测 + Kahn 拓扑排序 + 分层并行执行
 *
 * 算法:
 * 1. DFS 后向边检测 - 检测环
 * 2. Kahn 拓扑排序 - 生成分层结构
 * 3. Hyperf Coroutine 并行 - 同层节点并行执行
 */
class AgentDAG
{
    /** @var array<string, AgentInterface> */
    private array $nodes = [];

    /** @var array<string, array<string>> 邻接表: nodeId => [依赖它的节点] */
    private array $adjacency = [];

    /** @var array<string, int> 入度表: nodeId => 入度数 */
    private array $inDegree = [];

    /** @var array<string, mixed> 节点上下文 */
    private array $contexts = [];

    public function __construct(
        private readonly StdoutLoggerInterface $logger
    ) {}

    /**
     * 添加节点
     *
     * @param array<string, mixed> $context
     */
    public function addNode(string $nodeId, AgentInterface $agent, array $context = []): self
    {
        if (isset($this->nodes[$nodeId])) {
            throw new BusinessException(
                Code::VALIDATION_ERROR,
                "node [{$nodeId}] already exists",
                422
            );
        }

        $this->nodes[$nodeId] = $agent;
        $this->contexts[$nodeId] = $context;
        $this->adjacency[$nodeId] = [];
        $this->inDegree[$nodeId] = 0;

        return $this;
    }

    /**
     * 添加依赖关系: from 必须在 to 之前执行
     */
    public function addDependency(string $from, string $to): self
    {
        if (! isset($this->nodes[$from])) {
            throw new BusinessException(
                Code::VALIDATION_ERROR,
                "node [{$from}] not found",
                422
            );
        }
        if (! isset($this->nodes[$to])) {
            throw new BusinessException(
                Code::VALIDATION_ERROR,
                "node [{$to}] not found",
                422
            );
        }

        // 避免重复边
        if (! in_array($to, $this->adjacency[$from])) {
            $this->adjacency[$from][] = $to;
            ++$this->inDegree[$to];
        }

        return $this;
    }

    /**
     * 执行 DAG
     *
     * @return array<string, AgentOutcome>
     */
    public function execute(): array
    {
        // 1. DFS 环检测
        $this->detectCycle();

        // 2. Kahn 拓扑排序 + 分层
        $layers = $this->topologicalSort();

        // 3. 分层并行执行
        return $this->executeLayered($layers);
    }

    /**
     * DFS 环检测 (后向边检测)
     */
    private function detectCycle(): void
    {
        $visited = [];
        $recStack = [];

        foreach (array_keys($this->nodes) as $node) {
            if (! isset($visited[$node])) {
                $this->dfs($node, $visited, $recStack, []);
            }
        }
    }

    /**
     * DFS 递归遍历
     *
     * @param array<string, bool> $visited
     * @param array<string, bool> $recStack
     * @param array<int, string> $path
     */
    private function dfs(string $node, array &$visited, array &$recStack, array $path): void
    {
        $visited[$node] = true;
        $recStack[$node] = true;
        $path[] = $node;

        foreach ($this->adjacency[$node] as $neighbor) {
            if (! isset($visited[$neighbor])) {
                $this->dfs($neighbor, $visited, $recStack, $path);
            } elseif (isset($recStack[$neighbor]) && $recStack[$neighbor]) {
                // 发现后向边 (环)
                $cyclePath = $this->extractCyclePath($path, $neighbor);
                throw new BusinessException(
                    Code::WORKSHOP_AGENT_FAILED,
                    'cycle detected in DAG: ' . implode(' → ', $cyclePath),
                    422
                );
            }
        }

        unset($recStack[$node]);
    }

    /**
     * 提取环路径
     *
     * @param array<int, string> $path
     * @return array<int, string>
     */
    private function extractCyclePath(array $path, string $cycleStart): array
    {
        $startIndex = array_search($cycleStart, $path);
        if ($startIndex === false) {
            return array_merge($path, [$cycleStart]);
        }
        $cycle = array_slice($path, $startIndex);
        $cycle[] = $cycleStart;
        return $cycle;
    }

    /**
     * Kahn 拓扑排序 + 分层
     *
     * @return array<int, array<int, string>> 分层结构: [[layer0_nodes], [layer1_nodes], ...]
     */
    private function topologicalSort(): array
    {
        $inDegree = $this->inDegree;
        $layers = [];
        $processed = 0;

        // 初始化第一层: 入度为 0 的节点
        $currentLayer = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $currentLayer[] = $node;
            }
        }

        while (! empty($currentLayer)) {
            $layers[] = $currentLayer;
            $processed += count($currentLayer);
            $nextLayer = [];

            foreach ($currentLayer as $node) {
                foreach ($this->adjacency[$node] as $neighbor) {
                    --$inDegree[$neighbor];
                    if ($inDegree[$neighbor] === 0) {
                        $nextLayer[] = $neighbor;
                    }
                }
            }

            $currentLayer = $nextLayer;
        }

        // 检查是否所有节点都被处理 (理论上 DFS 已排除环, 此处为双保险)
        if ($processed !== count($this->nodes)) {
            throw new BusinessException(
                Code::WORKSHOP_AGENT_FAILED,
                'topological sort failed: cycle detected',
                422
            );
        }

        return $layers;
    }

    /**
     * 分层并行执行
     *
     * @param array<int, array<int, string>> $layers
     * @return array<string, AgentOutcome>
     */
    private function executeLayered(array $layers): array
    {
        $results = [];
        $failed = false;

        foreach ($layers as $layerIndex => $layer) {
            if ($failed) {
                $this->logger->warning("[AgentDAG] skipping layer {$layerIndex} due to previous failure");
                break;
            }

            $this->logger->info("[AgentDAG] executing layer {$layerIndex}: " . implode(', ', $layer));

            if (count($layer) === 1) {
                // 单节点串行执行
                $nodeId = $layer[0];
                try {
                    $results[$nodeId] = $this->executeNode($nodeId, $results);
                    if ($results[$nodeId]->isFailed()) {
                        $failed = true;
                    }
                } catch (\Throwable $e) {
                    $this->logger->error("[AgentDAG] node [{$nodeId}] failed: {$e->getMessage()}");
                    $failed = true;
                }
            } else {
                // 多节点并行执行
                $parallel = new Parallel();
                foreach ($layer as $nodeId) {
                    $parallel->add(function () use ($nodeId, $results) {
                        return $this->executeNode($nodeId, $results);
                    }, $nodeId);
                }

                $layerResults = $parallel->wait();
                foreach ($layerResults as $nodeId => $outcome) {
                    if ($outcome instanceof \Throwable) {
                        $this->logger->error("[AgentDAG] node [{$nodeId}] failed: {$outcome->getMessage()}");
                        $failed = true;
                    } else {
                        $results[$nodeId] = $outcome;
                        if ($outcome->isFailed()) {
                            $failed = true;
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * 执行单个节点
     *
     * @param array<string, AgentOutcome> $previousResults
     */
    private function executeNode(string $nodeId, array $previousResults): AgentOutcome
    {
        $agent = $this->nodes[$nodeId];
        $context = $this->contexts[$nodeId];

        // 注入依赖节点的结果到 context
        $context['dag_results'] = $previousResults;

        $this->logger->info("[AgentDAG] executing node [{$nodeId}] ({$agent->getName()})");

        return $agent->execute($context);
    }

    /**
     * 获取所有节点 ID
     *
     * @return array<int, string>
     */
    public function getNodeIds(): array
    {
        return array_keys($this->nodes);
    }

    /**
     * 获取节点数量
     */
    public function getNodeCount(): int
    {
        return count($this->nodes);
    }
}
