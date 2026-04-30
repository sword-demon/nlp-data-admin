<?php

declare(strict_types=1);

namespace App\Model;

use App\Contract\JwtSubjectInterface;
use Hyperf\DbConnection\Model\Model;

class User extends Model implements JwtSubjectInterface
{
    public const ROLE_USER = 'user';
    public const ROLE_ADMIN = 'admin';

    public const VIP_FREE = 'free';
    public const VIP_MONTHLY = 'monthly';
    public const VIP_YEARLY = 'yearly';

    protected ?string $table = 'users';

    protected array $fillable = [
        'username',
        'email',
        'password_hash',
        'avatar',
        'role',
        'vip_level',
        'vip_expired_at',
        'quota_total',
        'quota_used',
    ];

    /**
     * 敏感字段不输出到 JSON（API 响应与 toArray）。
     */
    protected array $hidden = [
        'password_hash',
    ];

    protected array $casts = [
        'id' => 'integer',
        'vip_expired_at' => 'datetime',
        'quota_total' => 'integer',
        'quota_used' => 'integer',
    ];

    public function articles(): \Hyperf\Database\Model\Relations\HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function agentLogs(): \Hyperf\Database\Model\Relations\HasMany
    {
        return $this->hasMany(AgentLog::class);
    }

    // ---------- JwtSubjectInterface ----------

    public function getJwtIdentifier(): string
    {
        return (string) $this->getKey();
    }

    public function getJwtCustomClaims(): array
    {
        return [
            'username' => $this->getAttribute('username'),
            'role' => $this->getAttribute('role'),
        ];
    }

    /**
     * 根据 JWT sub claim 反查用户；返回 null 表示令牌失效。
     */
    public static function retrieveById(string|int $key): ?self
    {
        /** @var self|null $user */
        $user = self::query()->find((int) $key);

        return $user;
    }
}
