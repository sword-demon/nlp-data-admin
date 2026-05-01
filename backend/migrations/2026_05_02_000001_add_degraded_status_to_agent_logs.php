<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * ADR-0003：agent_logs.status 扩为四态枚举，支持 Agent 降级结局的可观测性聚合。
 *
 * - 原枚举：enum('running','success','failed')
 * - 新枚举：enum('running','success','degraded','failed')
 *
 * 零数据回填（历史数据全是 running/success/failed，新值 degraded 从本次迁移开始写入）。
 * 使用原生 SQL MODIFY COLUMN，Hyperf Schema 对 enum change 支持不完善。
 */
class AddDegradedStatusToAgentLogs extends Migration
{
    public function up(): void
    {
        Db::statement(
            "ALTER TABLE `agent_logs` MODIFY COLUMN `status` "
                . "ENUM('running','success','degraded','failed') NOT NULL DEFAULT 'running'"
        );
    }

    public function down(): void
    {
        // 回滚前确保没有 degraded 行（或将其改为 success 保留数据）
        Db::statement("UPDATE `agent_logs` SET `status` = 'success' WHERE `status` = 'degraded'");
        Db::statement(
            "ALTER TABLE `agent_logs` MODIFY COLUMN `status` "
                . "ENUM('running','success','failed') NOT NULL DEFAULT 'running'"
        );
    }
}
