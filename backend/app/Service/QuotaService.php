<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use Carbon\Carbon;
use Hyperf\Redis\Redis;

/**
 * 配额管理：创作入口的文章生成次数控制。
 *
 * 设计：
 *   - Redis key: `quota:{userId}:{YYYYMM}` 存储当月已使用次数，TTL 设置到月底 +1 天
 *   - DB users.quota_used 作为持久化备份，月初重置
 *   - quota_total = -1 表示无限配额（年费）
 *
 * 并发安全：使用 Redis INCR 原子操作避免超发。
 */
class QuotaService
{
    private const REDIS_KEY_PREFIX = 'quota:';

    public function __construct(
        private readonly Redis $redis
    ) {}

    /**
     * 检查用户是否还有剩余配额。
     */
    public function checkQuota(int $userId): bool
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return false;
        }

        $total = (int) $user->quota_total;
        if ($total < 0) {
            return true; // 无限配额（年费）
        }

        $used = $this->getUsedCount($userId, $user);
        return $used < $total;
    }

    /**
     * 扣减 1 次配额。调用方需在 checkQuota 通过后调用。
     *
     * 原子操作：Redis INCR → 若超出则回滚并抛异常
     */
    public function consumeQuota(int $userId): int
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return 0;
        }

        $total = (int) $user->quota_total;
        if ($total < 0) {
            return -1; // 无限配额
        }

        $key = $this->buildRedisKey($userId);
        $newValue = (int) $this->redis->incr($key);

        // 首次 set 时补 TTL（到下月月初 + 1 天）
        if ($newValue === 1) {
            $this->redis->expireAt($key, $this->nextMonthStartTimestamp() + 86400);
        }

        if ($newValue > $total) {
            // 超发 → 回滚
            $this->redis->decr($key);
            return -2;
        }

        // 同步更新 DB（异步友好：失败不影响主流程）
        try {
            $user->quota_used = $newValue;
            $user->save();
        } catch (\Throwable) {
            // 静默忽略，Redis 已经是真相源
        }

        return $newValue;
    }

    /**
     * 获取配额使用情况。
     *
     * @return array{total: int, used: int, remaining: int, reset_at: string}
     */
    public function getQuotaUsage(int $userId): array
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return ['total' => 0, 'used' => 0, 'remaining' => 0, 'reset_at' => ''];
        }

        $total = (int) $user->quota_total;
        $used = $this->getUsedCount($userId, $user);
        $remaining = $total < 0 ? -1 : max(0, $total - $used);

        return [
            'total' => $total,
            'used' => $used,
            'remaining' => $remaining,
            'reset_at' => Carbon::createFromTimestamp($this->nextMonthStartTimestamp())->format('c'),
        ];
    }

    /**
     * 月初重置（由 cron 触发，v1 通过 TTL 自然过期即可）。
     */
    public function resetMonthlyQuota(int $userId): void
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }
        $user->quota_used = 0;
        $user->save();

        $this->redis->del($this->buildRedisKey($userId));
    }

    private function getUsedCount(int $userId, User $user): int
    {
        $key = $this->buildRedisKey($userId);
        $cached = $this->redis->get($key);
        if ($cached !== false && $cached !== null && $cached !== '') {
            return (int) $cached;
        }
        // Redis miss → 读 DB
        return (int) $user->quota_used;
    }

    private function buildRedisKey(int $userId): string
    {
        return self::REDIS_KEY_PREFIX . $userId . ':' . date('Ym');
    }

    private function nextMonthStartTimestamp(): int
    {
        return Carbon::now()->startOfMonth()->addMonth()->getTimestamp();
    }
}
