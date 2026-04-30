<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\Code;
use App\Exception\BusinessException;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Guzzle\ClientFactory;
use Throwable;

/**
 * z-pay 聚合支付对接。
 *
 * 文档：https://z-pay.cn/doc.html
 *
 * 支付流程：
 *   1. 服务端调用 createOrder() → 返回支付 URL / 二维码 URL
 *   2. 用户扫码支付
 *   3. z-pay 异步 POST 回调到 notify_url；调用 verifyCallback() 校验签名
 *   4. 前端轮询 queryOrder() 确认支付状态
 *
 * 签名算法（MD5）：
 *   - 所有非空参数按 key 字母升序
 *   - 过滤掉 sign / sign_type
 *   - 拼接为 key1=value1&key2=value2...
 *   - 末尾拼接商户密钥（直接拼接，不带 & 和 key=）
 *   - MD5 小写输出
 */
class ZPayService
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        ConfigInterface $config,
        private readonly ClientFactory $clientFactory,
        private readonly StdoutLoggerInterface $logger
    ) {
        $this->config = (array) $config->get('payment.zpay', []);
    }

    /**
     * 生成 z-pay MD5 签名。
     *
     * @param array<string, mixed> $params
     */
    public function generateSign(array $params): string
    {
        $key = (string) ($this->config['key'] ?? '');

        // 过滤空值 + 排除 sign / sign_type
        $filtered = [];
        foreach ($params as $k => $v) {
            if ($k === 'sign' || $k === 'sign_type') {
                continue;
            }
            if ($v === '' || $v === null) {
                continue;
            }
            $filtered[$k] = (string) $v;
        }

        ksort($filtered);

        $pairs = [];
        foreach ($filtered as $k => $v) {
            $pairs[] = "{$k}={$v}";
        }

        return md5(implode('&', $pairs) . $key);
    }

    /**
     * 验证 z-pay 回调签名。
     *
     * @param array<string, mixed> $params 回调原始参数（含 sign / sign_type）
     */
    public function verifyCallback(array $params): bool
    {
        $sign = (string) ($params['sign'] ?? '');
        if ($sign === '') {
            return false;
        }

        $expected = $this->generateSign($params);

        return hash_equals($expected, $sign);
    }

    /**
     * 创建 z-pay 订单。
     *
     * 优先尝试 mapi.php 接口支付（返回支付二维码 URL，前端自行渲染）；
     * 若未配置，则回退为返回 submit.php 的跳转 URL。
     *
     * @return array{out_trade_no: string, pay_url: string, qrcode: string, mode: string}
     */
    public function createOrder(
        string $outTradeNo,
        string $payType,
        float $amount,
        string $subject,
        string $returnUrl = '',
        string $notifyUrl = ''
    ): array {
        $pid = (string) ($this->config['pid'] ?? '');
        if ($pid === '' || ($this->config['key'] ?? '') === '') {
            throw new BusinessException(Code::PAY_CREATE_FAILED, 'z-pay not configured (ZPAY_PID/ZPAY_KEY)', 500);
        }

        $params = [
            'pid' => $pid,
            'type' => $payType,
            'out_trade_no' => $outTradeNo,
            'notify_url' => $notifyUrl !== '' ? $notifyUrl : (string) ($this->config['notify_url'] ?? ''),
            'return_url' => $returnUrl !== '' ? $returnUrl : (string) ($this->config['return_url'] ?? ''),
            'name' => $subject,
            'money' => number_format($amount, 2, '.', ''),
            'sign_type' => 'MD5',
        ];

        $params['sign'] = $this->generateSign($params);

        // 尝试 mapi.php 接口支付获取二维码
        try {
            $apiResult = $this->requestMapi($params);
            if (isset($apiResult['code']) && (int) $apiResult['code'] === 1) {
                return [
                    'out_trade_no' => $outTradeNo,
                    'pay_url' => (string) ($apiResult['payurl'] ?? ''),
                    'qrcode' => (string) ($apiResult['qrcode'] ?? ($apiResult['payurl'] ?? '')),
                    'mode' => 'api',
                ];
            }
            $this->logger->warning('[ZPayService] mapi returned non-success: ' . json_encode($apiResult, JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            $this->logger->warning('[ZPayService] mapi failed, fallback to submit: ' . $e->getMessage());
        }

        // 回退：返回 submit.php 的 GET URL（前端可直接跳转 / 使用 iframe）
        $submitUrl = (string) ($this->config['submit_url'] ?? 'https://zpayz.cn/submit.php');
        $payUrl = $submitUrl . '?' . http_build_query($params);

        return [
            'out_trade_no' => $outTradeNo,
            'pay_url' => $payUrl,
            'qrcode' => $payUrl,
            'mode' => 'submit',
        ];
    }

    /**
     * 查询订单支付状态。
     *
     * @return array{status: string, raw: array<string, mixed>}
     */
    public function queryOrder(string $outTradeNo): array
    {
        $pid = (string) ($this->config['pid'] ?? '');
        $apiUrl = (string) ($this->config['api_url'] ?? 'https://zpayz.cn/api.php');

        $client = $this->buildClient();
        try {
            $response = $client->get($apiUrl, [
                'query' => [
                    'act' => 'order',
                    'pid' => $pid,
                    'key' => (string) ($this->config['key'] ?? ''),
                    'out_trade_no' => $outTradeNo,
                ],
            ]);
            $body = (string) $response->getBody();
            $decoded = json_decode($body, true);
            $raw = is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            $this->logger->warning('[ZPayService] queryOrder failed: ' . $e->getMessage());
            return ['status' => 'unknown', 'raw' => []];
        }

        // z-pay 返回 status=1 表示已支付
        $status = isset($raw['status']) && (int) $raw['status'] === 1 ? 'paid' : 'pending';
        return ['status' => $status, 'raw' => $raw];
    }

    /**
     * 生成商户订单号。格式：NLP_YmdHis_6随机字母数字。
     */
    public static function generateOutTradeNo(): string
    {
        $rand = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(6))), 0, 6);
        return 'NLP_' . date('YmdHis') . '_' . strtoupper($rand);
    }

    /**
     * 调用 z-pay mapi.php 接口支付，获取二维码 URL。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function requestMapi(array $params): array
    {
        $url = (string) ($this->config['mapi_url'] ?? 'https://zpayz.cn/mapi.php');
        $client = $this->buildClient();
        $response = $client->post($url, [
            'form_params' => $params,
            'headers' => ['Accept' => 'application/json'],
        ]);
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function buildClient(): \GuzzleHttp\Client
    {
        $timeout = (int) ($this->config['timeout'] ?? 15);
        return $this->clientFactory->create([
            'timeout' => $timeout,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
    }
}
