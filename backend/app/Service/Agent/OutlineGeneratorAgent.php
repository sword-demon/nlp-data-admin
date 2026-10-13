<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Annotation\AgentLog;
use App\Constants\AgentPrompts;
use App\Constants\Code;
use App\Contract\AgentInterface;
use App\Exception\BusinessException;
use App\Service\Agent\Outcome\AgentOutcome;
use App\Service\Agent\Outcome\Payload\OutlineDraft;
use App\Service\Agent\Outcome\Payload\OutlineNode;
use App\Service\ModelProviderService;
use Generator;
use Hyperf\Contract\StdoutLoggerInterface;

/**
 * 大纲生成 Agent：根据已选标题生成结构化大纲（使用 Function Calling）。
 *
 * Payload：OutlineDraft。markdown 或 nodes 为空抛异常 → FAILED。
 */
class OutlineGeneratorAgent extends AbstractAgent implements AgentInterface
{
    public function __construct(
        private readonly ModelProviderService $providers,
        private readonly StdoutLoggerInterface $logger
    ) {}

    public function getName(): string
    {
        return 'outline_generator';
    }

    #[AgentLog(name: 'outline_generator')]
    public function execute(array $context): AgentOutcome
    {
        $title = (string) ($context['title'] ?? '');
        $supplement = (string) ($context['supplement'] ?? '');
        if ($title === '') {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'title is required', 422);
        }

        $userPrompt = $this->render(AgentPrompts::OUTLINE_USER_TPL, [
            'title' => $title,
            'supplement' => $supplement !== '' ? $supplement : '（无）',
        ]);

        $preamble = $this->buildResearchPreamble($context['research_data'] ?? null);
        $userPrompt = $preamble . $userPrompt;

        // 定义 Function Calling tools
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_outline',
                    'description' => '生成文章的分层结构化大纲',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'markdown' => [
                                'type' => 'string',
                                'description' => '大纲的 Markdown 格式（支持 # ## ### 等标题等级）',
                            ],
                            'nodes' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => [
                                            'type' => 'integer',
                                            'description' => '节点唯一标识符',
                                        ],
                                        'text' => [
                                            'type' => 'string',
                                            'description' => '节点文本内容',
                                        ],
                                        'level' => [
                                            'type' => 'integer',
                                            'description' => '层级深度（1-4）',
                                            'minimum' => 1,
                                            'maximum' => 4,
                                        ],
                                        'parent_id' => [
                                            'type' => ['integer', 'null'],
                                            'description' => '父节点 ID（根节点为 null）',
                                        ],
                                    ],
                                    'required' => ['id', 'text', 'level'],
                                ],
                                'description' => '结构化节点列表',
                            ],
                        ],
                        'required' => ['markdown', 'nodes'],
                    ],
                ],
            ],
        ];

        try {
            $result = $this->providers->driver()->chatWithTools(
                AgentPrompts::OUTLINE_SYSTEM,
                [['role' => 'user', 'content' => $userPrompt]],
                $tools,
                ['temperature' => 0.6]
            );

            // 优先使用 tool_calls，否则降级
            if ($result['tool_calls'] !== null && count($result['tool_calls']) > 0) {
                return $this->parseToolCallsResponse($result['tool_calls']);
            } else {
                $this->logger->info('[OutlineGeneratorAgent] no tool_calls, fallback to text parsing');
                return $this->parseTextResponse($result['content']);
            }
        } catch (\Throwable $e) {
            $this->logger->error('[OutlineGeneratorAgent] Function Calling failed: ' . $e->getMessage());
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'outline generation failed: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * 解析 Function Calling 返回的结构化数据
     */
    private function parseToolCallsResponse(array $toolCalls): AgentOutcome
    {
        if (empty($toolCalls)) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'no tool_calls', 500);
        }

        $toolCall = $toolCalls[0];
        $arguments = $toolCall['function']['arguments'] ?? '{}';

        if (is_string($arguments)) {
            $decoded = json_decode($arguments, true);
            if ($decoded === null) {
                throw new BusinessException(
                    Code::WORKSHOP_PARSE_ERROR,
                    'tool_calls arguments is not valid JSON',
                    422
                );
            }
        } else {
            $decoded = $arguments;
        }

        $markdown = (string) ($decoded['markdown'] ?? '');
        $nodesRaw = $decoded['nodes'] ?? [];

        if ($markdown === '' || ! is_array($nodesRaw) || empty($nodesRaw)) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'invalid outline structure', 500);
        }

        // 验证节点层级关系
        $nodeIds = [];
        $nodes = [];
        foreach ($nodesRaw as $n) {
            if (! is_array($n) || empty($n['text'])) {
                continue;
            }
            $nodeId = (int) ($n['id'] ?? count($nodes) + 1);
            $parentId = isset($n['parent_id']) && $n['parent_id'] !== null ? (int) $n['parent_id'] : null;

            // 验证 parentId 不存在循环引用
            if ($parentId !== null && ! $this->isValidParentId($parentId, $nodeIds)) {
                $this->logger->warning("[OutlineGeneratorAgent] invalid parent_id: $parentId, skipping node");
                continue;
            }

            $nodes[] = new OutlineNode(
                id: $nodeId,
                text: (string) $n['text'],
                level: (int) ($n['level'] ?? 1),
                parentId: $parentId,
            );
            $nodeIds[$nodeId] = true;
        }

        if (empty($nodes)) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'outline has no valid nodes', 500);
        }

        return AgentOutcome::ok(new OutlineDraft(markdown: $markdown, nodes: $nodes));
    }

    /**
     * 验证 parentId 是否有效（不存在循环引用）
     */
    private function isValidParentId(int $parentId, array $validIds): bool
    {
        // 简单检查：parentId 应该已经存在于 validIds 中（前向引用）
        return isset($validIds[$parentId]);
    }

    /**
     * 降级：解析纯文本 JSON 响应
     */
    private function parseTextResponse(?string $content): AgentOutcome
    {
        if ($content === null || $content === '') {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'no content returned', 500);
        }

        $this->logger->debug('[OutlineGeneratorAgent] fallback text response: ' . $content);
        $decoded = $this->decodeJson($content, $this->getName());

        $markdown = (string) ($decoded['markdown'] ?? '');
        $nodesRaw = $decoded['nodes'] ?? [];

        if ($markdown === '' || ! is_array($nodesRaw) || empty($nodesRaw)) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'invalid outline structure', 500);
        }

        $nodeIds = [];
        $nodes = [];
        foreach ($nodesRaw as $n) {
            if (! is_array($n) || empty($n['text'])) {
                continue;
            }
            $nodeId = (int) ($n['id'] ?? count($nodes) + 1);
            $parentId = isset($n['parent_id']) && $n['parent_id'] !== null ? (int) $n['parent_id'] : null;

            if ($parentId !== null && ! $this->isValidParentId($parentId, $nodeIds)) {
                continue;
            }

            $nodes[] = new OutlineNode(
                id: $nodeId,
                text: (string) $n['text'],
                level: (int) ($n['level'] ?? 1),
                parentId: $parentId,
            );
            $nodeIds[$nodeId] = true;
        }

        if (empty($nodes)) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'outline has no valid nodes', 500);
        }

        return AgentOutcome::ok(new OutlineDraft(markdown: $markdown, nodes: $nodes));
    }

    public function executeStream(array $context): Generator
    {
        $outcome = $this->execute($context);
        /** @var OutlineDraft $payload */
        $payload = $outcome->payload;
        yield json_encode($payload->toArray(), JSON_UNESCAPED_UNICODE);
        return $outcome;
    }
}
