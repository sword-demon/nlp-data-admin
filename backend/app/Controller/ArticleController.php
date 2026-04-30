<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\Code;
use App\Exception\BusinessException;
use App\Helpers\ApiResponse;
use App\Model\Article;
use Psr\Http\Message\ResponseInterface;

/**
 * ArticleController — 用户侧文章列表 / 详情 / 删除。
 *
 * 路由前缀：/api/articles（受 JwtAuthMiddleware 保护）
 *   GET    /                     分页列表（仅本人）
 *   GET    /{id}                 单篇详情（归属校验）
 *   DELETE /{id}                 删除（归属校验）
 */
class ArticleController extends AbstractController
{
    /** GET /api/articles?page=&limit=&status=&keyword= */
    public function index(): ResponseInterface
    {
        $userId = $this->currentUserId();
        $page = max(1, (int) $this->request->input('page', 1));
        $limit = (int) $this->request->input('limit', 20);
        $limit = max(1, min(100, $limit));
        $status = trim((string) $this->request->input('status', ''));
        $keyword = trim((string) $this->request->input('keyword', ''));

        $q = Article::query()->where('user_id', $userId);
        if ($status !== '') {
            $q->where('status', $status);
        }
        if ($keyword !== '') {
            $q->where(function ($sub) use ($keyword) {
                $sub->where('title', 'like', "%{$keyword}%")
                    ->orWhere('selected_title', 'like', "%{$keyword}%")
                    ->orWhere('topic', 'like', "%{$keyword}%");
            });
        }

        $total = (int) (clone $q)->count();
        $rows = $q->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get([
                'id',
                'user_id',
                'title',
                'selected_title',
                'topic',
                'style',
                'status',
                'word_count',
                'ai_model',
                'created_at',
                'updated_at',
            ]);

        $list = $rows->map(fn($a) => [
            'id' => (int) $a->id,
            'title' => (string) ($a->selected_title ?: $a->title ?: $a->topic),
            'topic' => (string) $a->topic,
            'style' => (string) $a->style,
            'status' => (string) $a->status,
            'word_count' => (int) $a->word_count,
            'ai_model' => $a->ai_model,
            'created_at' => $a->created_at?->format('c'),
            'updated_at' => $a->updated_at?->format('c'),
        ])->all();

        return ApiResponse::paginate($this->response, $list, $total, $page, $limit);
    }

    /** GET /api/articles/{id} */
    public function show(int $id): ResponseInterface
    {
        $article = $this->loadArticle($id);

        return ApiResponse::success($this->response, [
            'id' => (int) $article->id,
            'title' => (string) ($article->selected_title ?: $article->title ?: $article->topic),
            'selected_title' => $article->selected_title,
            'topic' => $article->topic,
            'style' => $article->style,
            'outline' => $article->outline,
            'content' => $article->content,
            'images' => $article->images,
            'status' => $article->status,
            'word_count' => (int) $article->word_count,
            'ai_model' => $article->ai_model,
            'created_at' => $article->created_at?->format('c'),
            'updated_at' => $article->updated_at?->format('c'),
        ]);
    }

    /** DELETE /api/articles/{id} */
    public function destroy(int $id): ResponseInterface
    {
        $article = $this->loadArticle($id);
        $article->delete();
        return ApiResponse::success($this->response, ['id' => $id], 'article_deleted');
    }

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
