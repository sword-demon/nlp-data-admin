<?php

declare(strict_types=1);

use App\Controller\AiChatController;
use App\Controller\AuthController;
use App\Controller\IndexController;
use App\Controller\ObservabilityController;
use App\Controller\PayController;
use App\Controller\VipController;
use App\Controller\WorkshopController;
use App\Middleware\AdminMiddleware;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\QuotaCheckMiddleware;
use Hyperf\HttpServer\Router\Router;

// 健康检查 / 根路由
Router::addRoute(['GET', 'POST', 'HEAD'], '/', [IndexController::class, 'index']);

Router::get('/favicon.ico', static fn() => '');

// 业务 API 统一挂载在 /api 前缀下
Router::addGroup('/api', static function () {
    // 公开认证端点
    Router::addGroup('/auth', static function () {
        Router::post('/register', [AuthController::class, 'register']);
        Router::post('/login', [AuthController::class, 'login']);
    });

    // VIP 套餐展示（公开，用于前端未登录时展示定价）
    Router::get('/vip/plans', [VipController::class, 'plans']);

    // z-pay 异步回调 & 同步跳转（公开，不走 JWT）
    Router::addRoute(['GET', 'POST'], '/pay/notify', [PayController::class, 'notify']);
    Router::addRoute(['GET', 'POST'], '/pay/return', [PayController::class, 'returnPage']);

    // 受 JWT 保护的端点
    Router::addGroup('', static function () {
        Router::post('/auth/refresh', [AuthController::class, 'refresh']);
        Router::post('/auth/logout', [AuthController::class, 'logout']);
        Router::get('/auth/me', [AuthController::class, 'me']);

        Router::get('/ai/providers', [AiChatController::class, 'providers']);
        Router::post('/ai/chat', [AiChatController::class, 'chat']);
        Router::post('/ai/chat/stream', [AiChatController::class, 'chatStream']);

        // VIP 会员信息
        Router::addGroup('/vip', static function () {
            Router::get('/info', [VipController::class, 'info']);
            Router::get('/strategies', [VipController::class, 'strategies']);
            Router::get('/quota', [VipController::class, 'quota']);
        });

        // 支付（JWT 保护）
        Router::addGroup('/pay', static function () {
            Router::post('/create', [PayController::class, 'create']);
            Router::get('/status', [PayController::class, 'status']);
            Router::get('/orders', [PayController::class, 'orders']);
        });

        // 创作工坊（多 Agent 编排）
        Router::addGroup('/workshop', static function () {
            // 创作入口 → 配额检查
            Router::post('/create', [WorkshopController::class, 'create'], ['middleware' => [QuotaCheckMiddleware::class]]);
            Router::post('/{id:\d+}/select-title', [WorkshopController::class, 'selectTitle']);
            Router::put('/{id:\d+}/outline', [WorkshopController::class, 'updateOutline']);
            Router::get('/{id:\d+}/generate-stream', [WorkshopController::class, 'generateStream']);
            Router::get('/{id:\d+}/status', [WorkshopController::class, 'status']);
            Router::get('/{id:\d+}/result', [WorkshopController::class, 'result']);
        });

        // 管理后台 — 可观测性 Dashboard（admin 角色专属）
        Router::addGroup('/admin/observability', static function () {
            Router::get('/overview', [ObservabilityController::class, 'overview']);
            Router::get('/agents', [ObservabilityController::class, 'agents']);
            Router::get('/trend', [ObservabilityController::class, 'trend']);
            Router::get('/slow', [ObservabilityController::class, 'slow']);
            Router::get('/logs', [ObservabilityController::class, 'logs']);
            Router::get('/user/{id:\d+}', [ObservabilityController::class, 'userActivity']);
        }, ['middleware' => [AdminMiddleware::class]]);
    }, ['middleware' => [JwtAuthMiddleware::class]]);

    // 健康探测（无认证）
    Router::addRoute(['GET', 'POST', 'HEAD'], '/', [IndexController::class, 'index']);
});
