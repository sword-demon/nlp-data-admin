<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service\Agent;

use App\Constants\Code;
use App\Exception\BusinessException;
use App\Service\Agent\TitleGeneratorAgent;
use App\Service\Agent\Outcome\AgentOutcome;
use App\Service\Agent\Outcome\Payload\TitleCandidate;
use App\Service\Agent\Outcome\Payload\TitleCandidates;
use App\Service\ModelProviderService;
use Hyperf\Contract\StdoutLoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * TitleGeneratorAgent Function Calling 单元测试
 */
class TitleGeneratorAgentTest extends TestCase
{
    private TitleGeneratorAgent $agent;
    private \PHPUnit\Framework\MockObject\MockObject $mockProviders;
    private \PHPUnit\Framework\MockObject\MockObject $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockProviders = $this->createMock(ModelProviderService::class);
        $this->mockLogger = $this->createMock(StdoutLoggerInterface::class);
        $this->agent = new TitleGeneratorAgent($this->mockProviders, $this->mockLogger);
    }

    /**
     * 测试场景 1: Function Calling 正常返回
     */
    public function testExecuteWithFunctionCalling(): void
    {
        $context = [
            'topic' => 'AI 技术发展趋势',
            'style' => '专业',
        ];

        // Mock chatWithTools() 返回 tool_calls
        $mockResponse = [
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_123',
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_titles',
                        'arguments' => json_encode([
                            'titles' => [
                                ['title' => 'AI 技术 2026 年新趋势全解析', 'analysis' => '引入最新 AI 动态', 'score' => 88],
                                ['title' => '人工智能发展的五大方向', 'analysis' => '系统性梳理', 'score' => 85],
                                ['title' => '通义千问与大模型竞争格局', 'analysis' => '行业对比', 'score' => 82],
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
        /** @var TitleCandidates $payload */
        $payload = $outcome->payload;
        $this->assertInstanceOf(TitleCandidates::class, $payload);
        $this->assertCount(3, $payload->titles);
        $this->assertEquals('AI 技术 2026 年新趋势全解析', $payload->titles[0]->title);
        $this->assertEquals(88, $payload->titles[0]->score);
    }

    /**
     * 测试场景 2: 无 tool_calls 降级
     */
    public function testExecuteWithoutToolCalls(): void
    {
        $context = [
            'topic' => 'AI 技术发展趋势',
            'style' => '专业',
        ];

        // Mock chatWithTools() 返回无 tool_calls（纯文本降级）
        $mockResponse = [
            'content' => json_encode([
                ['title' => 'AI 技术趋势', 'analysis' => '分析', 'score' => 80],
                ['title' => '大模型发展', 'analysis' => '发展方向', 'score' => 78],
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
        /** @var TitleCandidates $payload */
        $payload = $outcome->payload;
        $this->assertCount(2, $payload->titles);
    }

    /**
     * 测试场景 3: tool_calls arguments 格式异常
     */
    public function testExecuteWithInvalidJsonArguments(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(Code::WORKSHOP_PARSE_ERROR);

        $context = [
            'topic' => 'AI 技术',
            'style' => '专业',
        ];

        $mockResponse = [
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_456',
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_titles',
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
     * 测试场景 4: 标题数量超限（截取前 5 个）
     */
    public function testExecuteWithExcessiveTitles(): void
    {
        $context = [
            'topic' => 'AI 技术',
            'style' => '专业',
        ];

        $titles = [];
        for ($i = 1; $i <= 10; $i++) {
            $titles[] = [
                'title' => "标题 $i",
                'analysis' => "分析 $i",
                'score' => 80 + $i,
            ];
        }

        $mockResponse = [
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_789',
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_titles',
                        'arguments' => json_encode(['titles' => $titles]),
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
        /** @var TitleCandidates $payload */
        $payload = $outcome->payload;
        // 验证只返回前 5 个
        $this->assertCount(5, $payload->titles);
        $this->assertEquals('标题 1', $payload->titles[0]->title);
        $this->assertEquals('标题 5', $payload->titles[4]->title);
    }

    /**
     * 测试场景 5: 缺失 topic
     */
    public function testExecuteWithoutTopic(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(Code::WORKSHOP_AGENT_FAILED);

        $context = [
            'style' => '专业',
            // 缺失 topic
        ];

        $this->agent->execute($context);
    }

    /**
     * 测试场景 6: chatWithTools() 异常
     */
    public function testExecuteWithProviderException(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(Code::WORKSHOP_AGENT_FAILED);

        $context = [
            'topic' => 'AI 技术',
            'style' => '专业',
        ];

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chatWithTools')
            ->willThrowException(new \Exception('API error'));

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $this->agent->execute($context);
    }

    /**
     * 测试 executeStream() 方法
     */
    public function testExecuteStream(): void
    {
        $context = [
            'topic' => 'AI 技术',
            'style' => '专业',
        ];

        $mockResponse = [
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_stream',
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_titles',
                        'arguments' => json_encode([
                            'titles' => [
                                ['title' => '标题 1', 'analysis' => '分析', 'score' => 85],
                                ['title' => '标题 2', 'analysis' => '分析', 'score' => 82],
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

        // 获取流式输出
        $outputs = [];
        $result = null;
        foreach ($generator as $output) {
            $outputs[] = $output;
        }
        // 获取返回值（return 语句）
        try {
            $generator->getReturn();
        } catch (\Throwable $e) {
            // 某些 PHP 版本可能需要特殊处理
        }

        // 验证流式输出
        $this->assertCount(1, $outputs);
        $decoded = json_decode($outputs[0], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('titles', $decoded);
        $this->assertCount(2, $decoded['titles']);
    }
}
