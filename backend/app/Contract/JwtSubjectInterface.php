<?php

declare(strict_types=1);

namespace App\Contract;

/**
 * JWT 主体契约：任何可签发 JWT 的模型需实现此接口。
 */
interface JwtSubjectInterface
{
    /**
     * 返回放入 JWT sub claim 的稳定唯一标识。
     */
    public function getJwtIdentifier(): string;

    /**
     * 返回附加到 JWT payload 的自定义 claims。
     *
     * @return array<string, mixed>
     */
    public function getJwtCustomClaims(): array;
}
