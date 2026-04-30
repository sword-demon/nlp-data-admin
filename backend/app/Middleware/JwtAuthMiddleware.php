<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constants\Code;
use App\Exception\BusinessException;
use App\Model\User;
use App\Service\JwtService;
use Hyperf\Context\Context;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * JWT 认证中间件：
 *  - 从 Authorization: Bearer <token> 或 ?token= 提取 token
 *  - 验签 + 查库 → 把 User 实例通过 request attribute 透传给 Controller
 *  - 失败时直接返回 401 JSON（不走 AppExceptionHandler，避免日志噪音）
 *
 * 按路由组挂载（不是全局），以便 /api/auth/login、/api/auth/register 公开。
 */
class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly HttpResponse $response
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);
        if ($token === '') {
            return $this->unauthorized(Code::TOKEN_MISSING, 'Token missing');
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (BusinessException $e) {
            return $this->unauthorized($e->getCode() ?: Code::TOKEN_INVALID, $e->getMessage());
        }

        $sub = (string) ($payload['sub'] ?? '');
        $user = $sub !== '' ? User::retrieveById($sub) : null;
        if (! $user) {
            return $this->unauthorized(Code::USER_NOT_FOUND, 'User not found');
        }

        // 把认证上下文附着到 request
        $request = $request
            ->withAttribute('user', $user)
            ->withAttribute('user_id', (int) $user->getKey())
            ->withAttribute('jwt_payload', $payload);

        // 同时写入 Hyperf Context，便于 AOP / Service 层跨调用链取用（如 AgentLogAspect）
        Context::set('user_id', (int) $user->getKey());

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): string
    {
        $auth = $request->getHeaderLine('Authorization');
        if ($auth !== '' && stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }

        // SSE 浏览器原生 EventSource 无法设置 header，兜底从 query 读取
        $queryToken = $request->getQueryParams()['token'] ?? '';

        return is_string($queryToken) ? trim($queryToken) : '';
    }

    private function unauthorized(int $code, string $message): ResponseInterface
    {
        $body = json_encode([
            'code' => $code,
            'message' => $message,
            'data' => null,
        ], JSON_UNESCAPED_UNICODE);

        return $this->response->withStatus(401)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream((string) $body));
    }
}
