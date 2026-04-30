<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\Code;
use App\Enum\WorkshopState;
use App\Exception\BusinessException;
use App\Helpers\ApiResponse;
use App\Model\Article;
use App\Service\SseBroadcaster;
use App\Service\QuotaService;
use App\Service\WorkshopOrchestrator;
use Hyperf\Context\ResponseContext;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Engine\Http\EventStream;
use Hyperf\HttpMessage\Server\Response as HyperfServerResponse;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * 创作工坊控制器。
 *
 * 路由前缀 /api/workshop（全部受 JWT 保护）：
 *   POST   /create                  启动创作，生成候选标题
 *   POST   /{id}/select-title       选择标题，触发大纲生成
 *   PUT    /{id}/outline            用户编辑大纲
 *   GET    /{id}/generate-stream    SSE：正文生成 → 配图分析 → 配图生成
 *   GET    /{id}/status             查询状态机当前位置 + 过程数据
 *   GET    /{id}/result             查询最终成果（title/outline/content/images）
 *
 * 所有 action 都做 user_id 归属校验：非本人文章统一抛 WORKSHOP_FORBIDDEN。
 */
class WorkshopController extends AbstractController
{
    #[Inject]
    protected WorkshopOrchestrator $orchestrator;

    #[Inject]
    protected SseBroadcaster $sse;

    #[Inject]
    protected QuotaService $quota;

    /** POST /api/workshop/create */
    public function create(): ResponseInterface
    {
        $userId = $this->currentUserId();
        $topic = trim((string) $this->request->input('topic', ''));
        $style = trim((string) $this->request->input('style', ''));

        $result = $this->orchestrator->startCreation($userId, $topic, $style);

        /** @var Article $article */
        $article = $result['article'];

        // 创作启动成功 → 扣减配额（中间件已在入口检查过）
        $this->quota->consumeQuota($userId);

        return ApiResponse::success($this->response, [
            'article_id' => (int) $article->getKey(),
            'status' => $article->status,
            'topic' => $article->topic,
            'style' => $article->style,
            'titles' => $result['titles'],
        ], 'title_candidates_generated');
    }

    /** POST /api/workshop/{id}/select-title */
    public function selectTitle(int $id): ResponseInterface
    {
        $article = $this->loadArticle($id);

        $titleIndex = (int) $this->request->input('title_index', -1);
        $supplement = trim((string) $this->request->input('supplement', ''));

        if ($titleIndex < 0) {
            throw new BusinessException(Code::VALIDATION_ERROR, 'title_index is required', 422);
        }

        $result = $this->orchestrator->selectTitle($article, $titleIndex, $supplement);

        return ApiResponse::success($this->response, [
            'article_id' => (int) $article->getKey(),
            'status' => $article->status,
            'selected_title' => $article->selected_title,
            'outline' => $result['outline'],
        ], 'outline_generated');
    }

    /** PUT /api/workshop/{id}/outline */
    public function updateOutline(int $id): ResponseInterface
    {
        $article = $this->loadArticle($id);

        $outline = $this->request->input('outline');
        if (! is_array($outline)) {
            throw new BusinessException(Code::VALIDATION_ERROR, 'outline must be an object', 422);
        }

        $this->orchestrator->updateOutline($article, $outline);

        return ApiResponse::success($this->response, [
            'article_id' => (int) $article->getKey(),
            'status' => $article->status,
            'outline' => $article->outline,
        ], 'outline_updated');
    }

    /**
     * GET /api/workshop/{id}/generate-stream — SSE 流式
     *
     * 执行链路（同一 HTTP 长连接内逐 chunk 推送）：
     *   CONTENT_GENERATING → IMAGE_ANALYZING → IMAGE_GENERATING → COMPLETED
     *
     * 前端 EventSource 通过 ?token=xxx 传 JWT（原生 EventSource 不能设 header）。
     */
    public function generateStream(int $id): ResponseInterface
    {
        $article = $this->loadArticle($id);

        $psr7 = ResponseContext::get();
        $connection = $psr7 instanceof HyperfServerResponse ? $psr7->getConnection() : null;
        if (! $connection) {
            throw new BusinessException(Code::AI_STREAM_ERROR, 'SSE not supported in current context', 500);
        }

        $stream = new EventStream($connection);

        try {
            $this->orchestrator->generateContentAndImages($article, $stream);
        } catch (Throwable $e) {
            // Orchestrator 内部已做 markFailed + broadcastError，这里兜底防意外
            $this->sse->broadcastError($stream, $e->getMessage());
            $this->sse->end($stream);
        }

        // 连接已由 EventStream::end() 关闭；返回空响应避免 Hyperf 再次写 body
        return $this->response->raw('');
    }

    /** GET /api/workshop/{id}/status */
    public function status(int $id): ResponseInterface
    {
        $article = $this->loadArticle($id);

        $state = WorkshopState::tryFrom((string) $article->status) ?? WorkshopState::DRAFT;

        return ApiResponse::success($this->response, [
            'article_id' => (int) $article->getKey(),
            'status' => $state->value,
            'is_terminal' => $state->isTerminal(),
            'is_waiting_user' => $state->isWaitingUser(),
            'topic' => $article->topic,
            'style' => $article->style,
            'selected_title' => $article->selected_title,
            'generated_titles' => $article->generated_titles,
            'title_supplement' => $article->title_supplement,
            'outline' => $article->outline,
            'word_count' => (int) $article->word_count,
            'updated_at' => $article->updated_at?->format('c'),
        ]);
    }

    /** GET /api/workshop/{id}/result */
    public function result(int $id): ResponseInterface
    {
        $article = $this->loadArticle($id);

        return ApiResponse::success($this->response, [
            'article_id' => (int) $article->getKey(),
            'status' => $article->status,
            'title' => $article->title,
            'selected_title' => $article->selected_title,
            'topic' => $article->topic,
            'style' => $article->style,
            'outline' => $article->outline,
            'content' => $article->content,
            'images' => $article->images,
            'word_count' => (int) $article->word_count,
            'ai_model' => $article->ai_model,
            'created_at' => $article->created_at?->format('c'),
            'updated_at' => $article->updated_at?->format('c'),
        ]);
    }

    /**
     * 加载文章并校验当前 JWT 用户的归属权。
     *
     * - 404：文章不存在
     * - 403：文章存在但 user_id 不匹配
     */
    private function loadArticle(int $id): Article
    {
        $userId = $this->currentUserId();

        /** @var Article|null $article */
        $article = Article::query()->find($id);
        if (! $article) {
            throw new BusinessException(Code::WORKSHOP_NOT_FOUND, "Article [{$id}] not found", 404);
        }

        if ((int) $article->user_id !== $userId) {
            throw new BusinessException(Code::WORKSHOP_FORBIDDEN, 'You do not own this article', 403);
        }

        return $article;
    }

    private function currentUserId(): int
    {
        $userId = (int) $this->request->getAttribute('user_id');
        if ($userId <= 0) {
            throw new BusinessException(Code::TOKEN_INVALID, 'Auth context missing', 401);
        }
        return $userId;
    }
}
