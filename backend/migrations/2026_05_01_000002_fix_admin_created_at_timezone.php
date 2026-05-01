<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 回填 admin 账号 created_at 时区偏差（一次性数据修正）。
 *
 * 背景：
 *   2026_04_30_000007_seed_default_admin_user 最初使用 PHP date('Y-m-d H:i:s') 生成
 *   时间戳写入 users 表。执行时 bin/hyperf.php 还设置 date_default_timezone = 'UTC'，
 *   而 MySQL session_tz = '+08:00'（来自 docker/mysql/conf.d/custom.cnf 的 global
 *   default-time-zone）。因此 TIMESTAMP 列存储的"UTC 内部值"对应 seed 真实时刻；
 *   但由 2026_05_01_000001_convert_timestamps_to_datetime 迁移为 DATETIME 后，
 *   DATETIME 字面量继承的是"PHP UTC 数字字符串"，比真实 Shanghai 时刻少 8 小时。
 *
 * 本迁移仅修正 admin 这一行 users.created_at：+8 小时还原为真实上海时间。
 * 其他业务历史行（articles / agent_logs / vip_plans / orders / 非 admin users）
 * 在 grill-me 阶段已声明"数据不重要"，不做全量回填，避免误伤人工修改过的数据。
 *
 * 守护条件：created_at < '2026-05-01 10:00:00' 精确命中"迁移前写入的旧字面量"，
 *   避免重复执行 / 在已修正的库上二次 +8h。
 */
class FixAdminCreatedAtTimezone extends Migration
{
    private const ADMIN_USERNAME = 'admin';

    /** 旧字面量的时间上界（所有偏差行的 created_at 都 < 迁移运行时刻）。 */
    private const LEGACY_UPPER_BOUND = '2026-05-01 10:00:00';

    public function up(): void
    {
        Db::connection()->statement("SET time_zone = '+08:00'");

        // 显式 SET updated_at = updated_at 以阻止 ON UPDATE CURRENT_TIMESTAMP
        // 把 updated_at 刷成 now()——保留迁移 000001 触达的最后修改时间语义。
        Db::connection()->statement(
            'UPDATE `users` '
                . 'SET `created_at` = DATE_ADD(`created_at`, INTERVAL 8 HOUR), '
                . '    `updated_at` = `updated_at` '
                . 'WHERE `username` = ? AND `created_at` < ?',
            [self::ADMIN_USERNAME, self::LEGACY_UPPER_BOUND]
        );
    }

    public function down(): void
    {
        Db::connection()->statement("SET time_zone = '+08:00'");

        // 反向：把已修正的 admin.created_at -8 小时回到原始偏差状态。
        // 仅在当前值看起来"像被修正过的 CST 字面量"时才回滚。
        Db::connection()->statement(
            'UPDATE `users` '
                . 'SET `created_at` = DATE_SUB(`created_at`, INTERVAL 8 HOUR), '
                . '    `updated_at` = `updated_at` '
                . 'WHERE `username` = ? '
                . "AND `created_at` BETWEEN '2026-05-01 10:00:00' AND '2026-05-01 23:59:59'",
            [self::ADMIN_USERNAME]
        );
    }
}
