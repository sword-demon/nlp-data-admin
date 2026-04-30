<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'default' => env('AI_PROVIDER', 'dashscope'),
    'providers' => [
        'dashscope' => [
            'driver' => App\Service\Provider\DashScopeProvider::class,
            'api_key' => env('DASHSCOPE_API_KEY', ''),
            'base_url' => env('DASHSCOPE_BASE_URL', 'https://dashscope.aliyuncs.com/api/v1'),
            'default_model' => env('DASHSCOPE_MODEL', 'qwen-plus'),
            'models' => ['qwen-max', 'qwen-plus', 'qwen-turbo', 'qwen3-max', 'qwen3-plus', 'qwen3-flash'],
            'timeout' => 60,
        ],
        'openai' => [
            'driver' => App\Service\Provider\OpenAICompatibleProvider::class,
            'api_key' => env('OPENAI_API_KEY', ''),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'default_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'models' => ['gpt-4o-mini', 'gpt-4o'],
            'timeout' => 60,
        ],
    ],
];
