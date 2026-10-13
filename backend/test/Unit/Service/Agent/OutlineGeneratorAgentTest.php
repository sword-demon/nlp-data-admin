<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service\Agent;

use App\Constants\Code;
use App\Exception\BusinessException;
use App\Service\Agent\OutlineGeneratorAgent;
use App\Service\Agent\Outcome\Payload\OutlineDraft;
use App\Service\ModelProviderService;
use Hyperf\Contract\StdoutLoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * OutlineGeneratorAgent Function Calling 单元测试
 */
class OutlineGeneratorAgentTest extends TestCase
{
    private OutlineGeneratorAgent $agent;
    private \PHPUnit\Framework\MockObject\MockObject $mockProviders;
    private \PHPUnit\Framework\MockObject\MockObject $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockProviders = $this->createMock(ModelProviderService::class);
        $this->mockLogger = $this->createMock(StdoutLoggerInterface::class);
        $this->agent = new OutlineGeneratorAgent($this->mockProviders, $this->mockLogger);
    }

    /**
     * 测试场景 1: Function Calling 正常返回大纲
     */
    public function testExecuteWithFunctionCalling(): void
    {
        $context = [
            'title' => 'AI 技术发展趋势',
            'supplement' => '重点关注大模型',
        ];

        $mockResponse = [
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_outline_1',
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_outline',
                        'arguments' => json_encode([
                            'markdown' => "# AI 技术发展趋势\n## 大模型技术\n### 语言大模型\n## 应用落地\n### 企业应用",
                            'nodes' => [
                                ['id' => 1, 'text' => 'AI 技术发展趋势', 'level' => 1, 'parent_id' => null],
                                ['id' => 2, 'text' => '大模型技术', 'level' => 2, 'parent_id' => 1],
                                ['id' => 3, 'text' => '语言大模型', 'level' => 3, 'parent_id' => 2],
                                ['id' => 4, 'text' => '应用落地', 'level' => 2, 'parent_id' => 1],
                                ['id' => 5, 'text' => '企业应用', 'level' => 3, 'parent_id' => 4],
                            ],
                        ]),
                    ],
                ],
            ],
        ];

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chatWithTools')
            ->willReturn($mockResponse);

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $outcome = $this->agent->execute($context);

        $this->assertTrue($outcome->isOk());
        /** @var OutlineDraft $payload */
        $payload = $outcome->payload;
        $this->assertInstanceOf(OutlineDraft::class, $payload);
        $this->assertStringContainsString('# AI 技术发展趋势', $payload->markdown);
        $this->assertCount(5, $payload->nodes);
        $this->assertEquals('大模型技术', $payload->nodes[1]->text);
        $this->assertEquals(2, $payload->nodes[1]->parentId); // 父 ID 应该指向节点 2
    }

    /**
     * 测试场景 2: 无 tool_calls 降级
     */
    public function testExecuteWithoutToolCalls(): void
    {
        $context = [
            'title' => 'AI 技术',
            'supplement' => '',
        ];

        $mockResponse = [
            'content' => json_encode([
                'markdown' => "# AI 技术\n## 基础概念",
                'nodes' => [
                    ['id' => 1, 'text' => 'AI 技术', 'level' => 1],
                    ['id' => 2, 'text' => '基础概念', 'level' => 2, 'parent_id' => 1],
                ],
            ]),
            'tool_calls' => null,
        ];

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chatWithTools')
            ->willReturn($mockResponse);

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $outcome = $this->agent->execute($context);

        $this->assertTrue($outcome->isOk());
        /** @var OutlineDraft $payload */
        $payload = $outcome->payload;
        $this->assertCount(2, $payload->nodes);
    }

    /**
     * 测试场景 3: nodes 层级关系校验 - 无效的循环引用
     */
    public function testExecuteWithInvalidNodeHierarchy(): void
    {
        $context = [
            'title' => 'AI 技术',
            'supplement' => '',
        ];

        // 节点 3 的 parent_id 引用不存在的节点 99
        $mockResponse = [
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_hierarchy',
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_outline',
                        'arguments' => json_encode([
                            'markdown' => "# AI 技术\n## 基础\n### 深入",
                            'nodes' => [
                                ['id' => 1, 'text' => 'AI 技术', 'level' => 1, 'parent_id' => null],
                                ['id' => 2, 'text' => '基础', 'level' => 2, 'parent_id' => 1],
                                ['id' => 3, 'text' => '深入', 'level' => 3, 'parent_id' => 99], // 无效的 parent_id
                            ],
                        ]),
                    ],
                ],
            ],
        ];

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chatWithTools')
            ->willReturn($mockResponse);

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $outcome = $this->agent->execute($context);

        // 应该通过（无效节点被跳过），但仍需要至少 2 个有效节点
        $this->assertTrue($outcome->isOk());
        /** @var OutlineDraft $payload */
        $payload = $outcome->payload;
        $this->assertCount(2, $payload->nodes); // 只有节点 1 和 2
    }

    /**
     * 测试场景 4: markdown 为空
     */
    public function testExecuteWithEmptyMarkdown(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(Code::WORKSHOP_AGENT_FAILED);

        $context = [
            'title' => 'AI 技术',
            'supplement' => '',
        ];

        $mockResponse = [
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_empty',
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_outline',
                        'arguments' => json_encode([
                            'markdown' => '', // 空 markdown
                            'nodes' => [
                                ['id' => 1, 'text' => 'AI', 'level' => 1],
                            ],
                        ]),
                    ],
                ],
            ],
        ];

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chatWithTools')
            ->willReturn($mockResponse);

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $this->agent->execute($context);
    }

    /**
     * 测试场景 5: arguments JSON 格式异常
     */
    public function testExecuteWithInvalidJson(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(Code::WORKSHOP_PARSE_ERROR);

        $context = [
            'title' => 'AI 技术',
            'supplement' => '',
        ];

        $mockResponse = [
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_invalid',
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_outline',
                        'arguments' => 'not valid json {', // 无效 JSON
                    ],
                ],
            ],
        ];

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chatWithTools')
            ->willReturn($mockResponse);

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $this->agent->execute($context);
    }

    /**
     * 测试场景 6: 缺失 title
     */
    public function testExecuteWithoutTitle(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(Code::WORKSHOP_AGENT_FAILED);

        $context = [
            'supplement' => '补充信息',
            // 缺失 title
        ];

        $this->agent->execute($context);
    }

    /**
     * 测试 executeStream() 方法
     */
    public function testExecuteStream(): void
    {
        $context = [
            'title' => 'AI 技术',
            'supplement' => '',
        ];

        $mockResponse = [
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_stream',
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_outline',
                        'arguments' => json_encode([
                            'markdown' => "# AI\n## 基础",
                            'nodes' => [
                                ['id' => 1, 'text' => 'AI', 'level' => 1],
                                ['id' => 2, 'text' => '基础', 'level' => 2, 'parent_id' => 1],
                            ],
                        ]),
                    ],
                ],
            ],
        ];

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chatWithTools')
            ->willReturn($mockResponse);

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $generator = $this->agent->executeStream($context);

        $outputs = [];
        foreach ($generator as $output) {
            $outputs[] = $output;
        }

        $this->assertCount(1, $outputs);
        $decoded = json_decode($outputs[0], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('markdown', $decoded);
        $this->assertArrayHasKey('nodes', $decoded);
    }
}
