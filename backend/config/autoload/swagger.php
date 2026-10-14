<?php

declare(strict_types=1);

/**
 * Swagger / OpenAPI 配置
 */
return [
    'enable' => true,
    'output_file' => BASE_PATH . '/storage/swagger.json',
    'swagger' => '3.0',
    'scan' => [
        'paths' => [
            BASE_PATH . '/app/Service/Agent/Outcome/Payload',
            BASE_PATH . '/app/Controller',
        ],
    ],
    'info' => [
        'title' => 'NLP Data Admin API',
        'version' => '1.0.0',
        'description' => 'AI 内容创作工坊 API',
    ],
];
