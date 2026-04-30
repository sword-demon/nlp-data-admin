<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\Code;
use App\Exception\BusinessException;
use App\Model\User;
use App\Model\VipPlan;
use Carbon\Carbon;
use Hyperf\Contract\ConfigInterface;

/**
 * VIP 会员管理。
 *
 * 激活/续费逻辑：
 *   - 当前 VIP 未过期：新到期时间 = 当前到期时间 + 套餐天数（叠加续费）
 *   - 已过期或首次开通：新到期时间 = 当前时间 + 套餐天数
 *   - quota_total 重置为套餐配额（不累加）；quota_used 清零
 */
class VipService
{
    public function __construct(
        private readonly ConfigInterface $config
    ) {}

    /**
     * 获取所有可用套餐（按 sort_order 升序）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPlans(): array
    {
        return VipPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    /**
     * 获取某个等级的套餐配置（DB 优先，回退 config）。
     *
     * @return array<string, mixed>
     */
    public function getPlanByLevel(string $level): array
    {
        /** @var VipPlan|null $plan */
        $plan = VipPlan::query()->where('level', $level)->first();
        if ($plan !== null) {
            return $plan->toArray();
        }

        $configPlan = (array) $this->config->get("vip.plans.{$level}", []);
        if (empty($configPlan)) {
            throw new BusinessException(Code::VIP_PLAN_NOT_FOUND, "plan [{$level}] not found", 404);
        }

        return array_merge(['level' => $level], $configPlan);
    }

    /**
     * 获取用户的 VIP 信息 + 配额使用。
     *
     * @return array<string, mixed>
     */
    public function getUserVipInfo(int $userId): array
    {
        /** @var User|null $user */
        $user = User::query()->find($userId);
        if (! $user) {
            throw new BusinessException(Code::USER_NOT_FOUND, 'user not found', 404);
        }

        $level = (string) $user->vip_level;
        $isActive = $this->isVipActive($user);

        // VIP 过期则视同 free
        $effectiveLevel = $isActive ? $level : User::VIP_FREE;

        return [
            'user_id' => (int) $user->getKey(),
            'vip_level' => $level,
            'effective_level' => $effectiveLevel,
            'vip_expired_at' => $user->vip_expired_at?->format('c'),
            'is_active' => $isActive,
            'quota_total' => (int) $user->quota_total,
            'quota_used' => (int) $user->quota_used,
            'quota_remaining' => $this->calcRemaining((int) $user->quota_total, (int) $user->quota_used),
            'allowed_strategies' => $this->getAllowedStrategies($effectiveLevel),
        ];
    }

    /**
     * 激活 / 续费 VIP。
     *
     * @param string $planType monthly | yearly
     */
    public function activateVip(int $userId, string $planType): void
    {
        if (! in_array($planType, [VipPlan::LEVEL_MONTHLY, VipPlan::LEVEL_YEARLY], true)) {
            throw new BusinessException(Code::VIP_INVALID_PLAN, "invalid plan_type [{$planType}]", 422);
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);
        if (! $user) {
            throw new BusinessException(Code::USER_NOT_FOUND, 'user not found', 404);
        }

        $plan = $this->getPlanByLevel($planType);
        $duration = (int) ($plan['duration_days'] ?? 0);
        $quotaMonthly = (int) ($plan['quota_monthly'] ?? 0);

        $now = Carbon::now();
        $currentExpiredAt = $user->vip_expired_at;
        $baseTime = ($currentExpiredAt && $currentExpiredAt->gt($now)) ? Carbon::parse($currentExpiredAt) : $now;

        $user->vip_level = $planType;
        $user->vip_expired_at = $baseTime->copy()->addDays($duration);
        $user->quota_total = $quotaMonthly;
        $user->quota_used = 0;
        $user->save();
    }

    /**
     * 检查用户 VIP 是否有效。
     */
    public function isVipActive(User $user): bool
    {
        if ((string) $user->vip_level === User::VIP_FREE) {
            return true; // free 总是"有效"
        }
        if (! $user->vip_expired_at) {
            return false;
        }
        return Carbon::parse($user->vip_expired_at)->gt(Carbon::now());
    }

    /**
     * 获取某等级允许的配图策略。
     *
     * @return array<int, string>
     */
    public function getAllowedStrategies(string $level): array
    {
        // 先从 DB 读取
        /** @var VipPlan|null $plan */
        $plan = VipPlan::query()->where('level', $level)->first();
        if ($plan !== null && is_array($plan->allowed_image_strategies)) {
            return array_values(array_map('strval', $plan->allowed_image_strategies));
        }

        // 回退配置
        $fallback = (array) $this->config->get("vip.plans.{$level}.allowed_strategies", []);
        return array_values(array_map('strval', $fallback));
    }

    /**
     * 获取某用户当前允许的配图策略（考虑过期降级）。
     *
     * @return array<int, string>
     */
    public function getUserAllowedStrategies(int $userId): array
    {
        $info = $this->getUserVipInfo($userId);
        return $this->getAllowedStrategies((string) $info['effective_level']);
    }

    /**
     * 定时检查过期用户并降级到 free（由 cron / 定时任务触发，v1 可不实现）。
     */
    public function checkVipExpired(): int
    {
        $affected = User::query()
            ->where('vip_level', '!=', User::VIP_FREE)
            ->whereNotNull('vip_expired_at')
            ->where('vip_expired_at', '<', Carbon::now())
            ->update([
                'vip_level' => User::VIP_FREE,
                'quota_total' => (int) $this->config->get('vip.plans.free.quota_monthly', 5),
                'quota_used' => 0,
            ]);

        return (int) $affected;
    }

    private function calcRemaining(int $total, int $used): int
    {
        if ($total < 0) {
            return -1; // 无限
        }
        return max(0, $total - $used);
    }
}
