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
 * - VIP：yearly，首次种子时默认到期 2038-01-01；后续迁移
 *   2026_05_01_000001_convert_timestamps_to_datetime 会将该列升级为 DATETIME 并把
 *   admin 的到期时间提升为 9999-12-31 23:59:59（真正意义上的永久）。
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

        // created_at / updated_at 交给 DB 默认值 DEFAULT CURRENT_TIMESTAMP
        // （MySQL session time_zone 已被连接池设为 +08:00），避免
        // PHP date() 在 UTC 上下文生成少 8h 的时间字面量。
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
