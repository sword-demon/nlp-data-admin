<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service\Agent;

use App\Contract\AgentInterface;
use App\Exception\BusinessException;
use App\Service\Agent\AgentDAG;
use App\Service\Agent\Outcome\AgentOutcome;
use Generator;
use Hyperf\Contract\StdoutLoggerInterface;
use HyperfTest\TestCase;
use Mockery;

/**
 * AgentDAG 测试
 */
class AgentDAGTest extends TestCase
{
    private StdoutLoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = Mockery::mock(StdoutLoggerInterface::class);
        $this->logger->shouldReceive('info')->zeroOrMoreTimes();
        $this->logger->shouldReceive('warning')->zeroOrMoreTimes();
        $this->logger->shouldReceive('error')->zeroOrMoreTimes();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSimpleDAGParallelExecution()
    {
        $dag = new AgentDAG($this->logger);

        // 3 个无依赖节点，应该并行执行
        $agentA = $this->createMockAgent('A');
        $agentB = $this->createMockAgent('B');
        $agentC = $this->createMockAgent('C');

        $dag->addNode('A', $agentA, ['input' => 'a']);
        $dag->addNode('B', $agentB, ['input' => 'b']);
        $dag->addNode('C', $agentC, ['input' => 'c']);

        $results = $dag->execute();

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('A', $results);
        $this->assertArrayHasKey('B', $results);
        $this->assertArrayHasKey('C', $results);
    }

    public function testChainDependency()
    {
        $dag = new AgentDAG($this->logger);

        // A → B → C 链式依赖
        $agentA = $this->createMockAgent('A');
        $agentB = $this->createMockAgent('B');
        $agentC = $this->createMockAgent('C');

        $dag->addNode('A', $agentA);
        $dag->addNode('B', $agentB);
        $dag->addNode('C', $agentC);

        $dag->addDependency('A', 'B');
        $dag->addDependency('B', 'C');

        $results = $dag->execute();

        $this->assertCount(3, $results);
        $this->assertTrue($results['A']->isOk());
        $this->assertTrue($results['B']->isOk());
        $this->assertTrue($results['C']->isOk());
    }

    public function testDiamondDependency()
    {
        $dag = new AgentDAG($this->logger);

        // 菱形依赖: A → B, A → C, B → D, C → D
        $agentA = $this->createMockAgent('A');
        $agentB = $this->createMockAgent('B');
        $agentC = $this->createMockAgent('C');
        $agentD = $this->createMockAgent('D');

        $dag->addNode('A', $agentA);
        $dag->addNode('B', $agentB);
        $dag->addNode('C', $agentC);
        $dag->addNode('D', $agentD);

        $dag->addDependency('A', 'B');
        $dag->addDependency('A', 'C');
        $dag->addDependency('B', 'D');
        $dag->addDependency('C', 'D');

        $results = $dag->execute();

        $this->assertCount(4, $results);
        // B 和 C 应该在同一层并行执行
        $this->assertTrue($results['A']->isOk());
        $this->assertTrue($results['B']->isOk());
        $this->assertTrue($results['C']->isOk());
        $this->assertTrue($results['D']->isOk());
    }

    public function testSimpleCycleDetection()
    {
        $dag = new AgentDAG($this->logger);

        // A → B → C → A 环
        $agentA = $this->createMockAgent('A');
        $agentB = $this->createMockAgent('B');
        $agentC = $this->createMockAgent('C');

        $dag->addNode('A', $agentA);
        $dag->addNode('B', $agentB);
        $dag->addNode('C', $agentC);

        $dag->addDependency('A', 'B');
        $dag->addDependency('B', 'C');
        $dag->addDependency('C', 'A');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('cycle detected in DAG');

        $dag->execute();
    }

    public function testSelfLoopDetection()
    {
        $dag = new AgentDAG($this->logger);

        // A → A 自环
        $agentA = $this->createMockAgent('A');

        $dag->addNode('A', $agentA);
        $dag->addDependency('A', 'A');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('cycle detected in DAG');

        $dag->execute();
    }

    public function testMultipleCyclesDetection()
    {
        $dag = new AgentDAG($this->logger);

        // 多环: A → B → A, C → D → C
        // 应该检测到第一个环
        $agentA = $this->createMockAgent('A');
        $agentB = $this->createMockAgent('B');
        $agentC = $this->createMockAgent('C');
        $agentD = $this->createMockAgent('D');

        $dag->addNode('A', $agentA);
        $dag->addNode('B', $agentB);
        $dag->addNode('C', $agentC);
        $dag->addNode('D', $agentD);

        $dag->addDependency('A', 'B');
        $dag->addDependency('B', 'A');
        $dag->addDependency('C', 'D');
        $dag->addDependency('D', 'C');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('cycle detected in DAG');

        $dag->execute();
    }

    public function testNodeExecutionFailureStopsSubsequentLayers()
    {
        $dag = new AgentDAG($this->logger);

        // A → B → C, A 失败后 B 和 C 不应该执行
        $agentA = $this->createMockAgent('A', false); // 失败
        $agentB = $this->createMockAgent('B');
        $agentC = $this->createMockAgent('C');

        // B 和 C 不应该被调用
        $agentB->shouldNotReceive('execute');
        $agentC->shouldNotReceive('execute');

        $dag->addNode('A', $agentA);
        $dag->addNode('B', $agentB);
        $dag->addNode('C', $agentC);

        $dag->addDependency('A', 'B');
        $dag->addDependency('B', 'C');

        $results = $dag->execute();

        // 只有 A 被执行
        $this->assertCount(1, $results);
        $this->assertArrayHasKey('A', $results);
        $this->assertTrue($results['A']->isFailed());
    }

    public function testDuplicateNodeThrowsException()
    {
        $dag = new AgentDAG($this->logger);
        $agent = $this->createMockAgent('A');

        $dag->addNode('A', $agent);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('node [A] already exists');

        $dag->addNode('A', $agent);
    }

    public function testDependencyOnNonExistentNodeThrowsException()
    {
        $dag = new AgentDAG($this->logger);
        $agent = $this->createMockAgent('A');

        $dag->addNode('A', $agent);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('node [B] not found');

        $dag->addDependency('A', 'B');
    }

    public function testGetNodeIds()
    {
        $dag = new AgentDAG($this->logger);

        $agentA = $this->createMockAgent('A');
        $agentB = $this->createMockAgent('B');

        $dag->addNode('A', $agentA);
        $dag->addNode('B', $agentB);

        $nodeIds = $dag->getNodeIds();

        $this->assertCount(2, $nodeIds);
        $this->assertContains('A', $nodeIds);
        $this->assertContains('B', $nodeIds);
    }

    public function testGetNodeCount()
    {
        $dag = new AgentDAG($this->logger);

        $this->assertEquals(0, $dag->getNodeCount());

        $dag->addNode('A', $this->createMockAgent('A'));
        $this->assertEquals(1, $dag->getNodeCount());

        $dag->addNode('B', $this->createMockAgent('B'));
        $this->assertEquals(2, $dag->getNodeCount());
    }

    public function testContextPropagation()
    {
        $dag = new AgentDAG($this->logger);

        $agentA = Mockery::mock(AgentInterface::class);
        $agentA->shouldReceive('getName')->andReturn('A');
        $agentA->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($context) {
                return isset($context['input']) && $context['input'] === 'test';
            }))
            ->andReturn(AgentOutcome::ok(null));

        $dag->addNode('A', $agentA, ['input' => 'test']);

        $dag->execute();
    }

    // === 辅助方法 ===

    /**
     * 创建 Mock Agent
     *
     * @param string $name Agent 名称
     * @param bool $success 是否成功执行
     */
    private function createMockAgent(string $name, bool $success = true): AgentInterface
    {
        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('getName')->andReturn($name);

        if ($success) {
            $agent->shouldReceive('execute')
                ->andReturn(AgentOutcome::ok(null));
        } else {
            $agent->shouldReceive('execute')
                ->andReturn(AgentOutcome::failed('execution failed'));
        }

        $agent->shouldReceive('executeStream')
            ->andReturn($this->createMockGenerator());

        return $agent;
    }

    /**
     * 创建 Mock Generator
     */
    private function createMockGenerator(): Generator
    {
        yield 'test';
        return AgentOutcome::ok(null);
    }
}
