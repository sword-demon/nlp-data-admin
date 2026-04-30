# Plan: Phase 1 — 基础设施搭建

## Summary

搭建 AI 多智能体内容创作平台的完整开发环境。包含 Docker Compose 4 容器编排（MySQL 8.0 非 3306 / Redis 7.x 非 6379 / Backend Hyperf / Frontend Vite）、Hyperf 3.x 项目骨架与数据库连接、前端项目骨架、以及核心数据库表迁移。

## User Story

As a 开发者, I want 一键启动完整的开发环境, So that 后续所有 Phase 的开发工作有稳定的基础设施支撑。

## Problem → Solution

当前项目目录为空 → 拥有可通过 `docker compose up` 一键启动的 Docker 化开发环境，含 Hyperf 后端 + Vite 前端 + MySQL + Redis + 核心数据库表。

## Metadata

- **Complexity**: Medium
- **Source PRD**: `.claude/PRPs/prds/ai-multi-agent-content-platform.prd.md`
- **PRD Phase**: Phase 1 — 基础设施搭建
- **Estimated Files**: 20+

---

## UX Design

N/A — 本 Phase 为纯基础设施，无用户界面变更。

---

## Mandatory Reading

由于是全新项目，无现有代码库。以下为必须参考的外部资源：

| Priority | Resource | Why |
|---|---|---|
| P0 | Hyperf 官方文档 https://hyperf.wiki | 项目骨架、配置、数据库连接 |
| P0 | Hyperf Docker 镜像文档 | Swoole 扩展容器化 |
| P1 | Ant Design Vue 文档 https://antdv.com | 前端 UI 框架初始化 |
| P1 | Docker Compose 官方文档 | 编排语法、网络配置 |
| P2 | MySQL 8.0 官方文档 | 字符集、排序规则 |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| Hyperf 项目创建 | https://hyperf.wiki | `composer create-project hyperf/hyperf-skeleton` |
| Hyperf DB 配置 | https://hyperf.wiki/#/zh-cn/db/quick-start | `config/autoload/databases.php` |
| Hyperf Redis 配置 | https://hyperf.wiki/#/zh-cn/redis | `config/autoload/redis.php` |
| Ant Design Vue 初始化 | https://antdv.com/docs/vue/getting-started-cn | `npm create vue` + `npm i ant-design-vue` |
| Docker Compose | https://docs.docker.com/compose/ | services / networks / volumes / healthcheck |

---

## Conventions to Establish (本项目约定)

由于是新项目，以下约定需要在 Phase 1 中确立：

### PROJECT_STRUCTURE
```
/Volumes/MOVESPEED/ai-coding/nlp-data-admin/
├── docker/
│   ├── docker-compose.yml          # 4 容器编排
│   ├── mysql/
│   │   ├── conf.d/                 # MySQL 自定义配置
│   │   └── init/                   # 初始化 SQL
│   ├── redis/
│   │   └── redis.conf              # Redis 自定义配置
│   └── backend/
│       └── Dockerfile              # Backend 镜像
├── backend/                        # Hyperf 项目根目录
│   ├── app/
│   │   ├── Controller/             # 控制器层
│   │   ├── Service/                # 业务逻辑层
│   │   ├── Model/                  # 数据模型层
│   │   ├── Aspect/                 # AOP 切面
│   │   ├── Annotation/             # 自定义注解
│   │   └── Constants/              # 常量定义
│   ├── config/
│   │   ├── autoload/
│   │   │   ├── databases.php       # DB 连接配置
│   │   │   └── redis.php           # Redis 连接配置
│   │   └── config.php
│   ├── migrations/                 # 数据库迁移
│   ├── runtime/                    # 运行时文件
│   ├── vendor/                     # Composer 依赖
│   ├── .env                        # 环境变量
│   ├── .env.example                # 环境变量模板
│   └── composer.json
├── frontend/                       # Vite 前端项目
│   ├── src/
│   │   ├── api/                    # API 请求层
│   │   ├── components/             # 通用组件
│   │   ├── layouts/                # 布局组件
│   │   ├── pages/                  # 页面组件
│   │   ├── router/                 # 路由配置
│   │   ├── stores/                 # Pinia 状态
│   │   ├── utils/                  # 工具函数
│   │   └── App.vue
│   ├── vite.config.ts
│   ├── tsconfig.json
│   └── package.json
└── .gitignore
```

### NAMING_CONVENTION
```
PHP Class:      PascalCase    → TitleGeneratorAgent
PHP Method:     camelCase     → generateTitles()
PHP Config:     snake_case    → db_connections
DB Table:       snake_case    → user_quotas
DB Column:      snake_case    → created_at
Vue Component:  PascalCase    → CreationWorkshop.vue
TS Function:    camelCase     → fetchTitles()
TS File:        kebab-case    → api-client.ts
```

### ERROR_HANDLING
```php
// 统一异常处理：通过 Hyperf 异常处理器
// config/autoload/exceptions.php
// 业务异常使用 App\Exception\BusinessException
// HTTP 状态码 + 统一 JSON 响应格式
{
  "code": 0,           // 0=成功, 非0=失败
  "message": "success",
  "data": {}
}
```

### API_RESPONSE_FORMAT
```php
// 统一 JSON 响应封装
// App\Constants\Code::SUCCESS = 0
// App\Constants\Code::ERROR = 1
class ApiResponse {
    public static function success($data = [], string $message = 'success'): array
    public static function error(int $code, string $message, int $httpCode = 400): array
    public static function paginate($data, int $total, int $page, int $limit): array
}
```

---

## Files to Create

### Docker 环境

| File | Action | Justification |
|---|---|---|
| `docker/docker-compose.yml` | CREATE | 4 容器编排核心文件 |
| `docker/mysql/conf.d/custom.cnf` | CREATE | MySQL 字符集和性能配置 |
| `docker/mysql/init/01-init.sql` | CREATE | 初始数据库创建 |
| `docker/redis/redis.conf` | CREATE | Redis 自定义配置 |
| `docker/backend/Dockerfile` | CREATE | Hyperf Backend 镜像 |

### Backend

| File | Action | Justification |
|---|---|---|
| `backend/composer.json` | CREATE | Hyperf 项目骨架 |
| `backend/.env` | CREATE | 环境变量（数据库/Redis 连接） |
| `backend/.env.example` | CREATE | 环境变量模板 |
| `backend/config/autoload/databases.php` | CREATE | MySQL 连接配置 |
| `backend/config/autoload/redis.php` | CREATE | Redis 连接配置 |
| `backend/config/config.php` | CREATE | 应用基础配置 |
| `backend/config/autoload/exceptions.php` | CREATE | 全局异常处理器 |
| `backend/app/Constants/Code.php` | CREATE | 业务状态码常量 |
| `backend/app/Helpers/ApiResponse.php` | CREATE | 统一响应封装 |
| `backend/app/Model/User.php` | CREATE | 用户模型 |
| `backend/app/Model/Article.php` | CREATE | 文章模型 |
| `backend/migrations/2026_04_30_000001_create_users_table.php` | CREATE | 用户表迁移 |
| `backend/migrations/2026_04_30_000002_create_articles_table.php` | CREATE | 文章表迁移 |
| `backend/migrations/2026_04_30_000003_create_agent_logs_table.php` | CREATE | Agent 日志表迁移 |

### Frontend

| File | Action | Justification |
|---|---|---|
| `frontend/package.json` | CREATE | 项目依赖清单 |
| `frontend/vite.config.ts` | CREATE | Vite 构建配置 |
| `frontend/tsconfig.json` | CREATE | TypeScript 配置 |
| `frontend/index.html` | CREATE | 入口 HTML |
| `frontend/src/main.ts` | CREATE | Vue 应用入口 |
| `frontend/src/App.vue` | CREATE | 根组件 |
| `frontend/src/router/index.ts` | CREATE | 路由配置 |
| `frontend/src/api/client.ts` | CREATE | Axios 封装 + EventSource |
| `frontend/src/stores/auth.ts` | CREATE | 认证状态管理 |
| `frontend/src/layouts/DefaultLayout.vue` | CREATE | 默认布局 |
| `frontend/src/pages/Home.vue` | CREATE | 首页占位 |

### 根目录

| File | Action | Justification |
|---|---|---|
| `.gitignore` | CREATE | Git 忽略规则 |

---

## NOT Building

- 任何业务功能（认证、创作、支付等）— 这些属于后续 Phase
- 完整的前端页面 — Phase 1 仅骨架
- 生产级 Nginx 反向代理 — Phase 8
- CI/CD 配置 — 不在当前范围内

---

## Step-by-Step Tasks

### Task 1: 创建 Docker Compose 编排
- **ACTION**: 编写 `docker/docker-compose.yml`，定义 4 个 service + 自定义网络
- **IMPLEMENT**:
  - Network: `app-network` (bridge)
  - MySQL service: `mysql:8.0`, port `33060:3306`, volume `mysql_data:/var/lib/mysql`, custom cnf mount, init scripts mount, healthcheck
  - Redis service: `redis:7-alpine`, port `16379:6379`, custom conf mount, healthcheck
  - Backend service: 基于 `hyperf/hyperf:8.2-alpine-v3.18-swoole` 的自定义 Dockerfile, port `9501:9501`, 挂载 `../backend:/opt/www`, depends_on mysql + redis
  - Frontend service: `node:20-alpine`, port `5173:5173`, 挂载 `../frontend:/app`, command: `npm run dev -- --host`
  - 关键：MySQL 避开 3306，Redis 避开 6379
- **MIRROR**: N/A — 新项目
- **IMPORTS**: N/A
- **GOTCHA**: 确保 `depends_on` + `healthcheck` 条件等待，避免后端启动时数据库未就绪
- **VALIDATE**: `docker compose -f docker/docker-compose.yml config` 语法检查通过

### Task 2: MySQL 配置与初始化
- **ACTION**: 创建 MySQL 自定义配置和初始化 SQL
- **IMPLEMENT**:
  - `docker/mysql/conf.d/custom.cnf`: 设置 `character-set-server=utf8mb4`, `collation-server=utf8mb4_unicode_ci`, `default-time-zone=+08:00`
  - `docker/mysql/init/01-init.sql`: `CREATE DATABASE IF NOT EXISTS nlp_content DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
- **MIRROR**: N/A
- **IMPORTS**: N/A
- **GOTCHA**: MySQL 8.0 默认 `caching_sha2_password` 认证插件，PHP PDO 需确保支持
- **VALIDATE**: 容器启动后 `docker exec -it nlp-mysql mysql -u root -p -e "SHOW DATABASES;"` 看到 nlp_content 库

### Task 3: Redis 配置
- **ACTION**: 创建 Redis 自定义配置
- **IMPLEMENT**:
  - `docker/redis/redis.conf`: `port 6379`, `requirepass` (通过环境变量), `maxmemory 256mb`, `maxmemory-policy allkeys-lru`
- **MIRROR**: N/A
- **IMPORTS**: N/A
- **GOTCHA**: Redis 密码需通过环境变量注入，确保 docker-compose.yml 中 `REDIS_PASSWORD` 一致
- **VALIDATE**: `docker exec -it nlp-redis redis-cli -a $PASSWORD ping` 返回 PONG

### Task 4: Backend Dockerfile
- **ACTION**: 创建 Hyperf 后端容器镜像
- **IMPLEMENT**: 基于 `hyperf/hyperf:8.2-alpine-v3.18-swoole`，安装 Composer 依赖，暴露 9501 端口，CMD 启动 Hyperf 服务
- **MIRROR**: N/A
- **IMPORTS**: N/A
- **GOTCHA**: Swoole 需要 `--enable-swoole` 编译选项，基础镜像已包含；确保 WORKDIR 权限正确
- **VALIDATE**: `docker build -t nlp-backend -f docker/backend/Dockerfile .` 构建成功

### Task 5: Hyperf 项目骨架
- **ACTION**: 使用 Composer 创建 Hyperf 项目
- **IMPLEMENT**:
  - `composer create-project hyperf/hyperf-skeleton:3.1.* backend`
  - 安装核心依赖：`hyperf/database`, `hyperf/redis`, `hyperf/command`, `hyperf/devtool`
  - 配置 `composer.json` 的 autoload PSR-4: `App\\` → `app/`
- **MIRROR**: N/A
- **IMPORTS**: N/A
- **GOTCHA**: Hyperf 3.1 要求 PHP 8.1+，确认 Swoole 5.0+；`config/config.php` 中 `scan_cacheable` 设 false 方便开发
- **VALIDATE**: `cd backend && php bin/hyperf.php start` 启动成功，访问 9501 显示 Hyperf 欢迎页

### Task 6: 数据库连接配置
- **ACTION**: 配置 MySQL 和 Redis 连接
- **IMPLEMENT**:
  - `backend/.env`: `DB_HOST=nlp-mysql`, `DB_PORT=3306`（容器内端口）, `DB_DATABASE=nlp_content`, `DB_USERNAME=root`, `DB_PASSWORD=...`, `REDIS_HOST=nlp-redis`, `REDIS_PORT=6379`（容器内端口）, `REDIS_PASSWORD=...`
  - `backend/config/autoload/databases.php`: 读取 env 配置 `default` 连接，`driver=mysql`, `charset=utf8mb4`, `collation=utf8mb4_unicode_ci`
  - `backend/config/autoload/redis.php`: 读取 env 配置 `default` 连接
- **MIRROR**: N/A
- **IMPORTS**: `Hyperf\DbConnection\Db`, `Hyperf\Redis\Redis`
- **GOTCHA**: Docker Compose 中 service name 即 hostname，容器间通信用容器名而非 localhost
- **VALIDATE**: `php bin/hyperf.php` 进入 tinker，执行 `Db::select('SELECT 1')` 和 `Redis::ping()` 均成功

### Task 7: 统一 API 响应 + 异常处理
- **ACTION**: 创建响应封装和全局异常处理器
- **IMPLEMENT**:
  - `backend/app/Constants/Code.php`: `SUCCESS = 0`, `ERROR = 1`, `UNAUTHORIZED = 401`, `FORBIDDEN = 403`, `NOT_FOUND = 404`, `VALIDATION_ERROR = 422`
  - `backend/app/Helpers/ApiResponse.php`: `success()`, `error()`, `paginate()` 三个静态方法
  - `backend/config/autoload/exceptions.php`: 配置 `App\Exception\Handler\BusinessExceptionHandler` 处理器，返回统一 JSON 格式
- **MIRROR**: N/A
- **IMPORTS**: `Hyperf\Contract\StdoutLoggerInterface`, `Hyperf\HttpServer\Contract\ResponseInterface`
- **GOTCHA**: 异常处理器需区分 debug 模式（返回详细错误）和生产模式（返回通用错误信息）
- **VALIDATE**: 访问不存在的路由返回 JSON: `{"code":404, "message":"Not Found", "data":{}}`

### Task 8: 数据库迁移 — 核心表
- **ACTION**: 创建 users / articles / agent_logs 三张核心表迁移
- **IMPLEMENT**:
  - `users`: id, username, email, password_hash, avatar, role(enum), vip_level(enum), vip_expired_at, quota_total, quota_used, created_at, updated_at
  - `articles`: id, user_id(FK), title, topic, style, outline(JSON), content(TEXT), images(JSON), status(enum), word_count, created_at, updated_at
  - `agent_logs`: id, user_id(FK), article_id(FK nullable), agent_name, input_summary, output_summary, duration_ms, status(enum), error_message, created_at
- **MIRROR**: N/A
- **IMPORTS**: `Hyperf\Database\Migrations\Migration`, `Hyperf\Database\Schema\Schema`
- **GOTCHA**: 使用 `hyperf/database` 迁移，需运行 `php bin/hyperf.php migrate` 而不是独立的 migrate 命令；迁移文件需继承 `Hyperf\Database\Migrations\Migration`
- **VALIDATE**: `php bin/hyperf.php migrate` 执行后数据库中可见 3 张表 + migrations 记录表

### Task 9: Eloquent Model 基础
- **ACTION**: 创建 User / Article / AgentLog 三个 Model
- **IMPLEMENT**:
  - `User`: 关联 `articles()` hasMany, 关联 `agentLogs()` hasMany
  - `Article`: 关联 `user()` belongsTo, 关联 `agentLogs()` hasMany
  - `AgentLog`: 关联 `user()` belongsTo, 关联 `article()` belongsTo
- **MIRROR**: N/A
- **IMPORTS**: `Hyperf\Database\Model\Model`
- **GOTCHA**: Hyperf Model 需继承 `Hyperf\DbConnection\Model\Model` 而非 Laravel 的 `Illuminate\Database\Eloquent\Model`；但新版 hyperf/database 可能已统一；需确认实际依赖版本
- **VALIDATE**: tinker 中能正常 `User::find(1)` 不报错

### Task 10: 前端项目骨架
- **ACTION**: 使用 npm create vue 初始化前端项目
- **IMPLEMENT**:
  - `npm create vue@latest frontend` 选择 TS + Pinia + Vue Router + ESLint
  - 安装依赖：`ant-design-vue`, `axios`, `markdown-it`, `highlight.js`, `@vueuse/core`
  - `vite.config.ts`: 配置 proxy `/api` → `http://nlp-backend:9501`，但开发环境前端在宿主机运行，proxy 应指向 `http://localhost:9501`
  - `src/main.ts`: 注册 Ant Design Vue, Router, Pinia
  - `src/router/index.ts`: 路由 `/` → Home, `/workshop` → 占位, `/dashboard` → 占位
  - `src/api/client.ts`: Axios 实例（baseURL, 拦截器, JWT 注入）+ EventSource 工厂函数
  - `src/stores/auth.ts`: token/user 状态管理 (Pinia)
  - `src/layouts/DefaultLayout.vue`: Ant Design Layout (Sider + Header + Content + Footer)
  - `src/pages/Home.vue`: 简单欢迎页
- **MIRROR**: N/A
- **IMPORTS**: `ant-design-vue`, `axios`, `pinia`, `vue-router`
- **GOTCHA**: Docker 中 `vite dev —host` 需暴露到 0.0.0.0；Vite proxy 在 Docker 内指向 backend service name，在宿主机开发时指向 localhost
- **VALIDATE**: `npm run dev` 后访问 localhost:5173 看到 Ant Design 布局的首页

### Task 11: .gitignore + 根目录文件
- **ACTION**: 创建 .gitignore 排除不纳入版本控制的文件
- **IMPLEMENT**:
  - `backend/runtime/`, `backend/vendor/`, `backend/.env`
  - `frontend/node_modules/`, `frontend/dist/`
  - `docker/mysql/data/`, `.DS_Store`, `.idea/`, `*.log`
- **MIRROR**: N/A
- **IMPORTS**: N/A
- **GOTCHA**: `.env` 排除但保留 `.env.example` 作为模板
- **VALIDATE**: `git status` 中不出现 vendor/node_modules 等目录

### Task 12: 集成验证
- **ACTION**: 启动完整 Docker 环境并验证连通性
- **IMPLEMENT**:
  - `docker compose -f docker/docker-compose.yml up -d`
  - 等待所有容器 healthy
  - `curl http://localhost:9501` 返回 Hyperf 响应
  - `curl http://localhost:5173` 返回前端页面
  - Backend 容器内 `php bin/hyperf.php migrate` 创建表成功
- **MIRROR**: N/A
- **IMPORTS**: N/A
- **GOTCHA**: 首次启动需 `composer install` + `npm install`，确保 Dockerfile 或 entrypoint 包含依赖安装步骤
- **VALIDATE**: 4 个容器全部 running，数据库表存在，前后端可访问

---

## Testing Strategy

### Infrastructure Tests

| Test | Input | Expected Output |
|---|---|---|
| MySQL 连接 | Backend 调用 `Db::select('SELECT 1')` | 返回 `[{"1": 1}]` |
| Redis 连接 | Backend 调用 `Redis::ping()` | 返回 `true` 或 `"+PONG"` |
| API 响应 | `curl http://localhost:9501/` | 返回 JSON |
| 前端页面 | 浏览器访问 `localhost:5173` | 显示 Ant Design 布局页面 |
| 数据库迁移 | `php bin/hyperf.php migrate` | 3 张业务表创建 |
| 容器健康检查 | `docker compose ps` | 4 容器 status=healthy |

### Edge Cases Checklist
- [ ] MySQL 端口 33060 不与本地 3306 冲突
- [ ] Redis 端口 16379 不与本地 6379 冲突
- [ ] 容器重启后数据持久化（volume）
- [ ] `.env` 缺失时给出明确错误提示
- [ ] Backend 容器在 MySQL 未就绪时能等待（depends_on + healthcheck）

---

## Validation Commands

### Docker Compose
```bash
docker compose -f docker/docker-compose.yml config
```
EXPECT: YAML 语法验证通过

### Backend Start
```bash
cd backend && php bin/hyperf.php start
```
EXPECT: 服务启动，监听 0.0.0.0:9501

### Database Migration
```bash
cd backend && php bin/hyperf.php migrate
```
EXPECT: 3 张表创建成功

### Frontend Dev
```bash
cd frontend && npm run dev
```
EXPECT: Vite dev server 启动在 localhost:5173

### Integration
```bash
docker compose -f docker/docker-compose.yml up -d
docker compose -f docker/docker-compose.yml ps
```
EXPECT: 4 个容器均为 running/healthy

---

## Acceptance Criteria
- [ ] `docker compose up -d` 一键启动全部 4 个容器
- [ ] MySQL 服务在 33060 端口可访问
- [ ] Redis 服务在 16379 端口可访问
- [ ] Backend Hyperf 服务在 9501 端口可访问
- [ ] Frontend Vite dev server 在 5173 端口可访问
- [ ] Backend 容器可连接 MySQL 和 Redis
- [ ] 数据库 `nlp_content` 中 users / articles / agent_logs 3 张表存在
- [ ] API 返回统一 JSON 格式 `{"code":0, "message":"success", "data":{}}`
- [ ] .gitignore 配置正确

## Completion Checklist
- [ ] Docker Compose 编排文件完整
- [ ] Hyperf 项目骨架可运行
- [ ] 数据库迁移可执行
- [ ] 前端项目骨架可运行
- [ ] 环境变量模板完整（.env.example）
- [ ] 统一 API 响应格式已确立
- [ ] 异常处理器已配置
- [ ] 命名约定已文档化（在代码结构中体现）

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Swoole 扩展版本与 Hyperf 3.1 不兼容 | L | H | 使用官方 hyperf/hyperf 镜像 |
| Docker 网络配置导致容器间无法通信 | L | M | 使用自定义 bridge 网络 + service name 通信 |
| Vite HMR 在 Docker 中不工作 | M | L | 配置 `watch` 使用 polling 或直接在宿主机开发 |
| macOS 上 Docker 性能问题 | M | L | 使用 `cached` volume 挂载选项 |

## Notes

- 本项目为全新搭建，无现有代码库约束
- Phase 1 确立的所有约定（命名、目录结构、API 格式）将在后续 Phase 中严格执行
- MySQL 密码、Redis 密码等敏感信息通过 `.env` 管理，不硬编码
- Backend 使用 `hyperf/hyperf:8.2-alpine-v3.18-swoole` 官方镜像以避开 Swoole 编译问题
- 前端开发建议在宿主机运行 Vite dev server（性能更好），仅在生产构建时使用 Docker
