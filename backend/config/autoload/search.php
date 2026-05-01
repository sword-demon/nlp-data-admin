<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'default' => env('SEARCH_PROVIDER', 'exa'),
    'providers' => [
        'exa' => [
            'driver' => App\Service\SearchProvider\ExaProvider::class,
            'enabled' => (bool) env('EXA_ENABLED', false),
            'api_key' => env('EXA_API_KEY', ''),
            'base_url' => env('EXA_BASE_URL', 'https://api.exa.ai'),
            'num_results' => (int) env('EXA_NUM_RESULTS', 3),
            // 仅检索近 2 年的内容，过滤陈旧资料
            'start_published_date' => date('c', strtotime('-2 years')),
            'timeout' => 15,
        ],
    ],
    // Slice 03 的四道防线参数（缓存 / 单飞锁 / 令牌桶）也读这里
    'research' => [
        'cache_ttl' => (int) env('EXA_CACHE_TTL', 86400),
        'rate_limit_per_second' => (int) env('EXA_RATE_LIMIT_PER_SECOND', 3),
    ],
];
