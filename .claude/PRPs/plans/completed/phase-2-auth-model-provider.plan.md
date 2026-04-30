# Plan: Phase 2 — 用户认证 + 模型 Provider

## Summary

实现 JWT 用户认证体系（注册/登录/令牌刷新）和 AI 模型 Provider 层（DashScope 通义千问 SSE 流式 + OpenAI 兼容接口预留）。通过策略模式实现模型 Provider 的可扩展切换，建立 SSE 流式输出的基础通道。

## User Story

As a 内容创作者, I want 注册账号并登录系统, 然后通过 AI 模型实时流式生成内容, So that 我可以安全使用平台并实时看到 AI 的创作过程。

## Problem → Solution

当前无认证、无 AI 调用能力 → 用户可注册登录获得 JWT 令牌，系统可通过 DashScope 流式调用大模型，并预留 OpenAI 兼容接口用于后续扩展。

## Metadata

- **Complexity**: Large
- **Source PRD**: `.claude/PRPs/prds/ai-multi-agent-content-platform.prd.md`
- **PRD Phase**: Phase 2 — 用户认证 + 模型 Provider
- **Estimated Files**: 18+

---

## UX Design

### Before
```
┌─────────────────────────────┐
│  前端: 无登录页面           │
│  任何人都能访问             │
│  无 AI 调用能力             │
└─────────────────────────────┘
```

### After
```
┌─────────────────────────────┐
│  /login  → 登录表单         │
│  /register → 注册表单       │
│  登录后: JWT Token 存储     │
│  SSE 测试: 发送消息 → 流式  │
│  返回 AI 生成内容           │
└─────────────────────────────┘
```

### Interaction Changes

| Touchpoint | Before | After | Notes |
|---|---|---|---|
| 访问受限页面 | 直接可看 | 重定向到 /login | JWT 中间件保护 |
| 登录 | 不存在 | 邮箱+密码 → JWT Token | 令牌有效期 24h |
| 注册 | 不存在 | 用户名+邮箱+密码 → 账号创建 | 校验唯一性 |
| AI 调用 | 不存在 | POST /api/ai/chat → SSE Stream | DashScope 流式返回 |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Controller/AbstractController.php` | 1-30 | 控制器基类模式，DI 注入方式 |
| P0 | `backend/app/Helpers/ApiResponse.php` | 1-55 | 统一响应格式 |
| P0 | `backend/app/Constants/Code.php` | 1-17 | 业务状态码 |
| P0 | `backend/app/Model/User.php` | 1-47 | 用户模型结构与关联 |
| P0 | `backend/config/autoload/exceptions.php` | 1-19 | 异常处理器注册方式 |
| P1 | `backend/config/routes.php` | 1-19 | 路由定义格式 |
| P1 | `backend/config/autoload/middlewares.php` | 1-13 | 中间件注册位置 |
| P1 | `backend/app/Exception/Handler/AppExceptionHandler.php` | 1-46 | JSON 异常格式 |
| P2 | `backend/.env` | 1-18 | 环境变量配置 |
| P2 | `backend/config/autoload/server.php` | 1-56 | 服务器配置（SSE 相关） |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| DashScope SSE 流式 | `https://docs.qwencloud.com/developer-guides/text-generation/streaming` | `X-DashScope-SSE: enable` header + `incremental_output: true` |
| DashScope API 参考 | `https://docs.qwencloud.com/api-reference/chat/dashscope` | endpoint: `/api/v1/services/aigc/multimodal-generation/generation` |
| l1n6yun/hyperf-jwt | GitHub | JWT 注解 `#[Auth]` + 模型实现 `JwtSubjectInterface` |
| Hyperf EventStream | `Hyperf\Engine\Http\EventStream` | Phase 1 调研: write() 返回 false 检测断连 |

---

## Patterns to Mirror

### CONTROLLER_PATTERN
```php
// SOURCE: backend/app/Controller/AbstractController.php:20-29
abstract class AbstractController
{
    #[Inject]
    protected RequestInterface $request;

    #[Inject]
    protected ResponseInterface $response;
}

// SOURCE: backend/app/Controller/IndexController.php:15-25
class IndexController extends AbstractController
{
    public function index(): array
    {
        // 返回数组自动转 JSON
        return ['message' => 'Hello'];
    }
}
```

### API_RESPONSE_PATTERN
```php
// SOURCE: backend/app/Helpers/ApiResponse.php:12-22
use App\Helpers\ApiResponse;

// 成功响应
return ApiResponse::success($this->response, $data, '操作成功');

// 错误响应
return ApiResponse::error($this->response, Code::VALIDATION_ERROR, '参数错误', 422);
```

### MODEL_PATTERN
```php
// SOURCE: backend/app/Model/User.php:9-47
class User extends Model
{
    protected ?string $table = 'users';
    protected array $fillable = ['username', 'email', ...];
    protected array $casts = ['vip_expired_at' => 'datetime', ...];
}
```

### EXCEPTION_HANDLER_PATTERN
```php
// SOURCE: backend/app/Exception/Handler/AppExceptionHandler.php:20-40
// JSON 格式异常响应
return $response
    ->withHeader('Content-Type', 'application/json')
    ->withStatus(500)
    ->withBody(new SwooleStream($body));
```

### ROUTE_PATTERN
```php
// SOURCE: backend/config/routes.php:14
Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');
// 使用 Router::addRoute() 或 Router::addGroup()
```

### MIDDLEWARE_PATTERN
```php
// SOURCE: backend/config/autoload/middlewares.php:13
// 当前为空数组，参考 Hyperf 文档的中间件注册方式
return [
    'http' => [
        App\Middleware\JwtAuthMiddleware::class,
    ],
];
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/app/Service/AuthService.php` | CREATE | 用户注册/登录/令牌业务逻辑 |
| `backend/app/Controller/AuthController.php` | CREATE | 注册/登录/刷新 API 端点 |
| `backend/app/Middleware/JwtAuthMiddleware.php` | CREATE | JWT 令牌验证中间件 |
| `backend/app/Contract/ModelProviderInterface.php` | CREATE | 模型 Provider 策略接口 |
| `backend/app/Service/Provider/DashScopeProvider.php` | CREATE | DashScope SSE 流式实现 |
| `backend/app/Service/Provider/OpenAICompatibleProvider.php` | CREATE | OpenAI 兼容接口占位 |
| `backend/app/Service/ModelProviderService.php` | CREATE | Provider 调度与工厂 |
| `backend/app/Controller/AiChatController.php` | CREATE | AI 聊天 SSE 端点 |
| `backend/app/Exception/BusinessException.php` | CREATE | 业务异常类 |
| `backend/config/autoload/jwt.php` | CREATE | JWT 配置 |
| `backend/config/autoload/model.php` | CREATE | 模型 Provider 配置 |
| `backend/config/routes.php` | UPDATE | 添加认证 + AI 路由 |
| `backend/config/autoload/middlewares.php` | UPDATE | 注册 JWT 中间件 |
| `backend/config/autoload/exceptions.php` | UPDATE | 注册 JWT 异常处理器 |
| `backend/app/Model/User.php` | UPDATE | 实现 JwtSubjectInterface |
| `backend/composer.json` | UPDATE | 添加 JWT 依赖 |
| `backend/.env` | UPDATE | 添加 JWT_SECRET / AI 配置 |
| `backend/.env.example` | UPDATE | 同步环境变量模板 |
| `frontend/src/pages/Login.vue` | CREATE | 登录页面 |
| `frontend/src/pages/Register.vue` | CREATE | 注册页面 |
| `frontend/src/router/index.ts` | UPDATE | 添加认证路由 |
| `frontend/src/api/auth.ts` | CREATE | 认证 API 调用 |

## NOT Building

- 密码重置 / 邮箱验证 — Phase 2 仅基础认证
- OAuth 第三方登录 — 后续版本
- API 令牌速率限制 — Phase 5 与 VIP 体系一起实现
- 完整的 OpenAI 兼容 Provider — 仅预留接口，DashScope 为唯一实现
- 前端完整 UI — 仅登录/注册页面，创作工坊 UI 在 Phase 7

---

## Step-by-Step Tasks

### Task 1: 安装 JWT 依赖并配置
- **ACTION**: 安装 `l1n6yun/hyperf-jwt`，生成 JWT 密钥，创建配置文件
- **IMPLEMENT**:
  - `composer require l1n6yun/hyperf-jwt`
  - `php bin/hyperf.php gen:jwt-secret` 生成密钥
  - 创建 `config/autoload/jwt.php`: algo=HS256, ttl=86400, provider=user
  - 在 `.env` 和 `.env.example` 中添加 `JWT_SECRET`
- **MIRROR**: 遵循 `config/autoload/databases.php` 的 env 读取模式
- **IMPORTS**: `L1n6yun\HyperfJwt\...`
- **GOTCHA**: 需要在 Docker 容器内运行 `composer require`，避免 PHP 版本差异
- **VALIDATE**: `php bin/hyperf.php` 启动不报错，`gen:jwt-secret` 命令存在

### Task 2: User 模型实现 JwtSubjectInterface
- **ACTION**: 让 User 模型实现 `JwtSubjectInterface` 接口
- **IMPLEMENT**:
  - `implements JwtSubjectInterface`
  - `getJwtIdentifier()`: 返回 `(string)$this->id`
  - `retrieveById($key)`: 静态方法，从缓存或数据库查询用户
- **MIRROR**: 遵循现有 Model 的 PascalCase + extends Model 模式
- **IMPORTS**: `L1n6yun\HyperfJwt\Contracts\JwtSubjectInterface`
- **GOTCHA**: `retrieveById` 必须是 static 方法
- **VALIDATE**: 代码无语法错误

### Task 3: 创建业务异常类
- **ACTION**: 创建 `BusinessException` 用于业务逻辑错误
- **IMPLEMENT**: 继承 `\RuntimeException`，构造函数接收 `int $code` 和 `string $message`
- **MIRROR**: 参考 `AppExceptionHandler` 中的 JSON 格式
- **IMPORTS**: 无额外依赖
- **GOTCHA**: 异常 code 使用 `App\Constants\Code` 常量
- **VALIDATE**: 抛出异常时能被 `AppExceptionHandler` 捕获

### Task 4: 创建 AuthService
- **ACTION**: 实现注册和登录业务逻辑
- **IMPLEMENT**:
  - `register(array $data): User` — 校验唯一性，bcrypt 加密密码，创建用户
  - `login(string $email, string $password): array` — 验证凭据，调用 `auth()->login($user)` 返回 token
  - `refresh(): array` — 刷新令牌
  - `logout(): void` — 使令牌失效
- **MIRROR**: 使用构造函数 DI 注入依赖（参考 `AppExceptionHandler`）
- **IMPORTS**: `App\Model\User`, `L1n6yun\HyperfJwt\...`, `Hyperf\Di\Annotation\Inject`
- **GOTCHA**: 密码使用 `password_hash($password, PASSWORD_BCRYPT)`；登录验证用 `password_verify()`
- **VALIDATE**: 单元测试：注册返回 User，登录返回 token 数组

### Task 5: 创建 AuthController
- **ACTION**: 注册/登录/刷新/登出 API 端点
- **IMPLEMENT**:
  - `POST /api/auth/register` → `register()`: 接收 username/email/password，返回用户+token
  - `POST /api/auth/login` → `login()`: 接收 email/password，返回 token
  - `POST /api/auth/refresh` → `refresh()`: 需要 JWT，返回新 token
  - `POST /api/auth/logout` → `logout()`: 需要 JWT，使 token 失效
  - `GET /api/auth/me` → `me()`: 需要 JWT，返回当前用户信息
- **MIRROR**: 扩展 `AbstractController`，使用 `ApiResponse::success/error`
- **IMPORTS**: `App\Service\AuthService`, `App\Helpers\ApiResponse`, `App\Constants\Code`
- **GOTCHA**: 注册/登录端点不需要 JWT 认证，需在中间件中排除
- **VALIDATE**: curl 测试注册和登录，获得 JWT token

### Task 6: 创建 JwtAuthMiddleware
- **ACTION**: 实现 JWT 认证中间件
- **IMPLEMENT**:
  - 从 Authorization header 提取 Bearer token
  - 调用 `auth()->check()` 验证
  - 验证失败返回 401 JSON
  - 支持排除路径（register/login 等）
  - 通过时将用户信息注入上下文
- **MIRROR**: Hyperf 中间件标准：`Hyperf\HttpServer\Contract\MiddlewareInterface`
- **IMPORTS**: `Psr\Http\Message\ServerRequestInterface`, `Psr\Http\Server\RequestHandlerInterface`
- **GOTCHA**: 必须在 `config/autoload/middlewares.php` 的 `http` 数组中注册
- **VALIDATE**: 无 token 访问 `/api/auth/me` 返回 401

### Task 7: 更新配置 — 路由、中间件、异常处理
- **ACTION**: 将所有新组件注册到框架
- **IMPLEMENT**:
  - `routes.php`: 添加 `Router::addGroup('/api/auth/', ...)` 和 `/api/ai/` 路由组
  - `middlewares.php`: 注册 `JwtAuthMiddleware::class`
  - `exceptions.php`: 注册 JWT 异常处理器 `AuthExceptionHandler::class`
- **MIRROR**: 使用现有配置文件的数组格式
- **IMPORTS**: 各中间件/处理器类的完全限定名
- **GOTCHA**: 异常处理器注册顺序：`HttpExceptionHandler` → `AuthExceptionHandler` → `AppExceptionHandler`
- **VALIDATE**: 路由列表 `php bin/hyperf.php describe:routes` 显示新路由

### Task 8: 创建模型 Provider 策略接口
- **ACTION**: 定义 `ModelProviderInterface` 契约
- **IMPLEMENT**:
  ```php
  interface ModelProviderInterface {
      public function chatStream(string $prompt, array $messages, array $options = []): \Generator;
      public function chat(string $prompt, array $messages, array $options = []): string;
      public function getName(): string;
      public function getModels(): array;
  }
  ```
- **MIRROR**: 命名空间 `App\Contract`，接口名以 `Interface` 结尾
- **IMPORTS**: 仅 PHP 内置类型
- **GOTCHA**: `chatStream` 返回 Generator，用于 SSE 流式输出
- **VALIDATE**: 接口文件语法检查通过

### Task 9: 创建 DashScopeProvider
- **ACTION**: 实现 DashScope 通义千问 SSE 流式调用
- **IMPLEMENT**:
  - 构造函数接收 `apiKey` + `baseUrl` 配置
  - `chat()`: 使用 Guzzle 发送 POST 到 `{baseUrl}/services/aigc/multimodal-generation/generation`，非流式
  - `chatStream()`: 流式请求，设置 `X-DashScope-SSE: enable` header，`stream=>true`, `incremental_output=>true`
  - 使用 Guzzle `stream=>true` 选项，逐行解析 SSE data
  - `getModels()`: 返回可用模型列表 `['qwen3-max', 'qwen3-plus', 'qwen3-flash']`
- **MIRROR**: 使用 Constructor DI + env 配置模式；遵循 PSR-18 HTTP 客户端
- **IMPORTS**: `GuzzleHttp\Client`, `Hyperf\Guzzle\ClientFactory`
- **GOTCHA**: 
  - DashScope SSE 每行格式: `data: {"output":{"choices":[{"message":{"content":[{"text":"..."}]}}]}}`
  - 结束信号: `data: [DONE]`
  - 必须设置 `X-DashScope-SSE: enable` header
  - `incremental_output: true` 获取增量输出
- **VALIDATE**: 在 tinker 或测试中调用 `chatStream()` 验证流式输出

### Task 10: 创建 OpenAICompatibleProvider（占位）
- **ACTION**: 创建 OpenAI 兼容接口的占位实现
- **IMPLEMENT**:
  - 实现 `ModelProviderInterface`
  - `chat()`: 暂抛出 `\RuntimeException('Not implemented')`
  - `chatStream()`: 暂抛出异常
  - 保留完整接口以供 Phase 3+ 使用
- **MIRROR**: 与 DashScopeProvider 相同的接口实现模式
- **IMPORTS**: `GuzzleHttp\Client`
- **GOTCHA**: 预留但不可用，需在前端或配置中隐藏此选项
- **VALIDATE**: 语法检查通过

### Task 11: 创建 ModelProviderService
- **ACTION**: Provider 工厂与调度服务
- **IMPLEMENT**:
  - 构造函数读取 `config/autoload/model.php` 中的 provider 配置
  - `driver(string $name = null): ModelProviderInterface` — 返回指定或默认 Provider
  - `getAvailableProviders(): array` — 返回可用 Provider 列表（仅已实现的）
- **MIRROR**: 使用 Hyperf DI 容器管理 Provider 实例
- **IMPORTS**: `App\Contract\ModelProviderInterface`, `Hyperf\Contract\ConfigInterface`
- **GOTCHA**: 通过配置控制默认 Provider，方便后续切换
- **VALIDATE**: `$service->driver()` 返回 DashScopeProvider 实例

### Task 12: 创建 AI Chat Controller (SSE)
- **ACTION**: 实现 SSE 流式聊天端点
- **IMPLEMENT**:
  - `POST /api/ai/chat`: 接收 `{prompt, messages, model?}`
  - 使用 `Hyperf\Engine\Http\EventStream` 创建 SSE 连接
  - 遍历 `ModelProviderService::driver()->chatStream()` 的 Generator
  - 每个 chunk 通过 `$eventStream->write("data: ...\n\n")` 推送
  - 检查 `$eventStream->write()` 返回值判断客户端是否断开
- **MIRROR**: Controller 扩展 `AbstractController`；使用 EventStream API
- **IMPORTS**: `Hyperf\Engine\Http\EventStream`, `App\Service\ModelProviderService`
- **GOTCHA**:
  - 需要设置 `Content-Type: text/event-stream` 和 `Cache-Control: no-cache`
  - `$eventStream->write()` 返回 false 时立即停止推送
  - 需捕获异常并发送 `data: {"error":"..."}\n\n` 事件
- **VALIDATE**: 通过 curl 或前端 EventSource 接收流式输出

### Task 13: 创建模型 Provider 配置
- **ACTION**: 创建 `config/autoload/model.php`
- **IMPLEMENT**:
  ```php
  return [
      'default' => env('AI_PROVIDER', 'dashscope'),
      'providers' => [
          'dashscope' => [
              'driver' => App\Service\Provider\DashScopeProvider::class,
              'api_key' => env('DASHSCOPE_API_KEY'),
              'base_url' => env('DASHSCOPE_BASE_URL', 'https://dashscope-intl.aliyuncs.com/api/v1'),
              'default_model' => env('DASHSCOPE_MODEL', 'qwen3-plus'),
          ],
          'openai' => [
              'driver' => App\Service\Provider\OpenAICompatibleProvider::class,
              'api_key' => env('OPENAI_API_KEY'),
              'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
          ],
      ],
  ];
  ```
- **MIRROR**: 遵循 `databases.php` 的配置结构
- **IMPORTS**: 无
- **GOTCHA**: 环境变量名与 `.env` 一致
- **VALIDATE**: `config('model.default')` 返回值正确

### Task 14: 创建前端登录/注册页面
- **ACTION**: 实现登录和注册的前端页面
- **IMPLEMENT**:
  - `Login.vue`: Ant Design Form（邮箱+密码+登录按钮），调用 auth API，存储 token 到 Pinia
  - `Register.vue`: Ant Design Form（用户名+邮箱+密码+确认密码+注册按钮）
  - `auth.ts`: API 函数 `login()`, `register()`, `refreshToken()`, `getMe()`
  - 更新路由：添加 `/login` 和 `/register` 路由
  - 路由守卫：未登录重定向到 `/login`
- **MIRROR**: 使用 Ant Design Vue 组件模式；Pinia store 使用 `useAuthStore`
- **IMPORTS**: `ant-design-vue`, `axios`, `pinia`, `vue-router`
- **GOTCHA**: 
  - 登录成功后将 token 写入 `localStorage` 和 Pinia
  - `api/client.ts` 的 axios 拦截器已自动附加 Bearer token
  - 路由守卫使用 `router.beforeEach`
- **VALIDATE**: 浏览器访问 `/login`，填写表单，登录成功跳转到首页

### Task 15: 集成验证 — 端到端测试
- **ACTION**: 在 Docker 环境中验证完整链路
- **IMPLEMENT**:
  - 启动 Docker Compose 全栈环境
  - curl 测试: 注册 → 登录 → 获取 token → 访问受保护端点 → SSE 流式
  - 浏览器测试: 登录页面 → SSE 流式响应
- **MIRROR**: 遵循 Phase 1 集成验证模式
- **IMPORTS**: N/A
- **GOTCHA**: 确保 DashScope API Key 已配置
- **VALIDATE**: 完整链路无报错

---

## Testing Strategy

### Unit Tests

| Test | Input | Expected Output |
|---|---|---|
| AuthService::register | valid username/email/password | User created, password hashed |
| AuthService::register | duplicate email | throws BusinessException |
| AuthService::login | valid credentials | returns token array |
| AuthService::login | wrong password | throws BusinessException |
| DashScopeProvider::chatStream | simple prompt | Generator yields text chunks |
| JwtAuthMiddleware | valid token | passes to next handler |
| JwtAuthMiddleware | no token | returns 401 JSON |
| JwtAuthMiddleware | expired token | returns 401 JSON |

### Edge Cases Checklist
- [ ] 空用户名/密码注册 → 422 验证错误
- [ ] 超长用户名 → 422 验证错误
- [ ] 无效邮箱格式 → 422 验证错误
- [ ] 无效 JWT token → 401
- [ ] 过期 JWT token → 401
- [ ] DashScope API 超时 → SSE error 事件
- [ ] SSE 客户端断连 → Provider 停止推送
- [ ] 未配置 DASHSCOPE_API_KEY → 明确错误提示

---

## Validation Commands

### Static Analysis (PHP)
```bash
php -l app/Service/*.php app/Controller/*.php app/Middleware/*.php app/Contract/*.php
```
EXPECT: No syntax errors

### Static Analysis (TypeScript)
```bash
cd frontend && ./node_modules/.bin/vue-tsc --noEmit
```
EXPECT: Zero type errors

### JWT Config
```bash
php bin/hyperf.php describe:routes
```
EXPECT: 显示 auth 和 ai 路由

### SSE Test
```bash
curl -N -X POST http://localhost:9501/api/ai/chat \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"prompt":"Hello","messages":[]}'
```
EXPECT: 逐行流式输出 data: {...}

### Database Validation
```bash
docker exec nlp-mysql mysql -u root -pnlp_root_2024 nlp_content -e "SELECT id,username,email FROM users LIMIT 5"
```
EXPECT: 注册用户记录存在

### Browser Validation
```bash
# Start frontend dev server
cd frontend && npm run dev
```
- [ ] 访问 `localhost:5173/login` — 登录表单
- [ ] 访问 `localhost:5173/register` — 注册表单
- [ ] 注册新用户 → 自动登录 → 跳转首页
- [ ] 未登录访问 `/workshop` → 重定向到 `/login`

---

## Acceptance Criteria
- [ ] 用户可注册（用户名+邮箱+密码）
- [ ] 用户可登录获取 JWT Token
- [ ] JWT Token 可刷新
- [ ] 受保护端点无 Token 返回 401
- [ ] `/api/ai/chat` 返回 SSE 流式响应
- [ ] DashScope Provider 实现完整（流式+非流式）
- [ ] OpenAI Compatible Provider 接口预留
- [ ] 前端登录/注册页面可用
- [ ] 路由守卫生效

## Completion Checklist
- [ ] Code follows established patterns (AbstractController, ApiResponse, etc.)
- [ ] Error handling returns JSON format matching AppExceptionHandler
- [ ] JWT middleware registered in middlewares.php
- [ ] ModelProviderInterface defines clear contract
- [ ] DashScopeProvider handles SSE parsing correctly
- [ ] Environment variables documented in .env.example
- [ ] No hardcoded API keys or secrets
- [ ] Frontend uses Pinia store for auth state
- [ ] Self-contained — no questions needed during implementation

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| l1n6yun/hyperf-jwt 与 Hyperf 3.1 不兼容 | L | H | 备选 qbhy/hyperf-auth 或手写 JWT |
| DashScope API 返回格式变化 | L | M | 解析时做好异常处理，fail gracefully |
| SSE 在 Swoole 下长连接断开 | M | M | write() 返回 false 检测，心跳保活 |
| 前端页面与后端 API 跨域 | L | L | Vite proxy 已配置 `/api` → `localhost:9501` |

## Notes

- JWT 库选择 `l1n6yun/hyperf-jwt` 而非 `qbhy/hyperf-auth`，因为前者更简洁且专注 JWT，后者 concept 更重（多 guard），Phase 2 只需 JWT
- 备选: 如果 `l1n6yun/hyperf-jwt` 有问题，可降级为手写 JWT（使用 `lcobucci/jwt` 原生包）
- DashScope SSE 响应格式与 OpenAI SSE 不同，需要独立解析逻辑
- 模型 Provider 的策略模式为 Phase 3 的多 Agent 编排奠定基础
- 前端 SSE 客户端使用 `api/client.ts` 中的 `createEventSource()` 工厂函数
