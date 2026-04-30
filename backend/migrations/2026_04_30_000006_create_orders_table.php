<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('out_trade_no', 64)->unique()->comment('商户订单号');
            $table->string('zpay_order_id', 64)->nullable()->comment('z-pay 平台订单号');
            $table->enum('plan_type', ['monthly', 'yearly'])->comment('套餐类型');
            $table->decimal('amount', 10, 2)->comment('支付金额');
            $table->enum('pay_type', ['wxpay', 'alipay'])->comment('支付方式');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('notify_raw')->nullable()->comment('z-pay 回调原始数据');
            $table->string('subject', 255)->nullable()->comment('订单名称');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
}
