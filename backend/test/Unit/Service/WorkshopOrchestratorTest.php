<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service;

use App\Enum\WorkshopState;
use App\Model\Article;
use App\Service\Agent\ContentGeneratorAgent;
use App\Service\Agent\ImageAnalyzerAgent;
use App\Service\Agent\OutlineGeneratorAgent;
use App\Service\Agent\Outcome\AgentOutcome;
use App\Service\Agent\Outcome\Payload\ContentDraft;
use App\Service\Agent\Outcome\Payload\ReflectionPayload;
use App\Service\Agent\ParallelImageGenerator;
use App\Service\Agent\ReflectionAgent;
use App\Service\Agent\TitleGeneratorAgent;
use App\Service\Agent\TopicResearchAgent;
use App\Service\SseBroadcaster;
use App\Service\WorkshopOrchestrator;
use Hyperf\Config\Config;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Engine\Http\EventStream;
use HyperfTest\TestCase;
use Mockery;
use Throwable;

/**
 * WorkshopOrchestrator 集成 ReflectionAgent 测试
 */
class WorkshopOrchestratorTest extends TestCase
{
    private WorkshopOrchestrator $orchestrator;
    private TopicResearchAgent $topicResearch;
    private TitleGeneratorAgent $titleAgent;
    private OutlineGeneratorAgent $outlineAgent;
    private ContentGeneratorAgent $contentAgent;
    private ReflectionAgent $reflectionAgent;
    private ImageAnalyzerAgent $imageAnalyzer;
    private ParallelImageGenerator $imageGenerator;
    private SseBroadcaster $sse;
    private ConfigInterface $config;
    private StdoutLoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->topicResearch = Mockery::mock(TopicResearchAgent::class);
        $this->titleAgent = Mockery::mock(TitleGeneratorAgent::class);
        $this->outlineAgent = Mockery::mock(OutlineGeneratorAgent::class);
        $this->contentAgent = Mockery::mock(ContentGeneratorAgent::class);
        $this->reflectionAgent = Mockery::mock(ReflectionAgent::class);
        $this->imageAnalyzer = Mockery::mock(ImageAnalyzerAgent::class);
        $this->imageGenerator = Mockery::mock(ParallelImageGenerator::class);
        $this->sse = Mockery::mock(SseBroadcaster::class);
        $this->config = new Config(['model' => ['default' => 'dashscope']]);
        $this->logger = Mockery::mock(StdoutLoggerInterface::class);

        $this->orchestrator = new WorkshopOrchestrator(
            $this->topicResearch,
            $this->titleAgent,
            $this->outlineAgent,
            $this->contentAgent,
            $this->reflectionAgent,
            $this->imageAnalyzer,
            $this->imageGenerator,
            $this->sse,
            $this->config,
            $this->logger
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Context::destroy('article_id');
        Context::destroy('user_id');
        parent::tearDown();
    }

    public function testReflectionPassedNoRewrite()
    {
        $article = $this->createArticleInOutlineEditing();
        $stream = Mockery::mock(EventStream::class);

        // Mock content generation
        $this->mockContentGeneration('测试正文内容', []);

        // Mock reflection - passed
        $passedPayload = new ReflectionPayload(
            scores: ['logic' => 8, 'fact' => 8, 'language' => 8, 'title' => 8, 'structure' => 8],
            suggestions: [],
            averageScore: 8.0
        );
        $this->reflectionAgent->shouldReceive('execute')
            ->once()
            ->andReturn(AgentOutcome::ok($passedPayload));

        // Mock image analysis and generation
        $this->mockImageProcessing();

        // Expect SSE events
        $this->expectSseEvents($stream, true, false);

        $this->orchestrator->generateContentAndImages($article, $stream);

        $article->refresh();
        $this->assertEquals(WorkshopState::COMPLETED->value, $article->status);
    }

    public function testReflectionFailedTriggerRewrite()
    {
        $article = $this->createArticleInOutlineEditing();
        $stream = Mockery::mock(EventStream::class);

        // Mock first content generation
        $this->mockContentGeneration('第一次生成内容', []);

        // Mock reflection - failed
        $failedPayload = new ReflectionPayload(
            scores: ['logic' => 6, 'fact' => 5, 'language' => 6, 'title' => 7, 'structure' => 6],
            suggestions: ['逻辑不够严谨', '事实性待验证'],
            averageScore: 6.0
        );
        $this->reflectionAgent->shouldReceive('execute')
            ->once()
            ->andReturn(AgentOutcome::degraded($failedPayload, '评分不足', 'reflection_degraded'));

        // Mock rewrite content generation
        $this->mockContentGeneration('重写后的内容', []);

        // Mock second reflection - passed
        $passedPayload = new ReflectionPayload(
            scores: ['logic' => 8, 'fact' => 8, 'language' => 8, 'title' => 8, 'structure' => 8],
            suggestions: [],
            averageScore: 8.0
        );
        $this->reflectionAgent->shouldReceive('execute')
            ->once()
            ->andReturn(AgentOutcome::ok($passedPayload));

        // Mock image processing
        $this->mockImageProcessing();

        // Expect SSE events
        $this->expectSseEvents($stream, true, true);

        $this->orchestrator->generateContentAndImages($article, $stream);

        $article->refresh();
        $this->assertEquals(WorkshopState::COMPLETED->value, $article->status);
    }

    public function testReflectionFailedAfterRewriteNoFurtherRewrite()
    {
        $article = $this->createArticleInOutlineEditing();
        $stream = Mockery::mock(EventStream::class);

        // Mock first content generation
        $this->mockContentGeneration('第一次生成内容', []);

        // Mock first reflection - failed
        $failedPayload = new ReflectionPayload(
            scores: ['logic' => 5, 'fact' => 5, 'language' => 6, 'title' => 6, 'structure' => 5],
            suggestions: ['需要改进'],
            averageScore: 5.4
        );
        $this->reflectionAgent->shouldReceive('execute')
            ->twice()
            ->andReturn(AgentOutcome::degraded($failedPayload, '评分不足', 'reflection_degraded'));

        // Mock rewrite
        $this->mockContentGeneration('重写后仍不通过', []);

        // Mock image processing
        $this->mockImageProcessing();

        // Expect SSE events
        $this->expectSseEvents($stream, true, true);

        $this->orchestrator->generateContentAndImages($article, $stream);

        $article->refresh();
        $this->assertEquals(WorkshopState::COMPLETED->value, $article->status);
    }

    public function testReflectionExceptionSkipAndContinue()
    {
        $article = $this->createArticleInOutlineEditing();
        $stream = Mockery::mock(EventStream::class);

        // Mock content generation
        $this->mockContentGeneration('测试正文内容', []);

        // Mock reflection - throws exception
        $this->reflectionAgent->shouldReceive('execute')
            ->once()
            ->andThrow(new \RuntimeException('Reflection service unavailable'));

        // Mock image processing
        $this->mockImageProcessing();

        // Expect SSE events with error
        $this->expectSseEventsWithReflectionError($stream);

        $this->logger->shouldReceive('warning')->once();

        $this->orchestrator->generateContentAndImages($article, $stream);

        $article->refresh();
        $this->assertEquals(WorkshopState::COMPLETED->value, $article->status);
    }

    // === 辅助方法 ===

    private function createArticleInOutlineEditing(): Article
    {
        $article = new Article();
        $article->user_id = 1;
        $article->topic = '测试主题';
        $article->style = '通用';
        $article->selected_title = '测试标题';
        $article->title = '测试标题';
        $article->outline = [
            'markdown' => '## 第一章\n## 第二章',
            'nodes' => [],
        ];
        $article->status = WorkshopState::OUTLINE_EDITING->value;
        $article->ai_model = 'dashscope';
        $article->save();

        return $article;
    }

    private function mockContentGeneration(string $content, array $placeholders): void
    {
        $generator = (function () use ($content) {
            yield '测';
            yield '试';
            return AgentOutcome::ok(new ContentDraft($content, $placeholders));
        })();

        $this->contentAgent->shouldReceive('executeStream')
            ->andReturn($generator);
    }

    private function mockImageProcessing(): void
    {
        $this->imageAnalyzer->shouldReceive('execute')->andReturn(
            AgentOutcome::ok((object) ['toArray' => fn() => []])
        );
        $this->imageAnalyzer->shouldReceive('getName')->andReturn('image_analyzer');

        $this->imageGenerator->shouldReceive('execute')->andReturn(
            AgentOutcome::ok((object) ['toArray' => fn() => []])
        );
        $this->imageGenerator->shouldReceive('getName')->andReturn('image_generator');
    }

    private function expectSseEvents(EventStream $stream, bool $withReflection, bool $withRewrite): void
    {
        $this->sse->shouldReceive('broadcastStateChange')->atLeast()->once();
        $this->sse->shouldReceive('broadcastContentChunk')->atLeast()->once();
        $this->sse->shouldReceive('broadcast')->atLeast()->once();
        $this->sse->shouldReceive('end')->once();

        if ($withReflection) {
            $this->sse->shouldReceive('broadcast')
                ->with($stream, 'reflection_started', [])
                ->atLeast()->once();
            $this->sse->shouldReceive('broadcast')
                ->with($stream, 'reflection_completed', Mockery::any())
                ->atLeast()->once();
        }

        if ($withRewrite) {
            $this->sse->shouldReceive('broadcast')
                ->with($stream, 'reflection_rewrite_triggered', [])
                ->once();
        }

        $this->logger->shouldReceive('info')->atLeast()->once();
    }

    private function expectSseEventsWithReflectionError(EventStream $stream): void
    {
        $this->sse->shouldReceive('broadcastStateChange')->atLeast()->once();
        $this->sse->shouldReceive('broadcastContentChunk')->atLeast()->once();
        $this->sse->shouldReceive('broadcast')->atLeast()->once();
        $this->sse->shouldReceive('end')->once();

        $this->sse->shouldReceive('broadcast')
            ->with($stream, 'reflection_started', [])
            ->once();
        $this->sse->shouldReceive('broadcast')
            ->with($stream, 'reflection_error', Mockery::on(function ($arg) {
                return isset($arg['error']);
            }))
            ->once();

        $this->logger->shouldReceive('info')->atLeast()->once();
    }
}
