<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('password_hash', 255);
            $table->string('avatar', 500)->nullable()->default('');
            $table->enum('role', ['user', 'admin'])->default('user');
            $table->enum('vip_level', ['free', 'monthly', 'yearly'])->default('free');
            $table->timestamp('vip_expired_at')->nullable();
            $table->unsignedInteger('quota_total')->default(10);
            $table->unsignedInteger('quota_used')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}
