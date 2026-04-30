# 部署指南

> 目标：在一台干净的 Linux 服务器上，通过 `docker compose` 一键启动完整的
> AI 多智能体内容创作平台（Nginx + 后端 Hyperf + MySQL + Redis + 前端 SPA）。

---

## 1. 环境要求

| 组件           | 最低版本      | 说明                                       |
| -------------- | ------------- | ------------------------------------------ |
| 操作系统       | Linux / macOS | Ubuntu 22.04 / Debian 12 / CentOS 9 已验证 |
| Docker         | 24+           | 带 Buildx                                  |
| Docker Compose | v2.20+        | `docker compose` 而非 `docker-compose`     |
| CPU / 内存     | 2C / 4G       | 生产建议 4C / 8G                           |
| 磁盘           | 20G+          | MySQL / 镜像                               |
| 开放端口       | 80, 443       | 生产对外；本地开发另需 5173 / 9501 可选    |

外部服务（获取 API Key）：

- **DashScope**（必需，用于全部 Agent 的 LLM 调用）https://dashscope.console.aliyun.com
- **Pexels**（推荐，真实图源）https://www.pexels.com/api/
- **阿里云 OSS**（可选，图片托管）https://oss.console.aliyun.com
- **z-pay**（可选，上线付费必备）https://z-pay.cn

---

## 2. 快速开始（生产模式，3 步上线）

### Step 1. 克隆并进入项目

```bash
git clone <repo-url> nlp-data-admin
cd nlp-data-admin
```

### Step 2. 配置环境变量

```bash
cp backend/.env.example backend/.env
```

至少填写如下：

```bash
# 必填
JWT_SECRET=$(php -r "echo bin2hex(random_bytes(32));" 2>/dev/null || openssl rand -hex 32)
DASHSCOPE_API_KEY=sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxx
DB_PASSWORD=<强密码>
REDIS_PASSWORD=<强密码>

# 强烈建议
PEXELS_API_KEY=<pexels key>

# 上线付费必填
ZPAY_PID=<商户号>
ZPAY_KEY=<商户密钥>
ZPAY_NOTIFY_URL=https://<你的域名>/api/pay/notify
ZPAY_RETURN_URL=https://<你的域名>/vip/return
```

> compose 会从 `backend/.env` 读取同名变量注入容器（生产模式通过 `docker-compose.prod.yml` 显式映射），也可把 `.env` 放在 `docker/` 目录下供 compose 自动读取。

### Step 3. 启动

```bash
cd docker/
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

5 个容器全部就绪后（约 1–3 分钟），访问：

- 浏览器打开 `http://<服务器 IP>/` → 前端首页
- 调用 `http://<服务器 IP>/api/auth/login` → 后端可用

### 验证

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
# 5 行均应为 healthy 或 running

curl -s -o /dev/null -w "%{http_code}\n" http://localhost/                    # 200
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/api/vip/plans       # 200
```

---

## 3. 开发模式

```bash
cd docker/
docker compose up -d            # 默认启 dev，nlp-frontend 走 Vite dev server
```

- 前端：http://localhost:5173（Vite 热更新）
- 后端：http://localhost:9501（源码 volume 挂载）
- Nginx：http://localhost:8080（可选；dev 非必需）
- MySQL：`127.0.0.1:33060`
- Redis：`127.0.0.1:16379`（密码 `nlp_redis_2024`）

---

## 4. 环境变量一览

完整变量说明参见 [`backend/.env.example`](../backend/.env.example)。关键分组：

| 分组     | 变量前缀                                                                                | Phase | 必填                    |
| -------- | --------------------------------------------------------------------------------------- | ----- | ----------------------- |
| 应用     | `APP_`                                                                                  | 1     | —                       |
| 数据库   | `DB_`                                                                                   | 1     | ✅                      |
| Redis    | `REDIS_`                                                                                | 1     | ✅                      |
| JWT      | `JWT_SECRET`                                                                            | 2     | ✅                      |
| AI 模型  | `DASHSCOPE_` / `OPENAI_`                                                                | 2     | ✅（至少一个 provider） |
| Pexels   | `PEXELS_`                                                                               | 4     | 推荐                    |
| 其他图源 | `MERMAID_ENABLED` / `ICONIFY_ENABLED` / `EMOJI_ENABLED` / `SVG_ENABLED` / `NANOBANANA_` | 4     | 可选                    |
| OSS      | `OSS_`                                                                                  | 4     | 可选                    |
| 支付     | `ZPAY_`                                                                                 | 5     | 上线必填                |

---

## 5. 数据库与数据

### 5.1 迁移

- 容器启动时 `docker/backend/entrypoint.sh` 会自动执行 `php bin/hyperf.php migrate --force`；失败最多重试 3 次。
- 首次迁移会建 6 张表并**自动插入 3 条 VIP 套餐种子**（在 `CreateVipPlansTable` 迁移中 INSERT，无需额外 SQL）。

### 5.2 手动迁移

```bash
docker exec -it nlp-backend php bin/hyperf.php migrate --force
```

### 5.3 备份 & 恢复

```bash
# 备份
docker exec nlp-mysql mysqldump -uroot -p$DB_PASSWORD nlp_content > backup.sql

# 恢复
docker exec -i nlp-mysql mysql -uroot -p$DB_PASSWORD nlp_content < backup.sql
```

### 5.4 提升某用户为 admin（访问数据看板）

```bash
docker exec nlp-mysql mysql -uroot -p$DB_PASSWORD nlp_content \
  -e "UPDATE users SET role='admin' WHERE email='your@email.com';"
```

---

## 6. HTTPS / 域名配置

推荐用 **宿主机 Nginx + 证书** 反代容器内 Nginx（80），或直接扩展 `docker/nginx/conf.d/default.conf`：

```nginx
server {
    listen 443 ssl http2;
    server_name your.domain.com;

    ssl_certificate     /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;

    # 其余 location 直接沿用 default.conf 中的配置
    include /etc/nginx/conf.d/default.conf.locations;
}

server {
    listen 80;
    server_name your.domain.com;
    return 301 https://$host$request_uri;
}
```

证书申请：Let's Encrypt + certbot：

```bash
certbot certonly --standalone -d your.domain.com
```

---

## 7. Nginx SSE 关键配置（已内置）

`docker/nginx/conf.d/default.conf` 已启用以下 3 项（**缺一不可**）：

```nginx
proxy_buffering      off;
proxy_cache          off;
add_header           X-Accel-Buffering no;
```

同时长连接超时 600s，以支撑 AI 生成正文 2–3 分钟的场景。

---

## 8. 常见问题

### Q1. `docker compose up` 后后端容器反复重启

- `docker logs nlp-backend` 查看日志
- 最常见：MySQL 密码与 `backend/.env` 不一致；或 DashScope Key 填错导致 Agent 启动报错

### Q2. SSE 无数据（前端一直 loading）

- 用 curl 直接访问：`curl -N "http://localhost/api/workshop/1/generate-stream?token=xxx"`
- 若 curl 有数据但浏览器没有，检查中间是否有额外代理缓冲（如 Cloudflare 需打开 "Gray Cloud"）

### Q3. 端口 80 被占用

- 改 `docker-compose.prod.yml` 中 `nlp-nginx.ports` 为 `"8000:80"`

### Q4. 生产环境忘记 admin 账号

- 直接在 MySQL 中 UPDATE role（见 5.4）

### Q5. 前端访问 /api 报 404

- 确认 `nlp-nginx` 容器运行中；`docker exec nlp-nginx nginx -T` 查看实际加载的配置

### Q6. `php bin/hyperf.php migrate` 报 `SQLSTATE[HY000] [2002]`

- MySQL 还没 ready，entrypoint 会自动重试；如手动执行，先 `docker compose ps` 确认 mysql healthy

---

## 9. 升级流程

```bash
git pull
cd docker/
docker compose -f docker-compose.yml -f docker-compose.prod.yml build --pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

迁移会自动执行，无需手动。

---

## 10. 清理与重置

```bash
cd docker/
docker compose down              # 停止并移除容器
docker compose down -v           # 同时移除 volume（**会删库**，谨慎）
```
