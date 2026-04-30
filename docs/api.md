# API 接口文档

> NLP Data Admin · AI 多智能体内容创作平台
>
> Base URL：`http://localhost` （生产经 Nginx 反代 80，API 统一前缀 `/api`）

## 通用约定

### 认证

- 除"公开端点"外，所有接口需在 Header 中携带 JWT：
  ```
  Authorization: Bearer <token>
  ```
- SSE/EventSource 场景无法设置 Header，可以在 query 中传 `token=<jwt>`。

### 响应格式

```json
{
  "code": 200,
  "message": "ok",
  "data": {}
}
```

| Code | 含义                                |
| ---- | ----------------------------------- |
| 200  | 成功                                |
| 400  | 参数错误                            |
| 401  | 未登录 / token 失效                 |
| 403  | 权限不足（admin / 会员级别 / 配额） |
| 404  | 资源不存在                          |
| 422  | 业务校验失败                        |
| 500  | 服务端异常                          |

分页响应统一结构：

```json
{
  "code": 200,
  "data": { "list": [...], "total": 100, "page": 1, "limit": 20 }
}
```

---

## 1. 认证 Auth

### 1.1 注册 `POST /api/auth/register`（公开）

```jsonc
// Request
{ "username": "alice", "email": "a@x.com", "password": "Secret123" }
// Response.data
{ "token": "...", "expires_in": 86400, "user": { "id": 1, "username": "alice", "email": "a@x.com", "role": "user" } }
```

### 1.2 登录 `POST /api/auth/login`（公开）

```jsonc
// Request
{ "account": "alice", "password": "Secret123" } // account 支持用户名/邮箱
// Response.data 同注册
```

### 1.3 刷新 token `POST /api/auth/refresh`（JWT）

返回新 `token` 与 `expires_in`。

### 1.4 退出 `POST /api/auth/logout`（JWT）

### 1.5 当前用户 `GET /api/auth/me`（JWT）

返回 `{ id, username, email, role, created_at }`。

---

## 2. AI 通用对话 AI Chat

### 2.1 可用模型 `GET /api/ai/providers`（JWT）

返回当前配置的 provider + model 列表。

### 2.2 一次性对话 `POST /api/ai/chat`（JWT）

```jsonc
{ "messages": [{ "role": "user", "content": "你好" }] }
```

### 2.3 流式对话（SSE） `POST /api/ai/chat/stream`（JWT）

- `Content-Type: text/event-stream`
- 事件：`data: { "delta": "..." }` 多次 → `data: [DONE]`

---

## 3. VIP 会员

### 3.1 套餐列表 `GET /api/vip/plans`（公开）

```jsonc
[{ "id": 2, "name": "月费版", "level": "monthly", "price": 29.00, "duration_days": 30,
   "quota_monthly": 50, "allowed_image_strategies": ["pexels","mermaid",...] }]
```

### 3.2 我的会员信息 `GET /api/vip/info`（JWT）

```jsonc
{ "effective_level": "monthly", "vip_expired_at": "2026-06-01 00:00:00",
  "quota_total": 50, "quota_used": 8, "allowed_image_strategies": ["pexels",...] }
```

### 3.3 本月配额 `GET /api/vip/quota`（JWT）

`{ "used": 8, "total": 50, "reset_at": "2026-06-01 00:00:00" }`

### 3.4 可用配图策略 `GET /api/vip/strategies`（JWT）

---

## 4. 支付 Pay

### 4.1 创建订单 `POST /api/pay/create`（JWT）

```jsonc
// Request
{ "plan_id": 2, "pay_type": "alipay" }   // pay_type: alipay | wxpay
// Response.data
{ "out_trade_no": "NLP20260501xxx", "pay_url": "https://zpayz.cn/submit.php?...",
  "qr_url": "https://...qr.png", "amount": "29.00" }
```

### 4.2 查询订单状态 `GET /api/pay/status?out_trade_no=xxx`（JWT）

返回 `status` 字段：`pending / paid / failed / refunded`。

### 4.3 订单列表 `GET /api/pay/orders?page=1&limit=20`（JWT）

分页结构，`status` 支持按状态筛选。

### 4.4 异步回调 `POST /api/pay/notify`（公开，z-pay 调用）

成功返回 `success` 明文，失败返回 `fail`。

### 4.5 同步跳转 `GET /api/pay/return`（公开）

浏览器跳回，渲染 HTML 自动跳转前端。

---

## 5. 文章 Articles

### 5.1 列表 `GET /api/articles?page=1&limit=20&status=completed&keyword=AI`（JWT）

- `status` 可选：`draft / title_generating / title_selecting / outline_generating / outline_editing / content_generating / image_analyzing / image_generating / completed / failed`
- `keyword` 模糊匹配 `title / selected_title / topic`
- 响应：分页结构，`list[]` 每项含 `id / title / topic / style / status / word_count / ai_model / created_at / updated_at`。

### 5.2 详情 `GET /api/articles/{id}`（JWT）

在列表字段基础上追加 `selected_title / outline / content / images[]`。

### 5.3 删除 `DELETE /api/articles/{id}`（JWT）

返回 `{ "id": 1 }`。

---

## 6. 创作工坊 Workshop（多 Agent 编排）

### 6.1 创建选题 `POST /api/workshop/create`（JWT + 配额）

```jsonc
{ "topic": "AI 与未来工作", "style": "科普", "target_length": 2000 }
// Response.data: { "id": 42, "status": "title_generating" }
```

> 该接口触发 `TitleGenerator` Agent 异步生成；随后前端应立即连接 SSE 订阅进度。

### 6.2 选定标题 `POST /api/workshop/{id}/select-title`（JWT）

```jsonc
{ "title": "AI 会取代我的工作吗？未来十年最具说服力的推演" }
// 服务端触发 OutlineGenerator
```

### 6.3 更新大纲 `PUT /api/workshop/{id}/outline`（JWT）

```jsonc
// Request body 为完整 Outline 对象
{ "sections": [{ "heading": "引言", "points": ["…"] }, ...] }
// 服务端立即触发 ContentGenerator（流式）+ ImageAnalyzer + ImageGenerator
```

### 6.4 进度 SSE `GET /api/workshop/{id}/generate-stream`（JWT via query `?token=xxx`）

返回 `text/event-stream`，按 Agent 分阶段推送。事件类型：

| event               | payload 关键字段                                      | 触发时机              |
| ------------------- | ----------------------------------------------------- | --------------------- |
| `agent_start`       | `{ agent, stage, message }`                           | 每个 Agent 启动       |
| `title_list`        | `{ titles: string[] }`                                | TitleGenerator 结束   |
| `outline_draft`     | `{ outline: {...} }`                                  | OutlineGenerator 结束 |
| `agent_chunk`       | `{ agent: "content_generator_stream", delta: "..." }` | 正文流式增量          |
| `agent_complete`    | `{ agent, duration_ms, output_summary }`              | 单个 Agent 完成       |
| `image_ready`       | `{ placeholder_id, url, source, alt, keyword }`       | 单张配图完成          |
| `workshop_complete` | `{ article_id, word_count, images_count }`            | 全流程完成            |
| `workshop_error`    | `{ agent, message, code }`                            | 任一 Agent 失败       |

客户端收到 `workshop_complete` 或 `workshop_error` 后应关闭 EventSource。

### 6.5 轮询状态 `GET /api/workshop/{id}/status`（JWT）

在 SSE 不可用时的降级接口。

### 6.6 结果 `GET /api/workshop/{id}/result`（JWT）

返回与 `/api/articles/{id}` 相同的详情结构。

---

## 7. 管理后台可观测性 Admin Observability

> 全部需 `admin` 角色，非管理员 403。

统一查询参数：`start_date` / `end_date`（`YYYY-MM-DD`，默认最近 7 天）。

### 7.1 总览 `GET /api/admin/observability/overview`

```jsonc
{
  "total": 120,
  "success": 115,
  "failed": 5,
  "running": 0,
  "success_rate": 95.83,
  "avg_duration_ms": 4321,
  "p95_duration_ms": 9876,
  "slowest_agent": {
    "name": "content_generator_stream",
    "avg_duration_ms": 12345,
  },
  "start_date": "2026-04-25",
  "end_date": "2026-05-01",
}
```

### 7.2 分 Agent 统计 `GET /api/admin/observability/agents`

`{ list: [{ name, total, success, failed, success_rate, avg_duration_ms }] }`

### 7.3 日趋势 `GET /api/admin/observability/trend`

`{ list: [{ date, total, success, failed, success_rate, avg_duration_ms }] }`

### 7.4 慢日志 `GET /api/admin/observability/slow?threshold=10000&limit=20`

`{ threshold_ms: 10000, list: [AgentLog...] }`

### 7.5 最近日志 `GET /api/admin/observability/logs?agent_name=&status=&limit=50`

### 7.6 用户画像 `GET /api/admin/observability/user/{id}`

返回单个用户的调用量、成功率、Agent 使用分布。

---

## 附录 A：状态枚举

### 文章 status

| 值                   | 说明                         |
| -------------------- | ---------------------------- |
| `draft`              | 创建但未生成                 |
| `title_generating`   | 正在生成标题                 |
| `title_selecting`    | 等待用户选标题               |
| `outline_generating` | 正在生成大纲                 |
| `outline_editing`    | 等待用户编辑大纲             |
| `content_generating` | 正在流式生成正文             |
| `image_analyzing`    | 正在分析配图需求             |
| `image_generating`   | 正在并行生成配图             |
| `completed`          | 已完成                       |
| `failed`             | 失败（检查 `error_message`） |

### 订单 status

`pending / paid / failed / refunded`

### Agent 日志 status

`running / success / failed`
