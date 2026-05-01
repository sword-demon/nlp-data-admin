<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 预置默认管理员账号（幂等）。
 *
 * - 邮箱：admin@nlp.local
 * - 用户名：admin
 * - 密码：admin888（上线前请登录后自行修改！）
 * - 角色：admin
 * - VIP：yearly，有效期至 2038-01-01（受 MySQL TIMESTAMP 上限限制，非真正“永久”）
 *
 * 注意：仅当账号不存在时插入，重复执行不会覆盖已有数据与密码。
 */
class SeedDefaultAdminUser extends Migration
{
    private const ADMIN_EMAIL = 'admin@nlp.local';

    private const ADMIN_USERNAME = 'admin';

    private const ADMIN_PASSWORD = 'admin888';

    public function up(): void
    {
        $exists = Db::table('users')
            ->where('email', self::ADMIN_EMAIL)
            ->orWhere('username', self::ADMIN_USERNAME)
            ->exists();

        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        Db::table('users')->insert([
            'username' => self::ADMIN_USERNAME,
            'email' => self::ADMIN_EMAIL,
            'password_hash' => password_hash(self::ADMIN_PASSWORD, PASSWORD_BCRYPT),
            'avatar' => '',
            'role' => 'admin',
            'vip_level' => 'yearly',
            'vip_expired_at' => '2038-01-01 00:00:00',
            'quota_total' => 999999,
            'quota_used' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Db::table('users')
            ->where('email', self::ADMIN_EMAIL)
            ->where('username', self::ADMIN_USERNAME)
            ->delete();
    }
}
