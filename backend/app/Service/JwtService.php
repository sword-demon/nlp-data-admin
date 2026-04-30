<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\Code;
use App\Contract\JwtSubjectInterface;
use App\Exception\BusinessException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Hyperf\Contract\ConfigInterface;
use Throwable;

/**
 * JWT 编解码服务：基于 firebase/php-jwt，对上层仅暴露业务语义。
 */
class JwtService
{
    private string $secret;

    private string $algo;

    private int $ttl;

    private int $refreshTtl;

    private string $issuer;

    public function __construct(ConfigInterface $config)
    {
        $this->secret = (string) $config->get('jwt.secret', '');
        $this->algo = (string) $config->get('jwt.algo', 'HS256');
        $this->ttl = (int) $config->get('jwt.ttl', 86400);
        $this->refreshTtl = (int) $config->get('jwt.refresh_ttl', 604800);
        $this->issuer = (string) $config->get('jwt.issuer', 'nlp-data-admin');

        if ($this->secret === '' || str_starts_with($this->secret, 'please-')) {
            // 仅在首次启动未设置密钥时提醒，不直接 fail fast，允许启动流程继续
            trigger_error('JWT_SECRET not configured; tokens cannot be issued.', E_USER_WARNING);
        }
    }

    /**
     * 为指定主体签发访问令牌，返回 token + 过期时间戳。
     *
     * @return array{token: string, expires_at: int, ttl: int}
     */
    public function issue(JwtSubjectInterface $subject, array $extraClaims = []): array
    {
        $now = time();
        $exp = $now + $this->ttl;

        $payload = array_merge([
            'iss' => $this->issuer,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $exp,
            'sub' => $subject->getJwtIdentifier(),
        ], $subject->getJwtCustomClaims(), $extraClaims);

        return [
            'token' => JWT::encode($payload, $this->secret, $this->algo),
            'expires_at' => $exp,
            'ttl' => $this->ttl,
        ];
    }

    /**
     * 解码并校验 token，返回 payload 关联数组；校验失败抛 BusinessException。
     *
     * @return array<string, mixed>
     */
    public function decode(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));

            return (array) $decoded;
        } catch (ExpiredException) {
            throw new BusinessException(Code::TOKEN_EXPIRED, 'Token expired', 401);
        } catch (SignatureInvalidException) {
            throw new BusinessException(Code::TOKEN_INVALID, 'Token signature invalid', 401);
        } catch (Throwable $e) {
            throw new BusinessException(Code::TOKEN_INVALID, 'Token invalid: ' . $e->getMessage(), 401);
        }
    }

    /**
     * 基于旧 token 生成新 token；要求 iat 仍在 refresh_ttl 窗口内。
     *
     * @return array{token: string, expires_at: int, ttl: int}
     */
    public function refresh(string $token, JwtSubjectInterface $subject): array
    {
        $payload = $this->decodeEvenExpired($token);
        $iat = (int) ($payload['iat'] ?? 0);

        if ($iat <= 0 || time() - $iat > $this->refreshTtl) {
            throw new BusinessException(Code::TOKEN_EXPIRED, 'Refresh window closed, please re-login', 401);
        }

        return $this->issue($subject);
    }

    /**
     * 解码时忽略过期异常（仅用于 refresh 场景）。
     *
     * @return array<string, mixed>
     */
    public function decodeEvenExpired(string $token): array
    {
        // 解析 payload（不验签直接 base64 解，再单独验签）
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new BusinessException(Code::TOKEN_INVALID, 'Malformed token', 401);
        }

        // 先走标准验签；过期异常单独兜住
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));

            return (array) $decoded;
        } catch (ExpiredException) {
            // 仅在签名有效但过期时允许取出 payload
            $raw = base64_decode(strtr($parts[1], '-_', '+/'), true);
            if ($raw === false) {
                throw new BusinessException(Code::TOKEN_INVALID, 'Malformed token', 401);
            }
            $payload = json_decode($raw, true);

            return is_array($payload) ? $payload : [];
        } catch (Throwable $e) {
            throw new BusinessException(Code::TOKEN_INVALID, 'Token invalid: ' . $e->getMessage(), 401);
        }
    }
}
