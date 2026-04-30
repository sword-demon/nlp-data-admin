<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    // 单张配图获取超时（秒）。超过则降级为占位图。
    'timeout' => (int) env('IMAGE_FETCH_TIMEOUT', 25),

    // 对同类型 Agent 建议失效时的降级顺序（配置化 fallback chain）。
    'fallback_chain' => [
        'pexels' => ['iconify', 'emoji'],
        'mermaid' => ['svg', 'iconify'],
        'iconify' => ['emoji', 'svg'],
        'emoji' => ['iconify'],
        'svg' => ['iconify', 'emoji'],
        'nanobanana' => ['pexels', 'iconify'],
    ],

    'strategies' => [
        'pexels' => [
            'enabled' => env('PEXELS_ENABLED', true),
            'api_key' => env('PEXELS_API_KEY', ''),
            'base_url' => env('PEXELS_BASE_URL', 'https://api.pexels.com/v1'),
            'locale' => env('PEXELS_LOCALE', 'zh-CN'),
            'orientation' => env('PEXELS_ORIENTATION', 'landscape'),
            'per_page' => 3,
        ],
        'mermaid' => [
            'enabled' => env('MERMAID_ENABLED', true),
            // mermaid.ink 公共服务，将 mermaid 代码编码为 URL 参数渲染 PNG
            'render_endpoint' => env('MERMAID_RENDER_URL', 'https://mermaid.ink/img'),
            'theme' => env('MERMAID_THEME', 'default'),
        ],
        'iconify' => [
            'enabled' => env('ICONIFY_ENABLED', true),
            'search_endpoint' => env('ICONIFY_SEARCH_URL', 'https://api.iconify.design/search'),
            'fetch_endpoint' => env('ICONIFY_FETCH_URL', 'https://api.iconify.design'),
            'limit' => 5,
        ],
        'emoji' => [
            'enabled' => env('EMOJI_ENABLED', true),
            // Twemoji CDN：https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/svg/{code}.svg
            'cdn_base' => env('EMOJI_CDN_BASE', 'https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/svg'),
        ],
        'svg' => [
            'enabled' => env('SVG_ENABLED', true),
            'model' => env('SVG_MODEL', 'qwen-plus'),
        ],
        'nanobanana' => [
            'enabled' => env('NANOBANANA_ENABLED', true),
            'model' => env('NANOBANANA_MODEL', 'wanx2.1-t2i-turbo'),
            'submit_endpoint' => env('NANOBANANA_SUBMIT_URL', 'https://dashscope.aliyuncs.com/api/v1/services/aigc/text2image/image-synthesis'),
            'task_endpoint' => env('NANOBANANA_TASK_URL', 'https://dashscope.aliyuncs.com/api/v1/tasks'),
            'size' => env('NANOBANANA_SIZE', '1024*768'),
            'poll_interval' => 2,
            'max_poll' => 15,
        ],
    ],

    'oss' => [
        'enabled' => env('OSS_ENABLED', false),
        'access_key_id' => env('OSS_ACCESS_KEY_ID', ''),
        'access_key_secret' => env('OSS_ACCESS_KEY_SECRET', ''),
        'region' => env('OSS_REGION', 'cn-hangzhou'),
        'endpoint' => env('OSS_ENDPOINT', 'https://oss-cn-hangzhou.aliyuncs.com'),
        'bucket' => env('OSS_BUCKET', ''),
        // 可选自定义 CDN 域名（https://cdn.example.com）
        'cdn_domain' => env('OSS_CDN_DOMAIN', ''),
        'object_prefix' => env('OSS_OBJECT_PREFIX', 'nlp/images'),
    ],
];
