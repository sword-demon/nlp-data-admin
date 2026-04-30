<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\Code;
use App\Exception\BusinessException;
use App\Helpers\ApiResponse;
use App\Model\User;
use App\Service\AuthService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;

class AuthController extends AbstractController
{
    #[Inject]
    protected AuthService $auth;

    /**
     * POST /api/auth/register
     * body: { username, email, password }
     */
    public function register(): ResponseInterface
    {
        $data = [
            'username' => (string) $this->request->input('username', ''),
            'email' => (string) $this->request->input('email', ''),
            'password' => (string) $this->request->input('password', ''),
        ];

        $result = $this->auth->register($data);

        return ApiResponse::success($this->response, $this->formatAuthResult($result), 'registered');
    }

    /**
     * POST /api/auth/login
     * body: { email, password }
     */
    public function login(): ResponseInterface
    {
        $email = (string) $this->request->input('email', '');
        $password = (string) $this->request->input('password', '');

        if ($email === '' || $password === '') {
            throw new BusinessException(Code::VALIDATION_ERROR, 'Email and password required', 422);
        }

        $result = $this->auth->login($email, $password);

        return ApiResponse::success($this->response, $this->formatAuthResult($result), 'logged in');
    }

    /**
     * POST /api/auth/refresh  (protected)
     * 使用当前请求的 Authorization header 作为旧 token。
     */
    public function refresh(): ResponseInterface
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $oldToken = '';
        if (stripos($authHeader, 'Bearer ') === 0) {
            $oldToken = trim(substr($authHeader, 7));
        }
        if ($oldToken === '') {
            throw new BusinessException(Code::TOKEN_MISSING, 'Bearer token missing', 401);
        }

        $result = $this->auth->refresh($oldToken);

        return ApiResponse::success($this->response, $this->formatAuthResult($result), 'refreshed');
    }

    /**
     * POST /api/auth/logout  (protected)
     * 无状态 JWT：当前不维护黑名单，前端丢弃 token 即可。
     * 保留端点供未来接入 Redis 黑名单使用。
     */
    public function logout(): ResponseInterface
    {
        return ApiResponse::success($this->response, null, 'logged out');
    }

    /**
     * GET /api/auth/me  (protected)
     */
    public function me(): ResponseInterface
    {
        /** @var User|null $user */
        $user = $this->request->getAttribute('user');
        if (! $user) {
            throw new BusinessException(Code::TOKEN_INVALID, 'Auth context missing', 401);
        }

        return ApiResponse::success($this->response, $user->toArray());
    }

    /**
     * 把 AuthService 的返回统一序列化。
     *
     * @param array{user: User, token: string, expires_at: int, ttl: int} $result
     *
     * @return array<string, mixed>
     */
    private function formatAuthResult(array $result): array
    {
        return [
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'ttl' => $result['ttl'],
            'user' => $result['user']->toArray(),
        ];
    }
}
