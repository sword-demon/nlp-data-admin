<?php

declare(strict_types=1);
/**
 * 全局中间件为空；JWT 认证按路由组挂载，避免影响 /api/auth/login 等公开端点。
 */
return [
    'http' => [],
];
