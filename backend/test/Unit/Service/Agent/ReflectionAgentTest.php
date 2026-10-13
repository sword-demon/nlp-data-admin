<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service\Agent;

use App\Constants\Code;
use App\Exception\BusinessException;
use App\Service\Agent\ReflectionAgent;
use App\Service\Agent\Outcome\Payload\ReflectionPayload;
use App\Service\ModelProviderService;
use Hyperf\Contract\StdoutLoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * ReflectionAgent 单元测试
 */
class ReflectionAgentTest extends TestCase
{
    private ReflectionAgent $agent;
    private \PHPUnit\Framework\MockObject\MockObject $mockProviders;
    private \PHPUnit\Framework\MockObject\MockObject $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockProviders = $this->createMock(ModelProviderService::class);
        $this->mockLogger = $this->createMock(StdoutLoggerInterface::class);
        $this->agent = new ReflectionAgent($this->mockProviders, $this->mockLogger);
    }

    /**
     * 测试场景 1: 评分通过 (所有维度 >= 7 分)
     */
    public function testExecuteWithPassingScores(): void
    {
        $context = [
            'title' => 'AI 技术发展趋势',
            'content' => '人工智能技术在 2024 年迎来新的发展阶段...',
        ];

        $mockResponse = json_encode([
            'scores' => [
                'logic' => 8,
                'fact' => 8,
                'language' => 8,
                'title' => 8,
                'structure' => 8,
            ],
            'suggestions' => [
                '内容质量优秀，无需改进',
            ],
            'average_score' => 8.0,
        ]);

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chat')
            ->willReturn($mockResponse);

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $outcome = $this->agent->execute($context);

        $this->assertTrue($outcome->isOk());
        /** @var ReflectionPayload $payload */
        $payload = $outcome->payload;
        $this->assertInstanceOf(ReflectionPayload::class, $payload);
        $this->assertTrue($payload->passed());
        $this->assertEquals(8, $payload->scores['logic']);
        $this->assertEquals(8.0, $payload->averageScore);
    }

    /**
     * 测试场景 2: 评分不通过 (某维度 < 7 分)
     */
    public function testExecuteWithFailingScores(): void
    {
        $context = [
            'title' => 'AI 技术发展趋势',
            'content' => '内容存在逻辑问题...',
        ];

        $mockResponse = json_encode([
            'scores' => [
                'logic' => 6,      // < 7
                'fact' => 8,
                'language' => 7,
                'title' => 8,
                'structure' => 7,
            ],
            'suggestions' => [
                '段落间的逻辑关系不够清晰，建议重新组织',
                '补充更多具体的例子或数据支撑',
            ],
            'average_score' => 7.2,
        ]);

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chat')
            ->willReturn($mockResponse);

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $outcome = $this->agent->execute($context);

        $this->assertTrue($outcome->isDegraded());
        /** @var ReflectionPayload $payload */
        $payload = $outcome->payload;
        $this->assertFalse($payload->passed());
        $this->assertEquals(6, $payload->scores['logic']);
        $this->assertCount(2, $payload->suggestions);

        // 验证失败维度
        $failed = $payload->failedDimensions();
        $this->assertArrayHasKey('logic', $failed);
        $this->assertEquals(6, $failed['logic']);
    }

    /**
     * 测试场景 3: LLM 返回格式错误
     */
    public function testExecuteWithInvalidJsonResponse(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(Code::WORKSHOP_PARSE_ERROR);

        $context = [
            'title' => 'AI 技术',
            'content' => '内容...',
        ];

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chat')
            ->willReturn('not valid json {');

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $this->agent->execute($context);
    }

    /**
     * 测试场景 4: 缺失 content
     */
    public function testExecuteWithoutContent(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(Code::WORKSHOP_AGENT_FAILED);

        $context = [
            'title' => 'AI 技术',
            // 缺失 content
        ];

        $this->agent->execute($context);
    }

    /**
     * 测试场景 5: 多维度不通过
     */
    public function testExecuteWithMultipleFailingDimensions(): void
    {
        $context = [
            'title' => '低质量文章',
            'content' => '质量差的内容...',
        ];

        $mockResponse = json_encode([
            'scores' => [
                'logic' => 5,      // < 7
                'fact' => 4,       // < 7
                'language' => 6,   // < 7
                'title' => 5,      // < 7
                'structure' => 6,  // < 7
            ],
            'suggestions' => [
                '逻辑混乱，需要重新组织结构',
                '缺乏事实依据，需要补充资料',
                '语言表达欠缺，需要修改措辞',
                '标题不吸引人，需要重新拟定',
            ],
            'average_score' => 5.2,
        ]);

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chat')
            ->willReturn($mockResponse);

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $outcome = $this->agent->execute($context);

        $this->assertTrue($outcome->isDegraded());
        /** @var ReflectionPayload $payload */
        $payload = $outcome->payload;

        $failed = $payload->failedDimensions();
        $this->assertCount(5, $failed); // 所有维度都失败
        $this->assertLessThan(6, $payload->averageScore);
    }

    /**
     * 测试场景 6: 边界评分 (恰好 7 分通过)
     */
    public function testExecuteWithBoundaryScores(): void
    {
        $context = [
            'title' => '边界质量文章',
            'content' => '刚好及格的内容...',
        ];

        $mockResponse = json_encode([
            'scores' => [
                'logic' => 7,      // 恰好 >= 7
                'fact' => 7,
                'language' => 7,
                'title' => 7,
                'structure' => 7,
            ],
            'suggestions' => [],
            'average_score' => 7.0,
        ]);

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chat')
            ->willReturn($mockResponse);

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $outcome = $this->agent->execute($context);

        $this->assertTrue($outcome->isOk());
        /** @var ReflectionPayload $payload */
        $payload = $outcome->payload;
        $this->assertTrue($payload->passed());
    }

    /**
     * 测试场景 7: 缺失 scores 字段
     */
    public function testExecuteWithMissingScores(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(Code::WORKSHOP_PARSE_ERROR);

        $context = [
            'title' => 'AI 技术',
            'content' => '内容...',
        ];

        $mockResponse = json_encode([
            'suggestions' => ['建议'],
            'average_score' => 8.0,
            // 缺失 scores
        ]);

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chat')
            ->willReturn($mockResponse);

        $this->mockProviders->expects($this->once())
            ->method('driver')
            ->willReturn($mockDriver);

        $this->agent->execute($context);
    }

    /**
     * 测试 ReflectionPayload::fromArray()
     */
    public function testReflectionPayloadFromArray(): void
    {
        $data = [
            'scores' => [
                'logic' => 8,
                'fact' => 8,
                'language' => 7,
                'title' => 9,
                'structure' => 8,
            ],
            'suggestions' => ['建议 1', '建议 2'],
            'average_score' => 8.0,
        ];

        $payload = ReflectionPayload::fromArray($data);

        $this->assertEquals(8, $payload->scores['logic']);
        $this->assertCount(2, $payload->suggestions);
        $this->assertEquals(8.0, $payload->averageScore);
        $this->assertTrue($payload->passed());
    }

    /**
     * 测试 executeStream() 方法
     */
    public function testExecuteStream(): void
    {
        $context = [
            'title' => 'AI 技术',
            'content' => '高质量内容...',
        ];

        $mockResponse = json_encode([
            'scores' => [
                'logic' => 9,
                'fact' => 9,
                'language' => 8,
                'title' => 9,
                'structure' => 9,
            ],
            'suggestions' => ['非常优秀'],
            'average_score' => 8.8,
        ]);

        $mockDriver = $this->createMock(\stdClass::class);
        $mockDriver->expects($this->once())
            ->method('chat')
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
        $this->assertArrayHasKey('scores', $decoded);
        $this->assertArrayHasKey('passed', $decoded);
        $this->assertTrue($decoded['passed']);
    }
}
