<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constants\Code;
use App\Model\User;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * AdminMiddleware — 管理员角色检查中间件。
 *
 * 使用约束：
 *  - 必须挂载在 JwtAuthMiddleware 之后（依赖 request 中已注入的 user 实例）
 *  - 仅放行 role === User::ROLE_ADMIN 的用户
 *  - 非管理员返回 403 JSON
 */
class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly HttpResponse $response) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (! $user instanceof User) {
            return $this->forbidden(Code::TOKEN_INVALID, 'Auth context missing');
        }

        if ((string) $user->getAttribute('role') !== User::ROLE_ADMIN) {
            return $this->forbidden(Code::FORBIDDEN, 'Admin role required');
        }

        return $handler->handle($request);
    }

    private function forbidden(int $code, string $message): ResponseInterface
    {
        $body = json_encode([
            'code' => $code,
            'message' => $message,
            'data' => null,
        ], JSON_UNESCAPED_UNICODE);

        return $this->response->withStatus(403)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream((string) $body));
    }
}
