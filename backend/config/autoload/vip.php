<?php

declare(strict_types=1);

/**
 * VIP 等级与配额配置。
 *
 * - quota_monthly: -1 表示无限
 * - allowed_strategies: 允许使用的配图策略（与 config/autoload/image.php 中的策略名对应）
 * - duration_days: 0 表示永久（仅 free）
 */
return [
    'plans' => [
        'free' => [
            'name' => '免费版',
            'price' => 0.00,
            'duration_days' => 0,
            'quota_monthly' => 5,
            'allowed_strategies' => ['pexels', 'mermaid', 'iconify', 'emoji'],
        ],
        'monthly' => [
            'name' => '月费版',
            'price' => 29.00,
            'duration_days' => 30,
            'quota_monthly' => 50,
            'allowed_strategies' => ['pexels', 'mermaid', 'iconify', 'emoji', 'svg', 'nanobanana'],
        ],
        'yearly' => [
            'name' => '年费版',
            'price' => 199.00,
            'duration_days' => 365,
            'quota_monthly' => -1,
            'allowed_strategies' => ['pexels', 'mermaid', 'iconify', 'emoji', 'svg', 'nanobanana'],
        ],
    ],
    'default_plan' => 'free',
];
