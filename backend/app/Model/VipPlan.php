<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * VIP 套餐模型。
 *
 * 等级常量与 User::VIP_* 保持一致。
 * allowed_image_strategies 以 JSON 数组存储允许的配图策略名。
 */
class VipPlan extends Model
{
    public const LEVEL_FREE = 'free';
    public const LEVEL_MONTHLY = 'monthly';
    public const LEVEL_YEARLY = 'yearly';

    public const QUOTA_UNLIMITED = -1;

    protected ?string $table = 'vip_plans';

    protected array $fillable = [
        'name',
        'level',
        'price',
        'duration_days',
        'quota_monthly',
        'allowed_image_strategies',
        'description',
        'is_active',
        'sort_order',
    ];

    protected array $casts = [
        'id' => 'integer',
        'price' => 'float',
        'duration_days' => 'integer',
        'quota_monthly' => 'integer',
        'allowed_image_strategies' => 'json',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
