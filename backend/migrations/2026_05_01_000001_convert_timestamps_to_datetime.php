<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

/**
 * 将全部 TIMESTAMP 列迁移为 DATETIME。
 *
 * 背景
 * ----
 * MySQL TIMESTAMP 上限为 2038-01-19 03:14:07 UTC，对 VIP 永久会员、长期订单留存等
 * 业务时间场景构成真实风险。本次重构消除 2038 问题，并把项目整体时间模型收敛为
 * 全链路 Asia/Shanghai。
 *
 * 执行策略
 * --------
 *  1. 本迁移会话显式 SET time_zone='+08:00'，确保 TIMESTAMP → DATETIME 的字面量
 *     按上海时间落地（与应用层 PHP 时区一致）。
 *  2. 业务时间列（users.vip_expired_at / orders.paid_at）保持 NULLABLE DEFAULT NULL。
 *  3. 元数据列（created_at / updated_at）改为：
 *       - created_at: DATETIME NULL DEFAULT CURRENT_TIMESTAMP
 *       - updated_at: DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 *     兼容原 Hyperf timestamps() 的 NULLABLE 特性，并额外由 DB 兜底。
 *  4. 顺带将 admin 永久 VIP 从 2038-01-01 提升为 DATETIME 支持的最大值
 *     9999-12-31 23:59:59（真正意义上的"永久"）。
 *
 * 回滚策略（down）
 * ----------------
 * DATETIME 可表示 >2038 的时间，反向 ALTER 回 TIMESTAMP 时若存在超限行将
 * out-of-range 报错。因此 down() 先清洗 >TIMESTAMP 上限（Shanghai 视角为
 * 2038-01-19 11:14:07）的行：
 *   - users.vip_expired_at：置为 2038-01-01 00:00:00（与原 seed 一致）
 *   - orders.paid_at：置为 NULL（>2038 的支付时间无实际意义）
 *   - created_at / updated_at：夹到 2038-01-19 11:14:07（防御性，系统不应出现）
 * 然后执行反向 ALTER。
 *
 * 注意
 * ----
 *  - 原有 7 份 migration 保持不动，fresh 安装仍先建 TIMESTAMP 再由本迁移 ALTER。
 *  - 本迁移不修改 Model cast —— User/Order 已 cast 为 datetime（Carbon），列类型对 PHP 透明。
 *  - 应用层时区（bin/hyperf.php）、MySQL server 默认时区、databases.php 连接时区
 *    应同步更新为 +08:00，具体见同批次的配置文件改动。
 */
class ConvertTimestampsToDatetime extends Migration
{
    /**
     * 影响的 12 列（6 表），按 [table, column, kind] 分类。
     *
     * kind:
     *   - 'business'     : 业务时间列，NULLABLE DEFAULT NULL
     *   - 'meta_created' : 创建时间，NULLABLE DEFAULT CURRENT_TIMESTAMP
     *   - 'meta_updated' : 更新时间，NULLABLE DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
     */
    private const COLUMNS = [
        ['users', 'vip_expired_at', 'business'],
        ['users', 'created_at', 'meta_created'],
        ['users', 'updated_at', 'meta_updated'],
        ['articles', 'created_at', 'meta_created'],
        ['articles', 'updated_at', 'meta_updated'],
        ['agent_logs', 'created_at', 'meta_created'],
        ['agent_logs', 'updated_at', 'meta_updated'],
        ['vip_plans', 'created_at', 'meta_created'],
        ['vip_plans', 'updated_at', 'meta_updated'],
        ['orders', 'paid_at', 'business'],
        ['orders', 'created_at', 'meta_created'],
        ['orders', 'updated_at', 'meta_updated'],
    ];

    /**
     * TIMESTAMP 在 Shanghai 时区下可表达的上限（即 2038-01-19 03:14:07 UTC）。
     * 用于 down() 时识别并清洗超限行。
     */
    private const TIMESTAMP_MAX_SHANGHAI = '2038-01-19 11:14:07';

    public function up(): void
    {
        $connection = Schema::getConnection();
        $connection->statement("SET time_zone = '+08:00'");

        foreach (self::COLUMNS as [$table, $column, $kind]) {
            $connection->statement($this->buildAlterSql($table, $column, $kind, 'up'));
        }

        // admin 永久 VIP：原 2038-01-01（TIMESTAMP 时代的 placeholder）升级为 DATETIME 最大值
        Db::table('users')
            ->where('username', 'admin')
            ->whereBetween('vip_expired_at', ['2037-12-31 00:00:00', '2038-02-01 00:00:00'])
            ->update(['vip_expired_at' => '9999-12-31 23:59:59']);
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $connection->statement("SET time_zone = '+08:00'");

        // ① 清洗业务时间列：超 TIMESTAMP 上限的值无法回存
        Db::table('users')
            ->where('vip_expired_at', '>', self::TIMESTAMP_MAX_SHANGHAI)
            ->update(['vip_expired_at' => '2038-01-01 00:00:00']);

        Db::table('orders')
            ->where('paid_at', '>', self::TIMESTAMP_MAX_SHANGHAI)
            ->update(['paid_at' => null]);

        // ② 防御性清洗元数据列（系统正常运行不应出现 >2038 的 created_at/updated_at）
        foreach (self::COLUMNS as [$table, $column, $kind]) {
            if ($kind === 'meta_created' || $kind === 'meta_updated') {
                Db::table($table)
                    ->where($column, '>', self::TIMESTAMP_MAX_SHANGHAI)
                    ->update([$column => self::TIMESTAMP_MAX_SHANGHAI]);
            }
        }

        // ③ 反向 ALTER：DATETIME → TIMESTAMP
        foreach (self::COLUMNS as [$table, $column, $kind]) {
            $connection->statement($this->buildAlterSql($table, $column, $kind, 'down'));
        }
    }

    /**
     * 构造 ALTER TABLE ... MODIFY COLUMN SQL。
     *
     * @param string $direction 'up' | 'down'
     */
    private function buildAlterSql(string $table, string $column, string $kind, string $direction): string
    {
        if ($direction === 'up') {
            $definition = match ($kind) {
                'business' => 'DATETIME NULL DEFAULT NULL',
                'meta_created' => 'DATETIME NULL DEFAULT CURRENT_TIMESTAMP',
                'meta_updated' => 'DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            };
        } else {
            // 回滚到原 Hyperf timestamps() / timestamp()->nullable() 的原始定义
            $definition = 'TIMESTAMP NULL DEFAULT NULL';
        }

        return sprintf('ALTER TABLE `%s` MODIFY COLUMN `%s` %s', $table, $column, $definition);
    }
}
