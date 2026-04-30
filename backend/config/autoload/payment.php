<?php

declare(strict_types=1);

use function Hyperf\Support\env;

/**
 * z-pay 聚合支付配置。
 *
 * - submit_url: 页面跳转支付入口（表单 POST）
 * - api_url: API 接口支付 / 订单查询入口
 * - notify_url: z-pay 异步回调（POST 到此 URL，必须公网可达）
 * - return_url: z-pay 同步跳转 URL（前端展示支付结果页）
 */
return [
    'zpay' => [
        'pid' => env('ZPAY_PID', ''),
        'key' => env('ZPAY_KEY', ''),
        'submit_url' => env('ZPAY_SUBMIT_URL', 'https://zpayz.cn/submit.php'),
        'api_url' => env('ZPAY_API_URL', 'https://zpayz.cn/api.php'),
        'mapi_url' => env('ZPAY_MAPI_URL', 'https://zpayz.cn/mapi.php'),
        'notify_url' => env('ZPAY_NOTIFY_URL', ''),
        'return_url' => env('ZPAY_RETURN_URL', ''),
        'sign_type' => 'MD5',
        // HTTP 请求超时（秒）
        'timeout' => (int) env('ZPAY_TIMEOUT', 15),
    ],
];
