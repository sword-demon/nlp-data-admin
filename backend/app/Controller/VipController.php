<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\Code;
use App\Exception\BusinessException;
use App\Helpers\ApiResponse;
use App\Service\QuotaService;
use App\Service\VipService;
use Hyperf\Di\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;

/**
 * VIP 会员信息控制器：
 *   GET /api/vip/plans        (公开)  所有可用套餐
 *   GET /api/vip/info         (JWT)   当前用户 VIP 状态
 *   GET /api/vip/strategies   (JWT)   当前用户允许的配图策略
 *   GET /api/vip/quota        (JWT)   当前用户配额使用情况
 */
class VipController extends AbstractController
{
    #[Inject]
    protected VipService $vip;

    #[Inject]
    protected QuotaService $quota;

    /** GET /api/vip/plans — 公开 */
    public function plans(): ResponseInterface
    {
        return ApiResponse::success($this->response, [
            'plans' => $this->vip->getPlans(),
        ]);
    }

    /** GET /api/vip/info */
    public function info(): ResponseInterface
    {
        $userId = $this->currentUserId();
        $info = $this->vip->getUserVipInfo($userId);
        $usage = $this->quota->getQuotaUsage($userId);

        return ApiResponse::success($this->response, array_merge($info, [
            'quota_usage' => $usage,
        ]));
    }

    /** GET /api/vip/strategies */
    public function strategies(): ResponseInterface
    {
        $userId = $this->currentUserId();
        return ApiResponse::success($this->response, [
            'strategies' => $this->vip->getUserAllowedStrategies($userId),
        ]);
    }

    /** GET /api/vip/quota */
    public function quota(): ResponseInterface
    {
        $userId = $this->currentUserId();
        return ApiResponse::success($this->response, $this->quota->getQuotaUsage($userId));
    }

    private function currentUserId(): int
    {
        $userId = (int) $this->request->getAttribute('user_id');
        if ($userId <= 0) {
            throw new BusinessException(Code::TOKEN_INVALID, 'Auth context missing', 401);
        }
        return $userId;
    }
}
