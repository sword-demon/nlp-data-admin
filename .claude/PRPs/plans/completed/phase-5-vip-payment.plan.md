# Plan: Phase 5 — VIP 会员 + 支付

## Summary

实现 VIP 会员体系和 z-pay 支付闭环：定义免费/月费/年费三级会员，通过 z-pay 聚合支付（微信/支付宝扫码 + H5）完成付费升级，配额系统控制每月文章生成数量，配图策略权限区分（AI 生图/Nano Banana 仅 VIP 可用），订单管理支持查询和退款。

## User Story

As a 内容创作者, I want 订阅 VIP 会员获得更多文章生成配额和高级配图能力, 并通过微信/支付宝扫码完成支付, So that 我可以无限制地使用平台的完整创作能力。

## Problem → Solution

Phase 2-4 所有功能对所有用户开放，无商业化能力 → 通过 VIP 会员体系分级（免费 5 篇/月、月费 50 篇/月、年费不限），z-pay 支付实现商业闭环，配图权限控制激励付费转化。

## Metadata

- **Complexity**: Large
- **Source PRD**: `.claude/PRPs/prds/ai-multi-agent-content-platform.prd.md`
- **PRD Phase**: Phase 5 — VIP 会员 + 支付
- **Estimated Files**: 18+

---

## UX Design

### Before
```
┌─────────────────────────────┐
│  所有用户享有相同配额        │
│  无付费入口                  │
│  所有配图策略均可使用        │
│  无订单管理                  │
└─────────────────────────────┘
```

### After
```
┌─────────────────────────────┐
│  个人中心 → 会员中心        │
│  ┌───────────────────────┐  │
│  │ 免费版   月费版  年费版│  │
│  │ 5篇/月  50篇/月  不限 │  │
│  │ 基础配图 高级配图 全部 │  │
│  │  --     ¥29/月  ¥199/年│ │
│  │        [立即开通]       │  │
│  └───────────────────────┘  │
│                              │
│  支付弹窗: 微信/支付宝扫码  │
│  配额耗尽 → 提示升级 VIP    │
│  订单列表 → 查看支付状态    │
└─────────────────────────────┘
```

### Interaction Changes

| Touchpoint | Before | After | Notes |
|---|---|---|---|
| 创作工坊 | 无限制 | 配额不足时弹窗提示升级 | free 用户 5 篇/月 |
| 配图选择 | 全部可用 | NanoBanana/SvgConcept 仅 VIP | ImageStrategyFactory 检查权限 |
| 导航栏 | 无会员入口 | 显示 VIP 状态 + 到期时间 | 根据等级显示不同标签 |
| 订单页面 | 不存在 | 支付记录列表 + 状态 | 支持查看退款状态 |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Model/User.php` | 1-83 | VIP 等级常量 + 配额字段 |
| P0 | `backend/app/Helpers/ApiResponse.php` | 1-55 | 统一响应格式 |
| P0 | `backend/app/Constants/Code.php` | 1-17 | 业务状态码 |
| P1 | `backend/app/Service/WorkshopOrchestrator.php` | all | 配额检查插入点 |
| P1 | `backend/app/Service/ImageStrategyFactory.php` | all (Phase 4) | 配图权限检查插入点 |
| P1 | `backend/config/routes.php` | all | 路由定义格式 |
| P2 | `backend/.env.example` | all | 环境变量模板 |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| z-pay 页面跳转支付 | `https://z-pay.cn/doc.html` | `POST https://zpayz.cn/submit.php`，参数: pid, type, out_trade_no, notify_url, return_url, name, money, sign |
| z-pay API 接口支付 | `https://z-pay.cn/doc.html` | 返回支付二维码 URL 或跳转链接 |
| z-pay 订单查询 | `https://zpayz.cn/api.php?act=order&pid={pid}` | 查询订单支付状态 |
| z-pay MD5 签名 | `https://z-pay.cn/doc.html` | 参数按字母排序拼接 + 密钥 MD5 |
| z-pay 回调通知 | `https://z-pay.cn/doc.html` | POST 到 notify_url，需验证签名 |

---

## Patterns to Mirror

### CONTROLLER_PATTERN
```php
// SOURCE: backend/app/Controller/AbstractController.php
abstract class AbstractController
{
    #[Inject]
    protected RequestInterface $request;

    #[Inject]
    protected ResponseInterface $response;
}
```

### API_RESPONSE_PATTERN
```php
// SOURCE: backend/app/Helpers/ApiResponse.php
return ApiResponse::success($this->response, $data, '操作成功');
return ApiResponse::error($this->response, Code::VALIDATION_ERROR, '参数错误', 422);
```

### MODEL_PATTERN
```php
// SOURCE: backend/app/Model/User.php
class User extends Model
{
    public const VIP_FREE = 'free';
    public const VIP_MONTHLY = 'monthly';
    public const VIP_YEARLY = 'yearly';
    // quota_total, quota_used, vip_level, vip_expired_at 字段已存在
}
```

### PAYMENT_SERVICE_PATTERN (new)
```php
class ZPayService
{
    public function createOrder(int $userId, string $plan, string $payType): array;
    public function verifyCallback(array $params): bool;
    public function queryOrder(string $outTradeNo): array;
    public function generateSign(array $params): string;
}
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/app/Model/Order.php` | CREATE | 订单模型 |
| `backend/app/Model/VipPlan.php` | CREATE | VIP 套餐定义模型 |
| `backend/app/Service/ZPayService.php` | CREATE | z-pay 支付对接核心 |
| `backend/app/Service/VipService.php` | CREATE | VIP 会员管理（升级/续费/配额） |
| `backend/app/Service/QuotaService.php` | CREATE | 配额检查与扣减 |
| `backend/app/Controller/PayController.php` | CREATE | 支付/回调/订单 API |
| `backend/app/Controller/VipController.php` | CREATE | VIP 套餐/会员信息 API |
| `backend/app/Middleware/QuotaCheckMiddleware.php` | CREATE | 创作配额检查中间件 |
| `backend/config/autoload/payment.php` | CREATE | z-pay 配置 |
| `backend/config/autoload/vip.php` | CREATE | VIP 等级与配额配置 |
| `backend/config/routes.php` | UPDATE | 添加支付 + VIP 路由 |
| `backend/.env` | UPDATE | ZPAY_PID / ZPAY_KEY |
| `backend/.env.example` | UPDATE | 同步模板 |
| `backend/migrations/2026_04_30_000004_create_orders_table.php` | CREATE | 订单表迁移 |
| `backend/migrations/2026_04_30_000005_create_vip_plans_table.php` | CREATE | VIP 套餐表迁移 |
| `frontend/src/pages/VipCenter.vue` | CREATE | VIP 会员中心页面 |
| `frontend/src/pages/OrderList.vue` | CREATE | 订单列表页面 |
| `frontend/src/api/payment.ts` | CREATE | 支付 API 调用 |
| `frontend/src/api/vip.ts` | CREATE | VIP API 调用 |
| `frontend/src/components/PayModal.vue` | CREATE | 支付二维码弹窗 |
| `frontend/src/router/index.ts` | UPDATE | 添加 VIP + 订单路由 |
| `frontend/src/layouts/DefaultLayout.vue` | UPDATE | 导航栏显示 VIP 状态 |
| `frontend/src/stores/vip.ts` | CREATE | VIP Pinia store |

## NOT Building

- 自动续费 — v1 仅手动续费
- 优惠券/折扣码 — 后续版本
- 退款流程前端操作 — 仅后端管理（z-pay 提交退款 API）
- 发票管理 — 非核心
- 多币种支持 — 仅人民币
- 积分系统 — 后续版本

---

## Step-by-Step Tasks

### Task 1: 创建数据库迁移（orders + vip_plans）
- **ACTION**: 创建订单表和 VIP 套餐表迁移文件
- **IMPLEMENT**:
  - `orders` 表: id, user_id, out_trade_no, zpay_order_id, plan_type(monthly/yearly), amount, pay_type(wechat/alipay), status(pending/paid/failed/refunded), paid_at, notify_raw(JSON), created_at, updated_at
  - `vip_plans` 表: id, name, level(free/monthly/yearly), price, duration_days, quota_monthly(-1 表示无限), allowed_image_strategies(JSON), is_active, sort_order
  - 插入 3 条默认 VIP 套餐数据
- **MIRROR**: 参考 `migrations/2026_04_30_000001_create_users_table.php` 的迁移结构
- **IMPORTS**: `Hyperf\Database\Schema\Schema`
- **GOTCHA**: `out_trade_no` 需要唯一索引；`orders` 表的 `notify_raw` 用于存储 z-pay 回调原始数据（幂等校验）
- **VALIDATE**: `docker exec nlp-mysql mysql -u root -pnlp_root_2024 nlp_content -e "SHOW TABLES"` 显示 orders + vip_plans

### Task 2: 创建 Order 和 VipPlan 模型
- **ACTION**: 创建 Eloquent 模型
- **IMPLEMENT**:
  - `Order`: $fillable 包含所有字段，$casts 包含 notify_raw=json, paid_at=datetime, amount=float
  - `Order` 常量: STATUS_PENDING/PAID/FAILED/REFUNDED, PAY_TYPE_WECHAT/ALIPAY, PLAN_MONTHLY/YEARLY
  - `VipPlan`: $fillable 包含 name/level/price/duration_days/quota_monthly/allowed_image_strategies/is_active/sort_order
  - 关联: Order belongsTo User, VipPlan hasMany Order
- **MIRROR**: 参考 `User.php` 和 `Article.php` 的模型结构
- **IMPORTS**: `Hyperf\DbConnection\Model\Model`
- **GOTCHA**: VipPlan 的 allowed_image_strategies 存储为 JSON 数组，如 `["pexels","mermaid","iconify","emoji","svg","nanobanana"]`
- **VALIDATE**: 语法检查通过

### Task 3: 创建支付配置和环境变量
- **ACTION**: 创建 `config/autoload/payment.php` 和 `config/autoload/vip.php`，更新 `.env`
- **IMPLEMENT**:
  - `payment.php`:
    ```php
    return [
        'zpay' => [
            'pid' => env('ZPAY_PID', ''),
            'key' => env('ZPAY_KEY', ''),
            'submit_url' => env('ZPAY_SUBMIT_URL', 'https://zpayz.cn/submit.php'),
            'api_url' => env('ZPAY_API_URL', 'https://zpayz.cn/api.php'),
            'notify_url' => env('ZPAY_NOTIFY_URL', ''),
            'return_url' => env('ZPAY_RETURN_URL', ''),
        ],
    ];
    ```
  - `vip.php`:
    ```php
    return [
        'plans' => [
            'free' => ['quota_monthly' => 5, 'allowed_strategies' => ['pexels','mermaid','iconify','emoji']],
            'monthly' => ['quota_monthly' => 50, 'allowed_strategies' => ['pexels','mermaid','iconify','emoji','svg','nanobanana']],
            'yearly' => ['quota_monthly' => -1, 'allowed_strategies' => ['pexels','mermaid','iconify','emoji','svg','nanobanana']],
        ],
        'default_plan' => 'free',
    ];
    ```
  - `.env` 添加 ZPAY_PID, ZPAY_KEY, ZPAY_NOTIFY_URL, ZPAY_RETURN_URL
- **MIRROR**: 参考 `config/autoload/model.php` 的 env 读取模式
- **IMPORTS**: 无
- **GOTCHA**: ZPAY_NOTIFY_URL 必须是公网可访问的 URL（开发环境需使用 ngrok 等内网穿透）
- **VALIDATE**: `config('payment.zpay.pid')` 返回配置值

### Task 4: 创建 ZPayService
- **ACTION**: 实现 z-pay 支付对接核心服务
- **IMPLEMENT**:
  - `generateSign(array $params): string` — 按 z-pay MD5 签名规则: 参数按 key 字母升序排列，过滤空值和 sign/sign_type，拼接为 `key1=value1&key2=value2...` + 密钥，MD5 加密
  - `createOrder(int $userId, string $planType, string $payType): array` — 生成 out_trade_no，调用 z-pay submit.php 获取支付链接/二维码
  - `verifyCallback(array $params): bool` — 验证回调签名，防止伪造
  - `queryOrder(string $outTradeNo): array` — 调用 z-pay API 查询订单状态
  - `refund(string $outTradeNo, string $reason): array` — 提交退款（调用 z-pay 退款 API）
- **MIRROR**: 构造函数 DI 注入 ConfigInterface + ClientFactory（Guzzle）
- **IMPORTS**: `GuzzleHttp\Client`, `Hyperf\Guzzle\ClientFactory`, `Hyperf\Di\Annotation\Inject`
- **GOTCHA**:
  - MD5 签名: `md5(implode('&', array_map(fn($k,$v) => "$k=$v", array_keys($sorted), $sorted)) . $key)`
  - out_trade_no 格式: `NLP_{YmdHis}_{random6}`
  - z-pay 支付方式 type: `wxpay`(微信), `alipay`(支付宝)
  - 回调验证: 重新计算签名与回调中的 sign 字段比对
- **VALIDATE**: 单元测试签名生成与验证

### Task 5: 创建 VipService
- **ACTION**: VIP 会员管理（升级/续费/配额查询）
- **IMPLEMENT**:
  - `getPlans(): array` — 返回所有可用的 VIP 套餐
  - `getUserVipInfo(int $userId): array` — 返回用户当前 VIP 状态（等级、到期时间、配额使用情况）
  - `activateVip(int $userId, string $planType): void` — 激活/续费 VIP（更新 User 的 vip_level, vip_expired_at, quota_total）
  - `isVipActive(int $userId): bool` — 检查 VIP 是否有效（未过期）
  - `checkVipExpired(): void` — 定时检查 VIP 过期并降级（可由 cron 触发）
- **MIRROR**: 使用构造函数 DI 注入 UserRepository 或直接 Model 查询
- **IMPORTS**: `App\Model\User`, `App\Model\VipPlan`, `Carbon\Carbon`
- **GOTCHA**:
  - 续费逻辑: 如果当前 VIP 未过期，新到期时间 = 当前到期时间 + 套餐时长；如果已过期，从现在开始计算
  - quota_total 更新: 升级时重置为套餐配额，不累加
  - 免费用户 quota_total = 5，月费 = 50，年费 = -1(无限)
- **VALIDATE**: 激活 VIP 后 User 记录的 vip_level 和 vip_expired_at 正确更新

### Task 6: 创建 QuotaService
- **ACTION**: 配额检查与扣减服务
- **IMPLEMENT**:
  - `checkQuota(int $userId): bool` — 检查用户是否还有剩余配额
  - `consumeQuota(int $userId): void` — 扣减 1 次配额（文章生成时调用）
  - `getQuotaUsage(int $userId): array` — 返回 {total, used, remaining, reset_date}
  - `resetMonthlyQuota(int $userId): void` — 月初重置配额
  - 内部使用 Redis 缓存配额信息（减少 DB 查询）
- **MIRROR**: 使用构造函数 DI，结合 Redis 和 MySQL
- **IMPORTS**: `Hyperf\Redis\Redis`, `App\Model\User`
- **GOTCHA**:
  - Redis key: `quota:{userId}:{YYYYMM}` 存储当月已使用次数
  - 月费/年费用户重置周期不同；年费 -1 表示不限制
  - 使用 Redis INBY 原子操作避免并发超发
  - DB 的 quota_used 作为持久化备份
- **VALIDATE**: 免费用户第 6 次创作时 checkQuota 返回 false

### Task 7: 创建 QuotaCheckMiddleware
- **ACTION**: 创作入口配额检查中间件
- **IMPLEMENT**:
  - 拦截 `POST /api/workshop/create` 和 `POST /api/workshop/{id}/proceed`
  - 调用 `QuotaService::checkQuota()`
  - 配额不足时返回 403 JSON: `{code: 403, message: "配额不足，请升级 VIP", data: {current_plan, quota_used, quota_total}}`
  - 配额充足时放行
- **MIRROR**: 参考 `JwtAuthMiddleware` 的中间件结构
- **IMPORTS**: `App\Service\QuotaService`, `Psr\Http\Message\...`
- **GOTCHA**: 中间件顺序: JWT 认证 → 配额检查 → 业务处理
- **VALIDATE**: 配额不足时请求返回 403

### Task 8: 更新 ImageStrategyFactory 添加权限检查
- **ACTION**: 在配图策略工厂中加入 VIP 权限控制
- **IMPLEMENT**:
  - 获取当前用户 VIP 等级
  - 读取 `config/autoload/vip.php` 中对应等级的 `allowed_strategies`
  - 如果请求的策略不在允许列表中，抛出 BusinessException
  - NanoBanana 和 SvgConcept 仅 monthly/yearly 可用
- **MIRROR**: 在现有 `ImageStrategyFactory::driver()` 方法中添加权限判断
- **IMPORTS**: `App\Service\VipService`, `App\Exception\BusinessException`
- **GOTCHA**: 需要获取当前用户上下文（从 JWT 中间件注入的 Context 中读取 user_id）
- **VALIDATE**: 免费用户调用 NanoBanana 策略时抛出异常

### Task 9: 创建 PayController
- **ACTION**: 支付 API 端点
- **IMPLEMENT**:
  - `POST /api/pay/create` → 创建支付订单，返回支付链接/二维码 URL
  - `POST /api/pay/notify` → z-pay 异步回调（无需 JWT），验证签名后更新订单状态 + 激活 VIP
  - `GET /api/pay/return` → z-pay 同步跳转（前端展示支付结果）
  - `GET /api/pay/status?out_trade_no=xxx` → 查询订单支付状态（前端轮询）
  - `GET /api/pay/orders` → 用户订单列表（分页）
- **MIRROR**: 扩展 `AbstractController`，使用 `ApiResponse::success/error`
- **IMPORTS**: `App\Service\ZPayService`, `App\Service\VipService`, `App\Model\Order`
- **GOTCHA**:
  - `/api/pay/notify` 无需 JWT 中间件保护（z-pay 服务器回调）
  - 回调需幂等处理: 检查订单状态，已支付的不重复处理
  - 签名验证失败时记录日志但不处理
  - `create` 请求参数: `{plan_type: "monthly"|"yearly", pay_type: "wxpay"|"alipay"}`
- **VALIDATE**: curl 测试创建订单 → 获取支付 URL

### Task 10: 创建 VipController
- **ACTION**: VIP 会员信息 API
- **IMPLEMENT**:
  - `GET /api/vip/plans` → 返回所有可用 VIP 套餐
  - `GET /api/vip/info` → 当前用户 VIP 状态（等级、到期时间、配额）
  - `GET /api/vip/strategies` → 当前用户可用的配图策略列表
- **MIRROR**: 扩展 `AbstractController`
- **IMPORTS**: `App\Service\VipService`, `App\Service\QuotaService`
- **GOTCHA**: plans 接口无需 JWT（展示定价用），info/strategies 需要 JWT
- **VALIDATE**: 获取 VIP 信息返回正确等级

### Task 11: 更新路由配置
- **ACTION**: 注册支付和 VIP 相关路由
- **IMPLEMENT**:
  - `/api/pay/create` — POST, JWT 保护
  - `/api/pay/notify` — POST, 无 JWT（z-pay 回调）
  - `/api/pay/return` — GET, 无 JWT
  - `/api/pay/status` — GET, JWT 保护
  - `/api/pay/orders` — GET, JWT 保护
  - `/api/vip/plans` — GET, 无 JWT
  - `/api/vip/info` — GET, JWT 保护
  - `/api/vip/strategies` — GET, JWT 保护
  - 将 QuotaCheckMiddleware 应用到 workshop 路由组
- **MIRROR**: 参考 Phase 2 的路由注册模式
- **IMPORTS**: `Hyperf\HttpServer\Router\Router`
- **GOTCHA**: `/api/pay/notify` 必须排除在 JWT 中间件之外
- **VALIDATE**: `php bin/hyperf.php describe:routes` 显示所有新路由

### Task 12: 创建前端 VIP 中心页面
- **ACTION**: VIP 会员中心展示套餐 + 支付流程
- **IMPLEMENT**:
  - `VipCenter.vue`:
    - 3 列套餐卡片（免费/月费/年费），高亮当前套餐
    - 显示配额使用情况（进度条: 已用/总量）
    - 每个付费套餐有 "立即开通" 按钮
    - 当前 VIP 状态显示（到期时间）
  - `PayModal.vue`:
    - 支付方式选择（微信/支付宝）
    - 调用 `/api/pay/create` 获取支付链接
    - 展示支付二维码（使用 qrcode.js 或类似库渲染）
    - 轮询 `/api/pay/status` 检查支付状态
    - 支付成功后关闭弹窗 + 刷新 VIP 状态
  - `vip.ts` store: plans, currentVipInfo, quotaUsage
  - `payment.ts` API: createOrder, queryOrderStatus, getOrders
- **MIRROR**: Ant Design Vue Card/Modal/Progress 组件；Pinia store 模式
- **IMPORTS**: `ant-design-vue`, `pinia`, `axios`, `qrcode` (或 `qrcode.vue`)
- **GOTCHA**: 二维码展示需要安装 qrcode 库: `npm install qrcode.vue` 或使用在线 API
- **VALIDATE**: 点击 "立即开通" → 选择支付方式 → 弹窗显示二维码

### Task 13: 创建前端订单列表页面
- **ACTION**: 用户订单管理页面
- **IMPLEMENT**:
  - `OrderList.vue`:
    - 订单列表（表格: 订单号/套餐/金额/支付方式/状态/时间）
    - 状态标签（待支付/已支付/已退款）
    - 分页加载
    - 待支付订单可重新支付
- **MIRROR**: Ant Design Table + Tag 组件
- **IMPORTS**: `ant-design-vue`
- **GOTCHA**: 订单状态使用 Tag 颜色区分（待支付=orange, 已支付=green, 已退款=red）
- **VALIDATE**: 订单列表正确显示

### Task 14: 更新 DefaultLayout 显示 VIP 状态
- **ACTION**: 导航栏显示用户 VIP 状态和配额
- **IMPLEMENT**:
  - Header 区域添加 VIP 标签（免费用户灰色，月费蓝色，年费金色）
  - 显示配额: "本月已用 3/5 篇"
  - 点击跳转 VIP 中心
  - 配额不足时显示警告样式
- **MIRROR**: Ant Design Tag/Badge 组件
- **IMPORTS**: `ant-design-vue`
- **GOTCHA**: VIP 状态从 Pinia vip store 读取
- **VALIDATE**: 不同等级用户看到不同的 VIP 标签

### Task 15: 集成验证
- **ACTION**: Docker 环境端到端测试支付流程
- **IMPLEMENT**:
  - 启动全栈 → 注册免费用户 → 尝试第 6 次创作 → 验证 403 拦截
  - 访问 VIP 中心 → 选择月费 → 创建支付订单 → 验证订单记录
  - 模拟 z-pay 回调 → 验证 VIP 激活
  - 验证配额重置和配图权限变化
- **MIRROR**: Phase 1/2 集成验证模式
- **IMPORTS**: N/A
- **GOTCHA**: 开发环境回调测试需使用 curl 模拟 z-pay 回调或使用 ngrok
- **VALIDATE**: 完整支付→激活→使用流程无报错

---

## Testing Strategy

### Unit Tests

| Test | Input | Expected Output |
|---|---|---|
| ZPayService::generateSign | sorted params + key | 正确的 MD5 签名 |
| ZPayService::verifyCallback | valid callback params | true |
| ZPayService::verifyCallback | tampered sign | false |
| VipService::activateVip | userId, monthly | User.vip_level=monthly, vip_expired_at=now+30d |
| QuotaService::checkQuota | free user, used=4 | true |
| QuotaService::checkQuota | free user, used=5 | false |
| QuotaService::consumeQuota | userId | quota_used + 1 |
| ImageStrategyFactory | free user, nanobanana | throws BusinessException |
| ImageStrategyFactory | monthly user, nanobanana | returns NanoBananaStrategy |

### Edge Cases Checklist
- [ ] z-pay 回调重复通知 → 幂等处理，不重复激活
- [ ] 签名验证失败 → 记录日志，不处理
- [ ] VIP 过期后降级 → 下次请求时检查并重置
- [ ] 配额并发扣减 → Redis INCR 原子操作保证一致性
- [ ] 支付超时 → 订单状态保持 pending，可重新支付
- [ ] 年费无限配额 → quota_total=-1，checkQuota 直接返回 true
- [ ] 续费叠加 → 未过期用户续费，到期时间累加而非覆盖

---

## Validation Commands

### Static Analysis
```bash
php -l app/Service/ZPayService.php app/Service/VipService.php app/Service/QuotaService.php \
  app/Controller/PayController.php app/Controller/VipController.php \
  app/Model/Order.php app/Model/VipPlan.php app/Middleware/QuotaCheckMiddleware.php
```
EXPECT: No syntax errors

### TypeScript Check
```bash
cd frontend && ./node_modules/.bin/vue-tsc --noEmit
```
EXPECT: Zero type errors

### Database Validation
```bash
docker exec nlp-mysql mysql -u root -pnlp_root_2024 nlp_content \
  -e "SELECT * FROM vip_plans; SELECT id,user_id,out_trade_no,status FROM orders LIMIT 5"
```
EXPECT: 3 条 VIP 套餐 + 测试订单

### Sign Verification Test
```bash
php -r "
\$service = new App\Service\ZPayService(...);
\$params = ['pid' => '1001', 'type' => 'wxpay', 'out_trade_no' => 'TEST_001', 'money' => '29.00'];
echo \$service->generateSign(\$params);
"
```
EXPECT: 32 位 MD5 字符串

### Browser Validation
```bash
cd frontend && npm run dev
```
- [ ] 访问 `/vip` — VIP 中心页面
- [ ] 3 个套餐卡片正确显示
- [ ] 配额进度条显示
- [ ] 点击 "立即开通" — 支付方式选择
- [ ] 支付弹窗显示二维码

---

## Acceptance Criteria
- [ ] 3 个 VIP 套餐可在前端展示
- [ ] z-pay 创建订单返回支付链接/二维码
- [ ] z-pay 回调正确验证签名并激活 VIP
- [ ] 免费用户配额耗尽后无法创作（403）
- [ ] VIP 用户配额正确增加
- [ ] NanoBanana/SvgConcept 仅 VIP 可用
- [ ] 订单列表正确展示
- [ ] 支付成功后 VIP 状态实时更新

## Completion Checklist
- [ ] z-pay 签名算法正确实现
- [ ] 回调幂等处理
- [ ] 配额检查使用 Redis 原子操作
- [ ] VIP 过期自动降级逻辑
- [ ] 环境变量无硬编码密钥
- [ ] 前端支付流程完整（选套餐→支付→激活）
- [ ] 错误处理完善（支付失败/网络异常/签名错误）

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| z-pay 回调不可达（开发环境） | H | M | 使用 ngrok 内网穿透；提供 curl 模拟回调脚本 |
| MD5 签名算法与 z-pay 不一致 | M | H | 严格按文档实现 + 对比官方示例 |
| 配额并发超发 | L | M | Redis INCR 原子操作 + DB 二次校验 |
| VIP 到期检查延迟 | L | L | 中间件每次请求检查 + 定时任务兜底 |
| 支付二维码前端渲染问题 | L | L | 备选: 使用在线二维码 API |

## Notes

- z-pay 是聚合支付平台，支持个人/企业开通微信/支付宝接口，资金直接结算到银行卡
- 开发环境无法接收 z-pay 真实回调，需准备模拟回调脚本: `curl -X POST http://localhost:9501/api/pay/notify -d "pid=xxx&out_trade_no=xxx&trade_no=xxx&type=wxpay&name=xxx&money=29.00&sign=xxx"`
- VIP 等级常量已定义在 `User.php`: VIP_FREE/VIP_MONTHLY/VIP_YEARLY
- 配额字段已存在于 users 表: quota_total, quota_used
- 前端二维码渲染推荐 `qrcode.vue` 组件（轻量，无外部依赖）
