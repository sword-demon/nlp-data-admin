<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\Code;
use App\Enum\WorkshopState;
use App\Exception\BusinessException;
use App\Model\Article;
use App\Service\Agent\ContentGeneratorAgent;
use App\Service\Agent\ImageAnalyzerAgent;
use App\Service\Agent\OutlineGeneratorAgent;
use App\Service\Agent\ParallelImageGenerator;
use App\Service\Agent\TitleGeneratorAgent;
use App\Service\Agent\TopicResearchAgent;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Engine\Http\EventStream;
use Throwable;

/**
 * 创作工坊编排器（状态机 + Agent 调度）。
 *
 * 状态流：
 *   DRAFT → TOPIC_RESEARCHING → TITLE_GENERATING → TITLE_SELECTING   (同步 HTTP 返回)
 *         → OUTLINE_GENERATING → OUTLINE_EDITING                     (同步 HTTP 返回)
 *         → CONTENT_GENERATING → IMAGE_ANALYZING → IMAGE_GENERATING → COMPLETED
 *         └─────────────────── SSE 流式推送 ──────────────────┘
 *
 * Phase 3 简化设计：
 * - 不使用 Redis Pub/Sub；SSE 端点本身就是执行链路的 HTTP 长连接
 * - 所有状态持久化到 articles 表，过程数据就存在 article 的字段上
 */
class WorkshopOrchestrator
{
    public function __construct(
        private readonly TopicResearchAgent $topicResearch,
        private readonly TitleGeneratorAgent $titleAgent,
        private readonly OutlineGeneratorAgent $outlineAgent,
        private readonly ContentGeneratorAgent $contentAgent,
        private readonly ImageAnalyzerAgent $imageAnalyzer,
        private readonly ParallelImageGenerator $imageGenerator,
        private readonly SseBroadcaster $sse,
        private readonly ConfigInterface $config,
        private readonly StdoutLoggerInterface $logger
    ) {}

    /**
     * 第 1 步：创建文章 → 选题研究（可降级）→ 生成候选标题。
     *
     * @return array{article: Article, titles: array<int, array<string, mixed>>, research_fallback: bool}
     */
    public function startCreation(int $userId, string $topic, string $style): array
    {
        if ($topic === '') {
            throw new BusinessException(Code::VALIDATION_ERROR, 'topic is required', 422);
        }
        $style = $style !== '' ? $style : '通用';

        $article = new Article();
        $article->user_id = $userId;
        $article->topic = $topic;
        $article->style = $style;
        $article->title = '';
        $article->content = '';
        $article->status = WorkshopState::DRAFT->value;
        $article->ai_model = (string) $this->config->get('model.default', 'dashscope');
        $article->save();

        // 写入 Context，供 AgentLogAspect 关联 article_id
        Context::set('article_id', (int) $article->getKey());
        Context::set('user_id', $userId);

        // === 阶段 1a：选题研究（增强能力，失败降级） ===
        // 当前 slice 01 桩永远降级；slice 02/03 接入 Exa + 四道防线。
        $this->transitionTo($article, WorkshopState::TOPIC_RESEARCHING);

        $researchFallback = true;
        try {
            $researchResult = $this->topicResearch->execute(['topic' => $topic]);
            $article->research_data = $researchResult['research_data'] ?? null;
            $researchFallback = (bool) ($researchResult['fallback'] ?? true);
            $article->save();
        } catch (Throwable $e) {
            // 研究失败不阻塞主链路（ADR 0001）
            $this->logger->warning(
                "[Workshop#{$article->getKey()}] topic research exception, degraded: {$e->getMessage()}"
            );
            $article->research_data = null;
            $article->save();
            $researchFallback = true;
        }

        // === 阶段 1b：标题生成 ===
        $this->transitionTo($article, WorkshopState::TITLE_GENERATING);

        try {
            $result = $this->titleAgent->execute([
                'topic' => $topic,
                'style' => $style,
                'research_data' => $article->research_data,
            ]);
        } catch (Throwable $e) {
            $this->markFailed($article, $e->getMessage());
            throw $e;
        }

        /** @var array<int, array<string, mixed>> $titles */
        $titles = $result['titles'];
        $article->generated_titles = $titles;
        $article->save();

        $this->transitionTo($article, WorkshopState::TITLE_SELECTING);

        return [
            'article' => $article,
            'titles' => $titles,
            'research_fallback' => $researchFallback,
        ];
    }

    /**
     * 第 2 步：用户选择标题，生成大纲。
     *
     * @return array{outline: array<string, mixed>}
     */
    public function selectTitle(Article $article, int $titleIndex, string $supplement): array
    {
        Context::set('article_id', (int) $article->getKey());
        Context::set('user_id', (int) $article->user_id);
        $this->assertState($article, WorkshopState::TITLE_SELECTING);

        $titles = (array) ($article->generated_titles ?? []);
        if (! isset($titles[$titleIndex]) || ! is_array($titles[$titleIndex])) {
            throw new BusinessException(
                Code::WORKSHOP_TITLE_INDEX_INVALID,
                "title index [{$titleIndex}] out of range",
                422
            );
        }

        $selected = (string) $titles[$titleIndex]['title'];
        $article->selected_title = $selected;
        $article->title = $selected;
        $article->title_supplement = $supplement;
        $article->save();

        $this->transitionTo($article, WorkshopState::OUTLINE_GENERATING);

        try {
            $result = $this->outlineAgent->execute([
                'title' => $selected,
                'supplement' => $supplement,
                'research_data' => $article->research_data,
            ]);
        } catch (Throwable $e) {
            $this->markFailed($article, $e->getMessage());
            throw $e;
        }

        $article->outline = $result;
        $article->save();

        $this->transitionTo($article, WorkshopState::OUTLINE_EDITING);

        return ['outline' => $result];
    }

    /**
     * 第 2.5 步：用户编辑大纲（保持在 OUTLINE_EDITING 状态）。
     *
     * @param array<string, mixed> $outline
     */
    public function updateOutline(Article $article, array $outline): void
    {
        Context::set('article_id', (int) $article->getKey());
        Context::set('user_id', (int) $article->user_id);
        $this->assertState($article, WorkshopState::OUTLINE_EDITING);

        if (empty($outline['markdown']) || empty($outline['nodes'])) {
            throw new BusinessException(Code::VALIDATION_ERROR, 'outline.markdown and outline.nodes required', 422);
        }

        $article->outline = $outline;
        $article->save();
    }

    /**
     * 第 3 步（SSE 流式）：从大纲生成正文 → 分析配图 → 生成配图。
     *
     * 执行过程中实时通过 $stream 广播事件：state / content_chunk / error / done
     */
    public function generateContentAndImages(Article $article, EventStream $stream): void
    {
        Context::set('article_id', (int) $article->getKey());
        Context::set('user_id', (int) $article->user_id);
        $this->assertState($article, WorkshopState::OUTLINE_EDITING);

        // === 阶段 3a：正文生成（流式） ===
        $this->transitionTo($article, WorkshopState::CONTENT_GENERATING);
        $this->sse->broadcastStateChange($stream, WorkshopState::CONTENT_GENERATING, ['title' => $article->selected_title]);

        $outline = (array) ($article->outline ?? []);
        $content = '';
        try {
            $generator = $this->contentAgent->executeStream([
                'title' => (string) $article->selected_title,
                'style' => (string) $article->style,
                'supplement' => (string) $article->title_supplement,
                'outline_markdown' => (string) ($outline['markdown'] ?? ''),
                'research_data' => $article->research_data,
            ]);
            foreach ($generator as $chunk) {
                $content .= $chunk;
                $this->sse->broadcastContentChunk($stream, $chunk);
            }
        } catch (Throwable $e) {
            $this->markFailed($article, $e->getMessage());
            $this->sse->broadcastError($stream, $e->getMessage(), $this->contentAgent->getName());
            $this->sse->end($stream);
            return;
        }

        $placeholders = $this->contentAgent->extractPlaceholders($content);
        $article->content = $content;
        $article->word_count = mb_strlen($content);
        $article->save();

        $this->sse->broadcast($stream, 'content_completed', [
            'word_count' => mb_strlen($content),
            'placeholder_count' => count($placeholders),
        ]);

        // === 阶段 3b：配图分析 ===
        $this->transitionTo($article, WorkshopState::IMAGE_ANALYZING);
        $this->sse->broadcastStateChange($stream, WorkshopState::IMAGE_ANALYZING);

        try {
            $analyzeResult = $this->imageAnalyzer->execute([
                'content' => $content,
                'placeholders' => $placeholders,
                'research_data' => $article->research_data,
            ]);
        } catch (Throwable $e) {
            $this->markFailed($article, $e->getMessage());
            $this->sse->broadcastError($stream, $e->getMessage(), $this->imageAnalyzer->getName());
            $this->sse->end($stream);
            return;
        }

        $this->sse->broadcast($stream, 'image_analyzed', $analyzeResult);

        // === 阶段 3c：并行配图（Phase 3 占位） ===
        $this->transitionTo($article, WorkshopState::IMAGE_GENERATING);
        $this->sse->broadcastStateChange($stream, WorkshopState::IMAGE_GENERATING);

        try {
            $imageResult = $this->imageGenerator->execute([
                'analyses' => $analyzeResult['analyses'],
            ]);
        } catch (Throwable $e) {
            $this->markFailed($article, $e->getMessage());
            $this->sse->broadcastError($stream, $e->getMessage(), $this->imageGenerator->getName());
            $this->sse->end($stream);
            return;
        }

        $images = (array) ($imageResult['images'] ?? []);

        // 将正文中的 placeholder://image/N 替换为真实 Markdown 图片
        $replacedContent = $this->replaceImagePlaceholders($content, $images);

        $article->images = $images;
        $article->content = $replacedContent;
        $article->word_count = mb_strlen($replacedContent);
        $article->save();

        // 依次推送每张图片的 image_ready，方便前端逐条点亮
        foreach ($images as $img) {
            $this->sse->broadcast($stream, 'image_ready', $img);
        }

        // 最终汇总事件：image_generated + 更新后的正文
        $this->sse->broadcast($stream, 'image_generated', ['images' => $images]);
        $this->sse->broadcast($stream, 'content_updated', [
            'content' => $replacedContent,
            'word_count' => mb_strlen($replacedContent),
        ]);

        // === 完成 ===
        $this->transitionTo($article, WorkshopState::COMPLETED);
        $this->sse->broadcastStateChange($stream, WorkshopState::COMPLETED, [
            'article_id' => $article->getKey(),
            'word_count' => (int) $article->word_count,
        ]);

        $this->sse->end($stream);
    }

    // ============ 状态机工具 ============

    /** 校验 article 当前状态必须是 $expected。 */
    private function assertState(Article $article, WorkshopState $expected): void
    {
        $current = $this->currentState($article);
        if ($current !== $expected) {
            throw new BusinessException(
                Code::WORKSHOP_INVALID_STATE,
                "expected state [{$expected->value}], got [{$current->value}]",
                409
            );
        }
    }

    private function currentState(Article $article): WorkshopState
    {
        $raw = (string) $article->status;
        $state = WorkshopState::tryFrom($raw);
        if ($state === null) {
            // 兼容历史 legacy 状态（title_selected 等）：视作 DRAFT
            $state = WorkshopState::DRAFT;
        }
        return $state;
    }

    private function transitionTo(Article $article, WorkshopState $target): void
    {
        $current = $this->currentState($article);
        if ($target !== WorkshopState::FAILED && ! $target->isReachableFrom($current)) {
            throw new BusinessException(
                Code::WORKSHOP_INVALID_STATE,
                "cannot transition from [{$current->value}] to [{$target->value}]",
                409
            );
        }
        $article->status = $target->value;
        $article->save();
        $this->logger->info("[Workshop#{$article->getKey()}] {$current->value} → {$target->value}");
    }

    private function markFailed(Article $article, string $reason): void
    {
        $this->logger->error("[Workshop#{$article->getKey()}] FAILED: {$reason}");
        $article->status = WorkshopState::FAILED->value;
        $article->save();
    }

    /**
     * 将正文中的 ![配图:xxx](placeholder://image/N) 替换为真实图片 Markdown。
     *
     * @param array<int, array<string, mixed>> $images
     */
    private function replaceImagePlaceholders(string $content, array $images): string
    {
        foreach ($images as $img) {
            $pid = (string) ($img['placeholder_id'] ?? '');
            $url = (string) ($img['url'] ?? '');
            if ($pid === '' || $url === '') {
                continue;
            }
            $alt = (string) ($img['alt'] ?? ($img['keyword'] ?? 'image'));
            $alt = str_replace([']', "\n", "\r"], ' ', $alt);
            $attribution = (string) ($img['attribution'] ?? '');

            $markdown = "![{$alt}]({$url})";
            if ($attribution !== '') {
                $markdown .= "\n*{$attribution}*";
            }

            // placeholder_id 形如 "image/3"；配置符可能为中文或英文
            $pattern = '/!\[配图:[^\]]*\]\(placeholder:\/\/' . preg_quote($pid, '/') . '\)/u';
            $content = preg_replace($pattern, $markdown, $content) ?? $content;
        }
        return $content;
    }
}
