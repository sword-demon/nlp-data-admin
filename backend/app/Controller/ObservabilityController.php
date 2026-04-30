<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helpers\ApiResponse;
use App\Service\ObservabilityService;
use Hyperf\Di\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;

/**
 * ObservabilityController — 管理后台可观测性 API。
 *
 * 路由前缀：/api/admin/observability
 * 全部端点需经过 JwtAuthMiddleware + AdminMiddleware 双重保护。
 *
 * 端点：
 *   GET  /overview?start_date=&end_date=          概览统计
 *   GET  /agents?start_date=&end_date=            Agent 维度统计
 *   GET  /trend?start_date=&end_date=             日趋势
 *   GET  /slow?threshold=30000&limit=20           慢执行
 *   GET  /logs?agent_name=&status=&limit=50       最近日志
 *   GET  /user/{id}?start_date=&end_date=         用户活跃度
 */
class ObservabilityController extends AbstractController
{
    #[Inject]
    protected ObservabilityService $service;

    public function overview(): ResponseInterface
    {
        $start = $this->dateInput('start_date');
        $end = $this->dateInput('end_date');
        return ApiResponse::success($this->response, $this->service->getOverview($start, $end));
    }

    public function agents(): ResponseInterface
    {
        $start = $this->dateInput('start_date');
        $end = $this->dateInput('end_date');
        return ApiResponse::success($this->response, [
            'list' => $this->service->getAgentStats($start, $end),
        ]);
    }

    public function trend(): ResponseInterface
    {
        $start = $this->dateInput('start_date');
        $end = $this->dateInput('end_date');
        return ApiResponse::success($this->response, [
            'list' => $this->service->getDailyTrend($start, $end),
        ]);
    }

    public function slow(): ResponseInterface
    {
        $threshold = (int) $this->request->input('threshold', 30000);
        $limit = (int) $this->request->input('limit', 20);
        return ApiResponse::success($this->response, [
            'threshold_ms' => $threshold,
            'list' => $this->service->getSlowAgents($threshold, $limit),
        ]);
    }

    public function logs(): ResponseInterface
    {
        $agentName = trim((string) $this->request->input('agent_name', ''));
        $status = trim((string) $this->request->input('status', ''));
        $limit = (int) $this->request->input('limit', 50);

        return ApiResponse::success($this->response, [
            'list' => $this->service->getRecentLogs(
                $agentName !== '' ? $agentName : null,
                $status !== '' ? $status : null,
                $limit
            ),
        ]);
    }

    public function userActivity(int $id): ResponseInterface
    {
        $start = $this->dateInput('start_date');
        $end = $this->dateInput('end_date');
        return ApiResponse::success($this->response, $this->service->getUserActivity($id, $start, $end));
    }

    private function dateInput(string $key): ?string
    {
        $v = $this->request->input($key);
        if (! is_string($v) || trim($v) === '') {
            return null;
        }
        return trim($v);
    }
}
