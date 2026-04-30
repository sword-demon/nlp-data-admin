# Plan: Phase 8 — 部署与文档

## Summary

完成项目的生产级部署配置和交付文档：Docker Compose 生产环境配置（Nginx 反向代理 + 多阶段构建优化）、环境变量模板完善、Nginx SSE 代理配置、数据库迁移自动化、API 文档生成、项目 README 和部署指南。实现一键 `docker compose up -d` 启动完整服务。

## User Story

As a 部署运维人员/开发者, I want 通过一条命令启动完整的 AI 内容创作平台, So that 我可以快速在服务器上部署并运行系统，无需手动配置环境。

## Problem → Solution

开发环境 Docker Compose 配置不适用于生产（无 Nginx、无 HTTPS、无健康检查优化、无日志管理）→ 完善 Docker Compose 编排、添加 Nginx 反向代理（支持 SSE）、优化镜像构建、编写完整部署文档和 API 文档。

## Metadata

- **Complexity**: Medium
- **Source PRD**: `.claude/PRPs/prds/ai-multi-agent-content-platform.prd.md`
- **PRD Phase**: Phase 8 — 部署与文档
- **Estimated Files**: 12+

---

## UX Design

N/A — 本 Phase 是纯基础设施和文档工作，无用户界面变更。

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `docker/docker-compose.yml` | all | 当前 Docker Compose 配置 |
| P0 | `docker/backend/Dockerfile` | all | 后端镜像构建 |
| P0 | `docker/backend/entrypoint.sh` | all | 启动脚本 |
| P0 | `docker/mysql/conf.d/custom.cnf` | all | MySQL 配置 |
| P0 | `docker/redis/redis.conf` | all | Redis 配置 |
| P1 | `backend/.env.example` | all | 环境变量模板 |
| P1 | `frontend/vite.config.ts` | all | 前端构建配置 |
| P2 | `backend/config/autoload/server.php` | all | Hyperf 服务器配置 |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| Nginx SSE 代理 | Nginx docs | `proxy_buffering off` + `proxy_cache off` + `X-Accel-Buffering: no` |
| Docker Compose production | Docker docs | 多阶段构建、健康检查、重启策略、资源限制 |
| Nginx 反向代理 | Nginx docs | `proxy_pass` + `proxy_set_header` + WebSocket 升级 |

---

## Patterns to Mirror

### DOCKER_COMPOSE_PATTERN
```yaml
# SOURCE: docker/docker-compose.yml
services:
  service-name:
    build:
      context: ..
      dockerfile: docker/service/Dockerfile
    environment:
      - KEY=${KEY}
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9501"]
      interval: 30s
      timeout: 10s
      retries: 3
    restart: unless-stopped
```

### ENTRYPOINT_PATTERN
```bash
# SOURCE: docker/backend/entrypoint.sh
#!/bin/sh
set -e
composer install --no-dev --optimize-autoloader --ignore-platform-reqs
php bin/hyperf.php migrate
php bin/hyperf.php start
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `docker/docker-compose.yml` | UPDATE | 生产环境配置优化 |
| `docker/docker-compose.prod.yml` | CREATE | 生产环境覆盖配置 |
| `docker/backend/Dockerfile` | UPDATE | 多阶段构建优化 |
| `docker/nginx/nginx.conf` | CREATE | Nginx 主配置 |
| `docker/nginx/conf.d/default.conf` | CREATE | 站点配置（反代 + SSE） |
| `docker/nginx/Dockerfile` | CREATE | Nginx 镜像 |
| `docker/backend/entrypoint.sh` | UPDATE | 添加 wait-for + 生产优化 |
| `backend/.env.example` | UPDATE | 完善 Phase 2-6 所有环境变量 |
| `backend/.env` | UPDATE | 同步模板 |
| `docs/api.md` | CREATE | API 接口文档 |
| `docs/deployment.md` | CREATE | 部署指南 |
| `README.md` | CREATE | 项目 README |
| `docker/mysql/init/02-seed-vip-plans.sql` | CREATE | VIP 套餐初始数据 |

## NOT Building

- CI/CD Pipeline（GitHub Actions）— 后续配置
- Kubernetes 编排 — Docker Compose 满足中小规模
- 自动化测试部署流程 — 手动部署为主
- 日志收集系统（ELK）— Docker 日志 + 文件日志
- 监控告警（Prometheus/Grafana）— 后续版本
- SSL 证书自动申请（Let's Encrypt）— 文档说明手动配置

---

## Step-by-Step Tasks

### Task 1: 优化后端 Dockerfile（多阶段构建）
- **ACTION**: 使用多阶段构建减小镜像体积
- **IMPLEMENT**:
  - Stage 1 (builder): `FROM composer:2 AS builder`，`composer install --no-dev --optimize-autoloader`
  - Stage 2 (runtime): `FROM hyperf/hyperf:8.2-alpine-v3.22-swoole`，COPY vendor from builder
  - 最终镜像只包含运行时依赖，不含 dev 依赖
  - 添加 `.dockerignore` 排除 node_modules/.git 等
- **MIRROR**: Docker 多阶段构建最佳实践
- **IMPORTS**: N/A
- **GOTCHA**: 需要在 builder 阶段 `--ignore-platform-reqs`（alpine 环境差异）
- **VALIDATE**: `docker build` 成功，镜像体积 < 300MB

### Task 2: 创建前端 Dockerfile
- **ACTION**: 前端多阶段构建（build + Nginx 服务）
- **IMPLEMENT**:
  - Stage 1 (build): `FROM node:20-alpine AS builder`，`npm ci && npm run build`
  - Stage 2 (serve): 不单独构建，直接由 Task 3 的 Nginx 容器服务前端静态文件
  - 前端构建产物 `dist/` 由 CI 或手动构建后 COPY 到 Nginx 容器
- **MIRROR**: 前端项目多阶段构建标准模式
- **IMPORTS**: N/A
- **GOTCHA**: 前端环境变量 `VITE_BACKEND_URL` 在构建时注入，需在 build 阶段设置
- **VALIDATE**: `docker build` 成功

### Task 3: 创建 Nginx 配置（反代 + SSE 支持）
- **ACTION**: Nginx 反向代理配置，支持后端 API + SSE + 前端静态文件
- **IMPLEMENT**:
  - `nginx/nginx.conf`: 全局配置（worker_connections, gzip, keepalive）
  - `nginx/conf.d/default.conf`:
    - `/` → 前端静态文件（`/usr/share/nginx/html`）
    - `/api` → `proxy_pass http://backend:9501`（后端 API）
    - `/api/workshop/*/sse` → SSE 专用 location:
      ```nginx
      location ~ /api/workshop/.*/sse {
          proxy_pass http://backend:9501;
          proxy_http_version 1.1;
          proxy_set_header Connection '';
          proxy_buffering off;
          proxy_cache off;
          proxy_read_timeout 300s;
          add_header X-Accel-Buffering no;
      }
      ```
    - SPA 路由 fallback: `try_files $uri $uri/ /index.html`
    - Gzip 压缩启用
    - 静态资源缓存（JS/CSS/Image 30 天）
- **MIRROR**: Nginx SSE 代理标准配置
- **IMPORTS**: N/A
- **GOTCHA**:
  - `proxy_buffering off` 是 SSE 正常工作的关键
  - `proxy_read_timeout` 设为 300s（SSE 长连接）
  - `X-Accel-Buffering: no` 告诉 Nginx 不缓冲响应
  - SPA fallback 确保前端路由正常（`try_files $uri /index.html`）
- **VALIDATE**: Nginx 配置语法检查: `nginx -t`

### Task 4: 创建 Nginx Dockerfile
- **ACTION**: Nginx 容器镜像
- **IMPLEMENT**:
  ```dockerfile
  FROM nginx:1.25-alpine
  COPY nginx/nginx.conf /etc/nginx/nginx.conf
  COPY nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf
  COPY ../frontend/dist /usr/share/nginx/html
  EXPOSE 80
  CMD ["nginx", "-g", "daemon off;"]
  ```
- **MIRROR**: 标准 Nginx Docker 镜像
- **IMPORTS**: N/A
- **GOTCHA**: 前端 dist/ 需要先构建才能 COPY
- **VALIDATE**: `docker build` 成功

### Task 5: 更新 Docker Compose 配置
- **ACTION**: 完善 docker-compose.yml 并添加生产环境覆盖文件
- **IMPLEMENT**:
  - `docker-compose.yml` (开发 + 通用):
    - 添加 nginx 服务
    - 所有服务添加 `restart: unless-stopped`
    - 统一 `app-network` 网络
    - 后端暴露 9501 仅内部（nginx 代理）
    - 前端不再单独暴露 5173（nginx 代理）
    - 添加依赖关系: nginx depends_on backend
  - `docker-compose.prod.yml` (生产覆盖):
    - 资源限制: memory/cpu limits
    - 日志驱动: json-file + 大小限制
    - 健康检查优化
    - 环境变量从 .env 文件读取
    - 不映射 MySQL/Redis 到宿主机（仅内部网络）
- **MIRROR**: Docker Compose 生产环境最佳实践
- **IMPORTS**: N/A
- **GOTCHA**:
  - `docker-compose.prod.yml` 使用 `extends` 或 `override` 模式
  - MySQL 数据使用 named volume 持久化
  - 生产环境不暴露 MySQL/Redis 端口到宿主机
- **VALIDATE**: `docker compose -f docker-compose.yml -f docker-compose.prod.yml config` 验证合并配置

### Task 6: 优化后端 entrypoint.sh
- **ACTION**: 添加数据库就绪检查和生产优化
- **IMPLEMENT**:
  - 添加 wait-for-mysql 脚本（轮询 MySQL 连接直到可用）
  - 添加 wait-for-redis 脚本
  - `php bin/hyperf.php migrate --force` 强制运行迁移
  - 添加启动信息输出（版本、环境、监听端口）
  - 信号处理: 优雅关闭（SIGTERM → stop Hyperf）
- **MIRROR**: Docker entrypoint 最佳实践
- **IMPORTS**: N/A
- **GOTCHA**: `migrate --force` 避免生产环境交互确认
- **VALIDATE**: 容器启动时自动等待数据库并运行迁移

### Task 7: 完善环境变量模板
- **ACTION**: 更新 `.env.example` 包含 Phase 2-6 所有配置
- **IMPLEMENT**:
  ```env
  # === 应用 ===
  APP_ENV=production
  APP_DEBUG=false

  # === 数据库 ===
  DB_HOST=nlp-mysql
  DB_PORT=3306
  DB_DATABASE=nlp_content
  DB_USERNAME=nlp_user
  DB_PASSWORD=
  DB_ROOT_PASSWORD=

  # === Redis ===
  REDIS_HOST=nlp-redis
  REDIS_PORT=6379
  REDIS_PASSWORD=

  # === JWT ===
  JWT_SECRET=
  JWT_TTL=86400

  # === AI 模型 ===
  AI_PROVIDER=dashscope
  DASHSCOPE_API_KEY=
  DASHSCOPE_BASE_URL=https://dashscope-intl.aliyuncs.com/api/v1
  DASHSCOPE_MODEL=qwen3-plus

  # === 配图 ===
  PEXELS_API_KEY=
  OSS_ACCESS_KEY_ID=
  OSS_ACCESS_KEY_SECRET=
  OSS_BUCKET=
  OSS_ENDPOINT=
  OSS_CDN_DOMAIN=

  # === 支付 ===
  ZPAY_PID=
  ZPAY_KEY=
  ZPAY_SUBMIT_URL=https://zpayz.cn/submit.php
  ZPAY_API_URL=https://zpayz.cn/api.php
  ZPAY_NOTIFY_URL=
  ZPAY_RETURN_URL=
  ```
- **MIRROR**: 参考 Phase 1 .env.example + Phase 2-5 新增变量
- **IMPORTS**: 无
- **GOTCHA**: 敏感值留空，注释说明用途
- **VALIDATE**: 所有配置项都有说明

### Task 8: 创建 VIP 套餐种子数据
- **ACTION**: 创建 SQL 种子数据文件
- **IMPLEMENT**:
  ```sql
  INSERT INTO vip_plans (name, level, price, duration_days, quota_monthly, allowed_image_strategies, is_active, sort_order) VALUES
  ('免费版', 'free', 0.00, 0, 5, '["pexels","mermaid","iconify","emoji"]', 1, 1),
  ('月费版', 'monthly', 29.00, 30, 50, '["pexels","mermaid","iconify","emoji","svg","nanobanana"]', 1, 2),
  ('年费版', 'yearly', 199.00, 365, -1, '["pexels","mermaid","iconify","emoji","svg","nanobanana"]', 1, 3);
  ```
- **MIRROR**: 参考 `docker/mysql/init/01-init.sql`
- **IMPORTS**: 无
- **GOTCHA**: 文件名前缀 `02-` 确保 `01-init.sql` 之后执行
- **VALIDATE**: `docker exec nlp-mysql mysql -u root -pnlp_root_2024 nlp_content -e "SELECT * FROM vip_plans"` 显示 3 条记录

### Task 9: 创建 API 文档
- **ACTION**: 编写完整的 API 接口文档
- **IMPLEMENT**:
  - `docs/api.md`:
    - 认证 API: 注册/登录/刷新/登出/个人信息
    - AI Chat API: SSE 流式聊天
    - 创作工坊 API: 创建/获取/选标题/编辑大纲/确认/推进/导出
    - 配图 API: 策略列表/配图状态
    - VIP API: 套餐列表/会员信息/可用策略
    - 支付 API: 创建订单/回调/查询/订单列表
    - 管理后台 API: 可观测性统计/日志查询
    - 每个接口: URL、Method、Headers、Request Body、Response Body、状态码
- **MIRROR**: RESTful API 文档格式
- **IMPORTS**: 无
- **GOTCHA**: 包含 SSE 事件的类型说明（agent_start/agent_chunk/agent_complete/image_ready/workshop_complete/workshop_error）
- **VALIDATE**: API 文档覆盖所有端点

### Task 10: 创建部署指南
- **ACTION**: 编写完整的部署操作文档
- **IMPLEMENT**:
  - `docs/deployment.md`:
    - 环境要求: Docker + Docker Compose + 最低配置
    - 快速开始: 克隆 → 配置 .env → `docker compose up -d`
    - 配置说明: 每个环境变量的含义和获取方法
    - 外部服务配置: DashScope API Key / Pexels API Key / OSS / z-pay
    - Nginx HTTPS 配置: SSL 证书 + 域名
    - 数据库管理: 迁移/备份/恢复
    - 日志查看: `docker compose logs`
    - 常见问题排查
    - 更新升级流程
- **MIRROR**: 标准部署文档格式
- **IMPORTS**: 无
- **GOTCHA**: 包含内网穿透方案（开发环境 z-pay 回调测试）
- **VALIDATE**: 按文档步骤可成功部署

### Task 11: 创建项目 README
- **ACTION**: 项目主 README
- **IMPLEMENT**:
  - `README.md`:
    - 项目名称 + 简介 + 截图
    - 核心特性列表（5 大能力）
    - 技术栈表格
    - 快速开始（3 步: clone → env → docker compose up）
    - 项目结构目录树
    - 开发指南（前后端分别启动）
    - 环境变量说明（链接到 docs/deployment.md）
    - API 文档链接
    - License
- **MIRROR**: 标准 GitHub README 格式
- **IMPORTS**: 无
- **GOTCHA**: README 保持精简，详细内容链接到 docs/
- **VALIDATE**: README 渲染正常

### Task 12: 一键部署验证
- **ACTION**: 全新环境一键部署测试
- **IMPLEMENT**:
  - 停止并清理当前所有容器和数据
  - `docker compose down -v && docker compose build --no-cache`
  - 配置 `.env` 最小变量集
  - `docker compose up -d`
  - 等待所有服务健康
  - 浏览器访问 Nginx 端口（80）验证:
    - 首页加载
    - 注册/登录
    - 创作工坊完整流程
    - Dashboard
  - 验证 API 文档准确性
- **MIRROR**: 从零部署验证
- **IMPORTS**: N/A
- **GOTCHA**: 需要真实的外部服务 API Key（DashScope, Pexels 等）
- **VALIDATE**: 从零启动到完成创作流程

---

## Testing Strategy

### Deployment Tests

| Test | Expected |
|---|---|
| `docker compose build` | 所有镜像构建成功 |
| `docker compose up -d` | 所有容器健康运行 |
| `docker compose ps` | 5 个服务 (nginx/mysql/redis/backend/frontend) 状态 healthy |
| 浏览器访问 :80 | 首页正常加载 |
| API 调用 /api/auth/login | 登录成功 |
| SSE 连接 | 流式数据正常 |
| `docker compose logs` | 日志格式正常 |
| `docker exec nlp-mysql mysqldump` | 数据库备份成功 |

### Edge Cases Checklist
- [ ] 首次部署（空数据）→ 迁移自动运行
- [ ] 重复部署 → 数据不丢失（volume 持久化）
- [ ] 容器崩溃 → 自动重启（restart: unless-stopped）
- [ ] 数据库连接失败 → 后端等待重试（entrypoint wait-for）
- [ ] Nginx 配置错误 → 前端和 API 不可访问
- [ ] 内存不足 → OOM 处理（资源限制配置）

---

## Validation Commands

### Build Validation
```bash
docker compose build --no-cache
```
EXPECT: All images built successfully

### Deploy Validation
```bash
docker compose up -d
docker compose ps  # All services healthy
```
EXPECT: 5 services running

### Service Health Check
```bash
# Nginx
curl -s -o /dev/null -w "%{http_code}" http://localhost
# EXPECT: 200

# Backend API
curl -s -o /dev/null -w "%{http_code}" http://localhost/api/auth/login
# EXPECT: 405 (Method Not Allowed, GET instead of POST)

# SSE test (with token)
curl -N http://localhost/api/workshop/1/sse?token=$TOKEN
# EXPECT: SSE stream
```

### Database Validation
```bash
docker exec nlp-mysql mysql -u root -pnlp_root_2024 nlp_content \
  -e "SHOW TABLES; SELECT COUNT(*) FROM vip_plans"
```
EXPECT: All tables + 3 VIP plans

### Log Check
```bash
docker compose logs --tail=20 backend
```
EXPECT: Hyperf started successfully

---

## Acceptance Criteria
- [ ] Docker Compose 一键启动所有服务
- [ ] Nginx 反代后端 API + 前端静态文件
- [ ] SSE 通过 Nginx 正常工作
- [ ] 数据库迁移自动执行
- [ ] VIP 套餐种子数据自动插入
- [ ] `.env.example` 包含所有配置项
- [ ] API 文档覆盖所有端点
- [ ] 部署指南完整可操作
- [ ] README 项目介绍清晰
- [ ] 从零部署到可用 < 15 分钟

## Completion Checklist
- [ ] Docker Compose 配置生产级
- [ ] Nginx SSE 代理配置正确
- [ ] 镜像多阶段构建优化
- [ ] 环境变量模板完整
- [ ] 数据持久化（named volumes）
- [ ] 健康检查配置
- [ ] 日志管理配置
- [ ] 文档完整准确

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Nginx SSE 代理配置不当 | M | H | 严格遵循 proxy_buffering off + X-Accel-Buffering: no |
| 镜像构建失败（依赖下载） | M | M | 使用国内镜像源 + 构建缓存 |
| 数据库迁移冲突 | L | M | 迁移使用 IF NOT EXISTS + force 参数 |
| 前端构建环境变量问题 | L | M | VITE_ 前缀变量在构建时注入 |

## Notes

- Docker Compose 生产部署适用于中小规模（单机/少量用户），大规模需迁移到 Kubernetes
- Nginx SSE 配置是最关键的部署细节: `proxy_buffering off` + `proxy_cache off` + `X-Accel-Buffering: no` 三项缺一不可
- 前端构建在 Docker 内完成（多阶段构建），无需在宿主机安装 Node.js
- 生产环境建议使用 named volumes 管理数据持久化，避免 bind mount 权限问题
- 部署文档需包含常见问题排查章节，覆盖: 端口占用/权限问题/内存不足/数据库连接失败
- SSL/HTTPS 需用户自行配置（Let's Encrypt + certbot 或手动证书），文档中给出指引
