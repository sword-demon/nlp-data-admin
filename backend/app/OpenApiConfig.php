<?php

declare(strict_types=1);

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'NLP Data Admin API',
    description: 'NLP 数据管理系统 API 文档'
)]
#[OA\Server(
    url: 'http://localhost:9501',
    description: '本地开发环境'
)]
#[OA\Server(
    url: 'https://api.example.com',
    description: '生产环境'
)]
class OpenApiConfig
{
}
