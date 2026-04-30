<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\AgentLog;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;

/**
 * ObservabilityService — Agent 执行日志的聚合统计查询。
 *
 * 所有日期参数格式：YYYY-MM-DD（视作 [start 00:00:00, end 23:59:59]）。
 * 缺省均为"最近 7 天"窗口。
 */
class ObservabilityService
{
    /**
     * 概览统计：总调用 / 成功率 / 平均耗时 / 最慢 Agent。
     *
     * @return array{
     *   total: int,
     *   success: int,
     *   failed: int,
     *   running: int,
     *   success_rate: float,
     *   avg_duration_ms: int,
     *   p95_duration_ms: int,
     *   slowest_agent: array{name: string, avg_duration_ms: int}|null,
     *   start_date: string,
     *   end_date: string
     * }
     */
    public function getOverview(?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->resolveRange($startDate, $endDate);

        $row = AgentLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_cnt")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_cnt")
            ->selectRaw("SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running_cnt")
            ->selectRaw('COALESCE(AVG(duration_ms), 0) as avg_ms')
            ->first();

        $total = (int) ($row->total ?? 0);
        $success = (int) ($row->success_cnt ?? 0);
        $failed = (int) ($row->failed_cnt ?? 0);
        $running = (int) ($row->running_cnt ?? 0);
        $avgMs = (int) round((float) ($row->avg_ms ?? 0));

        $slowest = AgentLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', AgentLog::STATUS_SUCCESS)
            ->selectRaw('agent_name, COALESCE(AVG(duration_ms), 0) as avg_ms')
            ->groupBy('agent_name')
            ->orderByDesc('avg_ms')
            ->first();

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'running' => $running,
            'success_rate' => $total > 0 ? round($success * 100 / $total, 2) : 0.0,
            'avg_duration_ms' => $avgMs,
            'p95_duration_ms' => $this->percentileDuration($start, $end, 0.95),
            'slowest_agent' => $slowest ? [
                'name' => (string) $slowest->agent_name,
                'avg_duration_ms' => (int) round((float) $slowest->avg_ms),
            ] : null,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }

    /**
     * 各 Agent 维度统计。
     *
     * @return array<int, array{name: string, total: int, success: int, failed: int, success_rate: float, avg_duration_ms: int}>
     */
    public function getAgentStats(?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->resolveRange($startDate, $endDate);

        $rows = AgentLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('agent_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_cnt")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_cnt")
            ->selectRaw('COALESCE(AVG(duration_ms), 0) as avg_ms')
            ->groupBy('agent_name')
            ->orderByDesc('total')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $total = (int) $r->total;
            $success = (int) $r->success_cnt;
            $out[] = [
                'name' => (string) $r->agent_name,
                'total' => $total,
                'success' => $success,
                'failed' => (int) $r->failed_cnt,
                'success_rate' => $total > 0 ? round($success * 100 / $total, 2) : 0.0,
                'avg_duration_ms' => (int) round((float) $r->avg_ms),
            ];
        }

        return $out;
    }

    /**
     * 按天的调用次数 / 成功率 / 平均耗时趋势。
     *
     * @return array<int, array{date: string, total: int, success: int, failed: int, success_rate: float, avg_duration_ms: int}>
     */
    public function getDailyTrend(?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->resolveRange($startDate, $endDate);

        $rows = AgentLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_cnt")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_cnt")
            ->selectRaw('COALESCE(AVG(duration_ms), 0) as avg_ms')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $total = (int) $r->total;
            $success = (int) $r->success_cnt;
            $out[] = [
                'date' => (string) $r->day,
                'total' => $total,
                'success' => $success,
                'failed' => (int) $r->failed_cnt,
                'success_rate' => $total > 0 ? round($success * 100 / $total, 2) : 0.0,
                'avg_duration_ms' => (int) round((float) $r->avg_ms),
            ];
        }

        return $out;
    }

    /**
     * 慢执行列表：duration_ms 超过阈值的日志。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSlowAgents(int $thresholdMs = 30000, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $thresholdMs = max(0, $thresholdMs);

        $rows = AgentLog::query()
            ->where('duration_ms', '>=', $thresholdMs)
            ->orderByDesc('duration_ms')
            ->limit($limit)
            ->get([
                'id',
                'user_id',
                'article_id',
                'agent_name',
                'duration_ms',
                'status',
                'error_message',
                'created_at',
            ]);

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'user_id' => $r->user_id !== null ? (int) $r->user_id : null,
            'article_id' => $r->article_id !== null ? (int) $r->article_id : null,
            'agent_name' => (string) $r->agent_name,
            'duration_ms' => (int) $r->duration_ms,
            'status' => (string) $r->status,
            'error_message' => $r->error_message,
            'created_at' => $r->created_at?->format('c'),
        ])->all();
    }

    /**
     * 最近日志列表，支持按 agent_name/status 筛选。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentLogs(
        ?string $agentName = null,
        ?string $status = null,
        int $limit = 50
    ): array {
        $limit = max(1, min(200, $limit));

        $q = AgentLog::query()->orderByDesc('id');
        if ($agentName !== null && $agentName !== '') {
            $q->where('agent_name', $agentName);
        }
        if ($status !== null && $status !== '') {
            $q->where('status', $status);
        }
        $rows = $q->limit($limit)->get([
            'id',
            'user_id',
            'article_id',
            'agent_name',
            'input_summary',
            'output_summary',
            'duration_ms',
            'status',
            'error_message',
            'created_at',
        ]);

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'user_id' => $r->user_id !== null ? (int) $r->user_id : null,
            'article_id' => $r->article_id !== null ? (int) $r->article_id : null,
            'agent_name' => (string) $r->agent_name,
            'input_summary' => (string) $r->input_summary,
            'output_summary' => (string) $r->output_summary,
            'duration_ms' => (int) $r->duration_ms,
            'status' => (string) $r->status,
            'error_message' => $r->error_message,
            'created_at' => $r->created_at?->format('c'),
        ])->all();
    }

    /**
     * 用户活跃度：某用户在窗口内的调用次数 / 成功率 / 使用过的 Agent 列表。
     *
     * @return array<string, mixed>
     */
    public function getUserActivity(int $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->resolveRange($startDate, $endDate);

        $row = AgentLog::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_cnt")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_cnt")
            ->selectRaw('COALESCE(AVG(duration_ms), 0) as avg_ms')
            ->first();

        $agents = AgentLog::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('agent_name, COUNT(*) as cnt')
            ->groupBy('agent_name')
            ->orderByDesc('cnt')
            ->get();

        $total = (int) ($row->total ?? 0);
        $success = (int) ($row->success_cnt ?? 0);

        return [
            'user_id' => $userId,
            'total' => $total,
            'success' => $success,
            'failed' => (int) ($row->failed_cnt ?? 0),
            'success_rate' => $total > 0 ? round($success * 100 / $total, 2) : 0.0,
            'avg_duration_ms' => (int) round((float) ($row->avg_ms ?? 0)),
            'agents' => $agents->map(fn ($a) => [
                'name' => (string) $a->agent_name,
                'count' => (int) $a->cnt,
            ])->all(),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }

    /**
     * 计算 p95 耗时（小样本时可能不精确，按 LIMIT+OFFSET 粗估）。
     */
    private function percentileDuration(Carbon $start, Carbon $end, float $percentile): int
    {
        $count = AgentLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', AgentLog::STATUS_SUCCESS)
            ->count();
        if ($count === 0) {
            return 0;
        }
        $offset = (int) max(0, floor($count * $percentile) - 1);
        $row = AgentLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', AgentLog::STATUS_SUCCESS)
            ->orderBy('duration_ms')
            ->offset($offset)
            ->limit(1)
            ->first(['duration_ms']);
        return $row ? (int) $row->duration_ms : 0;
    }

    /**
     * 解析日期区间，返回 [startCarbon, endCarbon]。
     * 默认最近 7 天（含今日）。非法输入回退默认。
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(?string $startDate, ?string $endDate): array
    {
        try {
            $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();
        } catch (\Throwable) {
            $end = Carbon::now()->endOfDay();
        }
        try {
            $start = $startDate ? Carbon::parse($startDate)->startOfDay() : (clone $end)->subDays(6)->startOfDay();
        } catch (\Throwable) {
            $start = (clone $end)->subDays(6)->startOfDay();
        }
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }
        return [$start, $end];
    }
}
