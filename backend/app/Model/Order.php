<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * 订单模型。
 *
 * 状态机：pending → paid | failed → refunded
 */
class Order extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    public const PAY_TYPE_WECHAT = 'wxpay';
    public const PAY_TYPE_ALIPAY = 'alipay';

    public const PLAN_MONTHLY = 'monthly';
    public const PLAN_YEARLY = 'yearly';

    protected ?string $table = 'orders';

    protected array $fillable = [
        'user_id',
        'out_trade_no',
        'zpay_order_id',
        'plan_type',
        'amount',
        'pay_type',
        'status',
        'paid_at',
        'notify_raw',
        'subject',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'float',
        'paid_at' => 'datetime',
        'notify_raw' => 'json',
    ];

    public function user(): \Hyperf\Database\Model\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
