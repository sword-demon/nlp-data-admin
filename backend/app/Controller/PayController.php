<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\Code;
use App\Exception\BusinessException;
use App\Helpers\ApiResponse;
use App\Model\Order;
use App\Model\VipPlan;
use App\Service\VipService;
use App\Service\ZPayService;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * 支付控制器：
 *   POST /api/pay/create    (JWT)   创建支付订单
 *   POST /api/pay/notify    (公开)  z-pay 异步回调
 *   GET  /api/pay/return    (公开)  z-pay 同步跳转
 *   GET  /api/pay/status    (JWT)   查询订单状态
 *   GET  /api/pay/orders    (JWT)   用户订单列表
 */
class PayController extends AbstractController
{
    #[Inject]
    protected ZPayService $zpay;

    #[Inject]
    protected VipService $vip;

    /** POST /api/pay/create */
    public function create(): ResponseInterface
    {
        $userId = $this->currentUserId();
        $planType = trim((string) $this->request->input('plan_type', ''));
        $payType = trim((string) $this->request->input('pay_type', ''));

        if (! in_array($planType, [Order::PLAN_MONTHLY, Order::PLAN_YEARLY], true)) {
            throw new BusinessException(Code::VIP_INVALID_PLAN, 'plan_type must be monthly | yearly', 422);
        }
        if (! in_array($payType, [Order::PAY_TYPE_WECHAT, Order::PAY_TYPE_ALIPAY], true)) {
            throw new BusinessException(Code::PAY_INVALID_PAY_TYPE, 'pay_type must be wxpay | alipay', 422);
        }

        $plan = $this->vip->getPlanByLevel($planType);
        $amount = (float) ($plan['price'] ?? 0);
        if ($amount <= 0) {
            throw new BusinessException(Code::PAY_CREATE_FAILED, 'invalid plan price', 500);
        }

        $outTradeNo = ZPayService::generateOutTradeNo();
        $subject = (string) ($plan['name'] ?? "VIP {$planType}");

        // 先落库（pending）
        $order = new Order();
        $order->user_id = $userId;
        $order->out_trade_no = $outTradeNo;
        $order->plan_type = $planType;
        $order->amount = $amount;
        $order->pay_type = $payType;
        $order->status = Order::STATUS_PENDING;
        $order->subject = $subject;
        $order->save();

        // 调用 z-pay 创建支付
        try {
            $result = $this->zpay->createOrder($outTradeNo, $payType, $amount, $subject);
        } catch (Throwable $e) {
            $order->status = Order::STATUS_FAILED;
            $order->save();
            throw new BusinessException(Code::PAY_CREATE_FAILED, 'create pay order failed: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success($this->response, [
            'order_id' => (int) $order->getKey(),
            'out_trade_no' => $outTradeNo,
            'pay_url' => $result['pay_url'] ?? '',
            'qrcode' => $result['qrcode'] ?? '',
            'mode' => $result['mode'] ?? 'submit',
            'plan_type' => $planType,
            'pay_type' => $payType,
            'amount' => $amount,
            'subject' => $subject,
            'status' => Order::STATUS_PENDING,
        ], 'pay_order_created');
    }

    /**
     * POST /api/pay/notify — z-pay 异步回调（无 JWT）。
     *
     * 必须返回纯文本 "success"（非 JSON），z-pay 才认为回调成功；
     * 否则会持续重试最多 8 次。
     */
    public function notify(): ResponseInterface
    {
        $params = $this->request->all();
        $logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);

        try {
            if (! $this->zpay->verifyCallback($params)) {
                $logger->warning('[PayNotify] sign verify failed: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
                return $this->response->raw('fail');
            }

            $outTradeNo = (string) ($params['out_trade_no'] ?? '');
            $tradeStatus = (string) ($params['trade_status'] ?? '');

            /** @var Order|null $order */
            $order = Order::query()->where('out_trade_no', $outTradeNo)->first();
            if (! $order) {
                $logger->warning("[PayNotify] order not found: {$outTradeNo}");
                return $this->response->raw('fail');
            }

            // 幂等：已支付订单不重复处理
            if ($order->status === Order::STATUS_PAID) {
                return $this->response->raw('success');
            }

            if ($tradeStatus === 'TRADE_SUCCESS') {
                $order->status = Order::STATUS_PAID;
                $order->paid_at = date('Y-m-d H:i:s');
                $order->zpay_order_id = (string) ($params['trade_no'] ?? '');
                $order->notify_raw = $params;
                $order->save();

                // 激活 VIP
                $this->vip->activateVip((int) $order->user_id, (string) $order->plan_type);
                $logger->info("[PayNotify] order {$outTradeNo} paid & VIP activated for user {$order->user_id}");
            } else {
                $order->notify_raw = $params;
                $order->save();
            }

            return $this->response->raw('success');
        } catch (Throwable $e) {
            $logger->error('[PayNotify] exception: ' . $e->getMessage());
            return $this->response->raw('fail');
        }
    }

    /** GET /api/pay/return — 同步跳转（前端可展示支付结果页） */
    public function returnPage(): ResponseInterface
    {
        $outTradeNo = (string) $this->request->input('out_trade_no', '');
        $tradeStatus = (string) $this->request->input('trade_status', '');

        return ApiResponse::success($this->response, [
            'out_trade_no' => $outTradeNo,
            'trade_status' => $tradeStatus,
        ], 'pay_return');
    }

    /** GET /api/pay/status?out_trade_no=xxx */
    public function status(): ResponseInterface
    {
        $userId = $this->currentUserId();
        $outTradeNo = (string) $this->request->input('out_trade_no', '');
        if ($outTradeNo === '') {
            throw new BusinessException(Code::VALIDATION_ERROR, 'out_trade_no is required', 422);
        }

        /** @var Order|null $order */
        $order = Order::query()->where('out_trade_no', $outTradeNo)->first();
        if (! $order) {
            throw new BusinessException(Code::PAY_ORDER_NOT_FOUND, 'order not found', 404);
        }
        if ((int) $order->user_id !== $userId) {
            throw new BusinessException(Code::FORBIDDEN, 'order does not belong to you', 403);
        }

        // 如果是 pending，主动向 z-pay 查一次
        if ($order->status === Order::STATUS_PENDING) {
            try {
                $remote = $this->zpay->queryOrder($outTradeNo);
                if (($remote['status'] ?? '') === 'paid') {
                    $order->status = Order::STATUS_PAID;
                    $order->paid_at = date('Y-m-d H:i:s');
                    $order->notify_raw = $remote['raw'] ?? null;
                    $order->save();
                    $this->vip->activateVip($userId, (string) $order->plan_type);
                }
            } catch (Throwable) {
                // 静默，保持 pending 状态
            }
        }

        return ApiResponse::success($this->response, [
            'order_id' => (int) $order->getKey(),
            'out_trade_no' => $outTradeNo,
            'status' => $order->status,
            'plan_type' => $order->plan_type,
            'pay_type' => $order->pay_type,
            'amount' => (float) $order->amount,
            'paid_at' => $order->paid_at?->format('c'),
        ]);
    }

    /** GET /api/pay/orders */
    public function orders(): ResponseInterface
    {
        $userId = $this->currentUserId();
        $page = max(1, (int) $this->request->input('page', 1));
        $limit = min(100, max(1, (int) $this->request->input('limit', 20)));

        $query = Order::query()->where('user_id', $userId)->orderByDesc('id');
        $total = (clone $query)->count();
        $orders = $query->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function (Order $o) {
                return [
                    'order_id' => (int) $o->getKey(),
                    'out_trade_no' => $o->out_trade_no,
                    'plan_type' => $o->plan_type,
                    'pay_type' => $o->pay_type,
                    'amount' => (float) $o->amount,
                    'status' => $o->status,
                    'subject' => $o->subject,
                    'paid_at' => $o->paid_at?->format('c'),
                    'created_at' => $o->created_at?->format('c'),
                ];
            })
            ->toArray();

        return ApiResponse::paginate($this->response, $orders, (int) $total, $page, $limit);
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
