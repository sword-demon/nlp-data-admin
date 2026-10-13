<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service\Agent;

use App\Service\Agent\AbstractAgent;
use App\Service\Agent\Outcome\AgentOutcome;
use App\Service\Agent\Outcome\Payload\TitleCandidates;
use App\Service\Agent\Outcome\Payload\TitleCandidate;
use PHPUnit\Framework\TestCase;

/**
 * AbstractAgent 缓存方法单元测试
 */
class AbstractAgentTest extends TestCase
{
    private AbstractAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        // 创建匿名子类以测试 protected 方法
        $this->agent = new class extends AbstractAgent {
            public function getName(): string
            {
                return 'test_agent';
            }

            // 暴露 protected 方法供测试
            public function publicBuildCacheKey(array $context): string
            {
                return $this->buildCacheKey($context);
            }

            public function publicRender(string $template, array $vars): string
            {
                return $this->render($template, $vars);
            }
        };
    }

    /**
     * 测试场景 1: buildCacheKey 一致性
     */
    public function testBuildCacheKeyConsistency(): void
    {
        $context1 = [
            'topic' => 'AI 技术',
            'style' => '专业',
            'user_id' => 123,      // 应该被排除
            'timestamp' => '2026-10-14T00:00:00',  // 应该被排除
        ];

        $context2 = [
            'style' => '专业',     // 不同的顺序
            'topic' => 'AI 技术',
            'user_id' => 456,      // 不同的 user_id，但应该被排除
            'timestamp' => '2026-10-14T01:00:00',  // 不同的时间，但应该被排除
        ];

        $key1 = $this->agent->publicBuildCacheKey($context1);
        $key2 = $this->agent->publicBuildCacheKey($context2);

        // 语义相同的 context 应生成相同的 key
        $this->assertEquals($key1, $key2);
        $this->assertStringStartsWith('agent:cache:test_agent:', $key1);
    }

    /**
     * 测试场景 2: buildCacheKey 差异检测
     */
    public function testBuildCacheKeyDifference(): void
    {
        $context1 = [
            'topic' => 'AI 技术',
            'style' => '专业',
        ];

        $context2 = [
            'topic' => 'AI 技术',
            'style' => '通俗',  // 不同的 style
        ];

        $key1 = $this->agent->publicBuildCacheKey($context1);
        $key2 = $this->agent->publicBuildCacheKey($context2);

        // 语义不同的 context 应生成不同的 key
        $this->assertNotEquals($key1, $key2);
    }

    /**
     * 测试 render() 模板替换
     */
    public function testRender(): void
    {
        $template = '选题: {topic}, 风格: {style}';
        $vars = ['topic' => 'AI', 'style' => '专业'];

        $result = $this->agent->publicRender($template, $vars);

        $this->assertEquals('选题: AI, 风格: 专业', $result);
    }

    /**
     * 测试 render() 未替换的占位符保留
     */
    public function testRenderUnmatchedPlaceholder(): void
    {
        $template = '选题: {topic}, 风格: {style}, 其他: {other}';
        $vars = ['topic' => 'AI', 'style' => '专业'];

        $result = $this->agent->publicRender($template, $vars);

        // {other} 应该保留
        $this->assertStringContainsString('{other}', $result);
        $this->assertStringContainsString('AI', $result);
    }

    /**
     * 测试 render() 空变量替换为空串
     */
    public function testRenderEmptyVariable(): void
    {
        $template = '内容: {content}';
        $vars = ['content' => ''];

        $result = $this->agent->publicRender($template, $vars);

        $this->assertEquals('内容: ', $result);
    }

    /**
     * 测试 decodeJson() 成功
     */
    public function testDecodeJsonSuccess(): void
    {
        $json = json_encode(['title' => 'AI', 'score' => 85]);

        $decoded = $this->agent->decodeJson($json, 'test_agent');

        $this->assertIsArray($decoded);
        $this->assertEquals('AI', $decoded['title']);
        $this->assertEquals(85, $decoded['score']);
    }

    /**
     * 测试 decodeJson() 移除 markdown 围栏
     */
    public function testDecodeJsonRemoveFence(): void
    {
        $json = "```json\n{\"title\": \"AI\", \"score\": 85}\n```";

        $decoded = $this->agent->decodeJson($json, 'test_agent');

        $this->assertEquals('AI', $decoded['title']);
        $this->assertEquals(85, $decoded['score']);
    }

    /**
     * 测试 decodeJson() 提取 JSON 片段
     */
    public function testDecodeJsonExtractFragment(): void
    {
        $text = "这是一些说明文字\n{\"title\": \"AI\", \"score\": 85}\n然后是更多文字";

        $decoded = $this->agent->decodeJson($text, 'test_agent');

        $this->assertEquals('AI', $decoded['title']);
        $this->assertEquals(85, $decoded['score']);
    }

    /**
     * 测试 decodeJson() 处理 JSON 数组
     */
    public function testDecodeJsonArray(): void
    {
        $json = '[{"title": "A"}, {"title": "B"}]';

        $decoded = $this->agent->decodeJson($json, 'test_agent');

        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertEquals('A', $decoded[0]['title']);
    }

    /**
     * 测试 buildResearchPreamble() 无数据
     */
    public function testBuildResearchPreambleEmpty(): void
    {
        $preamble = $this->agent->buildResearchPreamble(null);

        $this->assertEquals('', $preamble);
    }

    /**
     * 测试 buildResearchPreamble() 有摘要
     */
    public function testBuildResearchPreambleWithSummary(): void
    {
        $research = [
            'summary' => 'AI 发展背景...',
            'sources' => [
                ['title' => '文章 1', 'url' => 'http://example.com/1'],
                ['title' => '文章 2', 'url' => 'http://example.com/2'],
            ],
        ];

        $preamble = $this->agent->buildResearchPreamble($research);

        $this->assertStringContainsString('【选题背景研究】', $preamble);
        $this->assertStringContainsString('AI 发展背景', $preamble);
        $this->assertStringContainsString('【参考来源】', $preamble);
        $this->assertStringContainsString('文章 1', $preamble);
        $this->assertStringContainsString('文章 2', $preamble);
    }
}
