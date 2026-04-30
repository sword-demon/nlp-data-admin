<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateVipPlansTable extends Migration
{
    public function up(): void
    {
        Schema::create('vip_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('套餐名称');
            $table->enum('level', ['free', 'monthly', 'yearly'])->unique()->comment('等级');
            $table->decimal('price', 10, 2)->default(0)->comment('价格（元）');
            $table->unsignedInteger('duration_days')->default(0)->comment('时长（天），0=永久');
            $table->integer('quota_monthly')->default(0)->comment('每月配额，-1 表示无限');
            $table->json('allowed_image_strategies')->nullable()->comment('允许的配图策略');
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 预置 3 条默认套餐
        \Hyperf\DbConnection\Db::table('vip_plans')->insert([
            [
                'name' => '免费版',
                'level' => 'free',
                'price' => 0.00,
                'duration_days' => 0,
                'quota_monthly' => 5,
                'allowed_image_strategies' => json_encode(['pexels', 'mermaid', 'iconify', 'emoji']),
                'description' => '免费用户每月 5 篇文章，基础配图策略',
                'is_active' => 1,
                'sort_order' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => '月费版',
                'level' => 'monthly',
                'price' => 29.00,
                'duration_days' => 30,
                'quota_monthly' => 50,
                'allowed_image_strategies' => json_encode(['pexels', 'mermaid', 'iconify', 'emoji', 'svg', 'nanobanana']),
                'description' => '月费会员每月 50 篇文章，全部配图策略（含 AI 生图）',
                'is_active' => 1,
                'sort_order' => 2,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => '年费版',
                'level' => 'yearly',
                'price' => 199.00,
                'duration_days' => 365,
                'quota_monthly' => -1,
                'allowed_image_strategies' => json_encode(['pexels', 'mermaid', 'iconify', 'emoji', 'svg', 'nanobanana']),
                'description' => '年费会员无限配额，全部配图策略（含 AI 生图）',
                'is_active' => 1,
                'sort_order' => 3,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vip_plans');
    }
}
