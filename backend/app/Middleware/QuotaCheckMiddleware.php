<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constants\Code;
use App\Service\QuotaService;
use App\Service\VipService;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 配额检查中间件：
 *   - 必须挂在 JwtAuthMiddleware 之后（依赖 request attribute `user_id`）
 *   - 仅拦截 POST /api/workshop/create（创作入口，真正扣减配额的地方）
 *   - 配额不足时返回 403 JSON，携带当前配额信息供前端引导升级
 */
class QuotaCheckMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly QuotaService $quota,
        private readonly VipService $vip,
        private readonly HttpResponse $response
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = (int) $request->getAttribute('user_id');
        if ($userId <= 0) {
            // 应当由 JwtAuthMiddleware 处理；这里兜底放行避免影响登录态
            return $handler->handle($request);
        }

        if ($this->quota->checkQuota($userId)) {
            return $handler->handle($request);
        }

        $usage = $this->quota->getQuotaUsage($userId);
        $vipInfo = $this->vip->getUserVipInfo($userId);

        $body = json_encode([
            'code' => Code::QUOTA_EXCEEDED,
            'message' => '配额不足，请升级 VIP 会员',
            'data' => [
                'current_plan' => $vipInfo['vip_level'] ?? 'free',
                'effective_level' => $vipInfo['effective_level'] ?? 'free',
                'quota_total' => $usage['total'],
                'quota_used' => $usage['used'],
                'quota_remaining' => $usage['remaining'],
                'reset_at' => $usage['reset_at'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        return $this->response->withStatus(403)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream((string) $body));
    }
}
