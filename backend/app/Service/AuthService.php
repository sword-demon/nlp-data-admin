<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\Code;
use App\Exception\BusinessException;
use App\Model\User;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Db;

/**
 * 用户认证业务：注册 / 登录 / 刷新 / 当前用户。
 * 不承担 HTTP 细节，由 Controller 负责 IO 层。
 */
class AuthService
{
    public function __construct(private readonly JwtService $jwt) {}

    /**
     * 注册新用户并返回用户实例与首次登录 token。
     *
     * @param array{username: string, email: string, password: string} $data
     *
     * @return array{user: User, token: string, expires_at: int, ttl: int}
     */
    public function register(array $data): array
    {
        $username = trim($data['username'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || mb_strlen($username) > 50) {
            throw new BusinessException(Code::VALIDATION_ERROR, 'Username required (1-50 chars)', 422);
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessException(Code::VALIDATION_ERROR, 'Invalid email format', 422);
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 64) {
            throw new BusinessException(Code::VALIDATION_ERROR, 'Password length 6-64', 422);
        }

        // 事务内确保唯一性 + 写入
        /** @var User $user */
        $user = Db::transaction(function () use ($username, $email, $password) {
            if (User::query()->where('username', $username)->exists()) {
                throw new BusinessException(Code::USER_ALREADY_EXISTS, 'Username taken', 409);
            }
            if (User::query()->where('email', $email)->exists()) {
                throw new BusinessException(Code::EMAIL_ALREADY_EXISTS, 'Email registered', 409);
            }

            $u = new User();
            $cfg = ApplicationContext::getContainer()->get(ConfigInterface::class);
            $freeQuota = (int) $cfg->get('vip.plans.free.quota_monthly', 5);
            $u->fill([
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role' => User::ROLE_USER,
                'vip_level' => User::VIP_FREE,
                'quota_total' => $freeQuota,
                'quota_used' => 0,
            ]);
            $u->save();

            return $u;
        });

        $tk = $this->jwt->issue($user);

        return [
            'user' => $user,
            'token' => $tk['token'],
            'expires_at' => $tk['expires_at'],
            'ttl' => $tk['ttl'],
        ];
    }

    /**
     * 邮箱 + 密码登录。
     *
     * @return array{user: User, token: string, expires_at: int, ttl: int}
     */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! password_verify($password, (string) $user->getAttribute('password_hash'))) {
            throw new BusinessException(Code::INVALID_CREDENTIALS, 'Invalid email or password', 401);
        }

        $tk = $this->jwt->issue($user);

        return [
            'user' => $user,
            'token' => $tk['token'],
            'expires_at' => $tk['expires_at'],
            'ttl' => $tk['ttl'],
        ];
    }

    /**
     * 刷新令牌：基于旧 token 的 sub 字段反查用户，重新签发。
     *
     * @return array{user: User, token: string, expires_at: int, ttl: int}
     */
    public function refresh(string $oldToken): array
    {
        $payload = $this->jwt->decodeEvenExpired($oldToken);
        $sub = (string) ($payload['sub'] ?? '');
        if ($sub === '') {
            throw new BusinessException(Code::TOKEN_INVALID, 'Missing sub claim', 401);
        }

        $user = User::retrieveById($sub);
        if (! $user) {
            throw new BusinessException(Code::USER_NOT_FOUND, 'User not found', 404);
        }

        $tk = $this->jwt->refresh($oldToken, $user);

        return [
            'user' => $user,
            'token' => $tk['token'],
            'expires_at' => $tk['expires_at'],
            'ttl' => $tk['ttl'],
        ];
    }
}
