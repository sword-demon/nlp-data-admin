# Plan: Phase 3 — 三阶段创作流程

## Summary

实现 5 个 AI 智能体的完整协作链：TitleGeneratorAgent 生成标题 → OutlineGeneratorAgent 生成大纲 → ContentGeneratorAgent 生成正文 → ImageAnalyzerAgent 分析配图 → ParallelImageGenerator 调度配图。通过状态机管理三阶段流转，SSE 事件实时推送创作进度，在标题选择和大纲编辑两个节点暂停等待用户交互（Human-in-the-loop）。

## User Story

As a 内容创作者, I want 输入选题后由 5 个 AI 智能体协作完成从标题到配图的完整文章创作, 并在标题和大纲阶段介入修改, So that 我能在 15-30 分钟内获得一篇高质量的完整配图文章。

## Problem → Solution

当前只有单次 AI 对话能力 → 5 个专业化 Agent 通过状态机编排，三阶段 human-in-the-loop 协作，SSE 实时推送进度，产出带配图占位符的完整 Markdown 文章。

## Metadata

- **Complexity**: XL
- **Source PRD**: `.claude/PRPs/prds/ai-multi-agent-content-platform.prd.md`
- **PRD Phase**: Phase 3 — 三阶段创作流程
- **Estimated Files**: 25+

---

## UX Design

### Before
```
┌──────────────────────────────────┐
│  无创作流程                      │
│  仅有单次 AI 对话                │
│  无 Agent 协作                   │
│  无 human-in-the-loop            │
└──────────────────────────────────┘
```

### After
```
┌──────────────────────────────────┐
│  阶段 1: 输入选题 + 文章风格     │
│  ┌────────────────────────────┐  │
│  │ 标题 1: xxx  [选择]        │  │
│  │ 标题 2: xxx  [选择]        │  │
│  │ 标题 3: xxx  [选择]        │  │
│  │ [补充描述引导方向...]      │  │
│  │            [确认]          │  │
│  └────────────────────────────┘  │
│          ↓                       │
│  阶段 2: 结构化大纲              │
│  ┌────────────────────────────┐  │
│  │ 一、引言                   │  │
│  │   1.1 开篇...   [编辑]     │  │
│  │ 二、正文                   │  │
│  │   2.1 论点...   [编辑]     │  │
│  │ [AI 优化] [确认]           │  │
│  └────────────────────────────┘  │
│          ↓                       │
│  阶段 3: 正文生成 + 配图         │
│  ┌────────────────────────────┐  │
│  │ ████████░░░░ 60%           │  │
│  │ 正在生成正文...            │  │
│  │ 正在分析配图...            │  │
│  │ 正在获取图片...            │  │
│  └────────────────────────────┘  │
│          ↓                       │
│  完整 Markdown 文章预览          │
└──────────────────────────────────┘
```

### Interaction Changes

| Touchpoint | Before | After | Notes |
|---|---|---|---|
| 创作入口 | POST /api/ai/chat | POST /api/workshop/create | 创建创作会话 |
| 标题阶段 | 不存在 | POST /api/workshop/{id}/select-title | 用户选择标题 |
| 大纲阶段 | 不存在 | PUT /api/workshop/{id}/outline | 用户编辑大纲 |
| 进度推送 | 不存在 | GET /api/workshop/{id}/stream | SSE 事件流 |
| 正文/配图 | 不存在 | 自动化（无用户交互） | Agent 3-5 自动完成 |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Contract/ModelProviderInterface.php` | 1-20 (plan) | Agent 调用大模型的接口契约 |
| P0 | `backend/app/Service/Provider/DashScopeProvider.php` | 1-80 (plan) | SSE 流式调用的实现参考 |
| P0 | `backend/app/Model/Article.php` | 1-50 | 文章状态和结构 |
| P0 | `backend/app/Helpers/ApiResponse.php` | 1-55 | 统一响应格式 |
| P1 | `backend/app/Controller/AbstractController.php` | 1-30 | 控制器基类 |
| P1 | `backend/app/Service/ModelProviderService.php` | 1-40 (plan) | Provider 调度 |
| P2 | `backend/config/autoload/redis.php` | 1-30 | Redis 配置（SSE 频道） |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| Hyperf EventStream | `Hyperf\Engine\Http\EventStream` | `write()` 返回 false 时客户端已断开 |
| DashScope prompt engineering | 通义千问文档 | system prompt 控制 Agent 角色行为 |
| Markdown 语法 | CommonMark spec | 配图占位符格式 `![keyword](placeholder://...)` |

---

## Patterns to Mirror

Same codebase patterns as documented in Phase 2 plan, plus:

### SERVICE_PATTERN (new — to establish)
```php
// Agents follow this pattern:
class TitleGeneratorAgent
{
    public function __construct(
        private ModelProviderService $modelProvider,
        private StdoutLoggerInterface $logger,
    ) {}

    public function generate(string $topic, string $style): array
    {
        // 1. Build system prompt for this agent's role
        // 2. Call model provider chat() / chatStream()
        // 3. Parse and validate output
        // 4. Return structured result
    }
}
```

### STATE_MACHINE_PATTERN (new — to establish)
```php
enum WorkshopState: string
{
    case DRAFT = 'draft';
    case TITLE_GENERATING = 'title_generating';
    case TITLE_SELECTING = 'title_selecting';
    case OUTLINE_GENERATING = 'outline_generating';
    case OUTLINE_EDITING = 'outline_editing';
    case CONTENT_GENERATING = 'content_generating';
    case IMAGE_ANALYZING = 'image_analyzing';
    case IMAGE_GENERATING = 'image_generating';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/app/Enum/WorkshopState.php` | CREATE | 创作流程状态枚举 |
| `backend/app/Contract/AgentInterface.php` | CREATE | Agent 统一接口 |
| `backend/app/Service/Agent/TitleGeneratorAgent.php` | CREATE | 标题生成智能体 |
| `backend/app/Service/Agent/OutlineGeneratorAgent.php` | CREATE | 大纲生成智能体 |
| `backend/app/Service/Agent/ContentGeneratorAgent.php` | CREATE | 正文生成智能体 |
| `backend/app/Service/Agent/ImageAnalyzerAgent.php` | CREATE | 配图分析智能体 |
| `backend/app/Service/Agent/ParallelImageGenerator.php` | CREATE | 并行配图调度（Phase 4 集成点） |
| `backend/app/Service/WorkshopOrchestrator.php` | CREATE | 状态机编排器 |
| `backend/app/Service/SseBroadcaster.php` | CREATE | SSE 事件广播 |
| `backend/app/Controller/WorkshopController.php` | CREATE | 创作工坊 API |
| `backend/app/Controller/WorkshopSseController.php` | CREATE | SSE 流端点 |
| `backend/migrations/2026_04_30_000004_add_workshop_fields_to_articles.php` | CREATE | articles 表补充字段 |
| `backend/config/routes.php` | UPDATE | 添加 workshop 路由组 |
| `frontend/src/pages/Workshop.vue` | UPDATE | 实现完整创作工坊 UI |
| `frontend/src/components/workshop/TitleSelector.vue` | CREATE | 标题选择组件 |
| `frontend/src/components/workshop/OutlineEditor.vue` | CREATE | 大纲编辑器组件 |
| `frontend/src/components/workshop/ContentPreview.vue` | CREATE | 正文预览组件 |
| `frontend/src/components/workshop/ProgressPanel.vue` | CREATE | 进度面板组件 |
| `frontend/src/api/workshop.ts` | CREATE | 创作工坊 API 调用 |
| `frontend/src/stores/workshop.ts` | CREATE | 创作流程状态管理 |

## NOT Building

- 配图实际获取和上传 — 属于 Phase 4，Phase 3 仅生成占位符
- VIP 配额检查 — 属于 Phase 5，Phase 3 暂时跳过
- AOP 日志记录 — 属于 Phase 6
- 文章导出功能 — 后续 Phase
- 大纲的 AI 自动优化建议 — Phase 3 仅支持手动编辑，AI 优化可选

---

## Step-by-Step Tasks

### Task 1: 创建状态枚举和文章表补充字段
- **ACTION**: 定义 `WorkshopState` 枚举，添加 `selected_title`、`generated_titles`、`title_supplement` 字段到 articles 表
- **IMPLEMENT**:
  - `WorkshopState`: 9 个状态值（draft → failed）
  - Migration: 添加 `selected_title VARCHAR(500)`, `generated_titles JSON`, `title_supplement TEXT`, `ai_model VARCHAR(100)` 列
- **MIRROR**: 参考 `app/Model/Article.php:12-19` 的常量定义和 `app/Constants/Code.php` 的类结构
- **IMPORTS**: `Hyperf\Database\Migrations\Migration`, `Hyperf\Database\Schema\Schema`
- **GOTCHA**: JSON 列在 MySQL 中需使用 `$table->json('generated_titles')` 而非 `text`
- **VALIDATE**: `php bin/hyperf.php migrate` 执行后 articles 表新增 4 列

### Task 2: 创建 AgentInterface 契约
- **ACTION**: 定义所有 Agent 的统一接口
- **IMPLEMENT**:
  ```php
  interface AgentInterface {
      public function getName(): string;
      public function execute(array $context): array;
      public function executeStream(array $context): \Generator;
  }
  ```
- **MIRROR**: 参考 `app/Contract/ModelProviderInterface.php` 的接口定义风格
- **IMPORTS**: 无外部依赖
- **GOTCHA**: `executeStream` 返回 Generator 支持 SSE 逐条推送；`$context` 是之前 Agent 的输出上下文
- **VALIDATE**: 语法检查通过

### Task 3: 创建 SseBroadcaster
- **ACTION**: SSE 事件广播服务，统一管理创作流程中的实时推送
- **IMPLEMENT**:
  - `broadcast(EventStream $stream, string $event, array $data): bool` — 发送 SSE 事件
  - `broadcastStateChange(EventStream $stream, WorkshopState $state, array $extra): bool` — 状态变更事件
  - `broadcastError(EventStream $stream, string $message, string $agent): bool` — 错误事件
  - 检查 `$stream->write()` 返回值判断断连
- **MIRROR**: 使用 `Hyperf\Engine\Http\EventStream` API 模式
- **IMPORTS**: `Hyperf\Engine\Http\EventStream`, `App\Enum\WorkshopState`
- **GOTCHA**: SSE 格式 `event: {name}\ndata: {json}\n\n`，每个事件间双换行
- **VALIDATE**: 测试发送事件，前端 EventSource 能接收

### Task 4: 创建 TitleGeneratorAgent
- **ACTION**: 标题生成智能体 — 根据选题和风格生成 3-5 个爆款标题
- **IMPLEMENT**:
  - System prompt: "你是一个爆款标题专家，擅长为自媒体文章创作吸引眼球的标题..."
  - `execute()`: 调用 ModelProvider，解析 JSON 格式的标题数组
  - `executeStream()`: 流式版本（Phase 3 暂用非流式 + 完成后广播）
  - 输出格式: `[{title, analysis, score}]`
- **MIRROR**: Service 层构造函数 DI（参考 `AppExceptionHandler:16-18`）
- **IMPORTS**: `App\Service\ModelProviderService`, `App\Contract\AgentInterface`
- **GOTCHA**: 
  - Prompt 中明确要求返回 JSON 数组格式，方便解析
  - 标题数量控制 3-5 个；temperature 设 0.8 增强创意性
  - 解析失败时降级为简单 split
- **VALIDATE**: 输入选题 "AI 对未来的影响"，返回 3-5 个标题

### Task 5: 创建 OutlineGeneratorAgent
- **ACTION**: 大纲生成智能体 — 根据选定标题生成结构化 Markdown 大纲
- **IMPLEMENT**:
  - System prompt: "你是一个资深内容编辑，擅长为文章设计逻辑严密的大纲结构..."
  - 接收 `{title, supplement}` 上下文
  - 输出: Markdown 格式的层级大纲（`## 一、` / `### 1.1` 结构）
  - 每个大纲节点附带 `{id, text, level, parent_id}`
- **MIRROR**: 与 TitleGeneratorAgent 相同的 DI + execute 模式
- **IMPORTS**: `App\Service\ModelProviderService`
- **GOTCHA**: 大纲结构需支持前端编辑，每个节点需有唯一 ID（用 UUID 或自增）
- **VALIDATE**: 输入标题，返回 3-5 个主章节 + 子节点的结构化大纲

### Task 6: 创建 ContentGeneratorAgent
- **ACTION**: 正文生成智能体 — 根据大纲逐段生成 Markdown 正文，含配图占位符
- **IMPLEMENT**:
  - System prompt: "你是一个专业自媒体作者，擅长创作通俗易懂、有深度的内容..."
  - 接收 `{title, outline, supplement}` 上下文
  - `executeStream()`: 流式生成，每个 chunk 实时广播
  - 在合适位置插入配图占位符: `![配图:关键词](placeholder://image/{index})`
  - 输出: 完整 Markdown 正文
- **MIRROR**: Generator 模式参考 `ModelProviderInterface::chatStream()`
- **IMPORTS**: `App\Service\ModelProviderService`
- **GOTCHA**: 
  - 配图占位符插入策略：每 2-3 段正文后插入一个占位符
  - 流式输出需缓存文本，检测段落边界插入占位符
  - 确保 Markdown 格式正确
- **VALIDATE**: 流式生成完整文章，含 3+ 配图占位符

### Task 7: 创建 ImageAnalyzerAgent
- **ACTION**: 配图分析智能体 — 分析正文中每个占位符的上下文，确定最佳配图方式和搜索关键词
- **IMPLEMENT**:
  - 接收 `{content, placeholders[]}` 上下文
  - 对每个占位符分析周围文本，输出:
    ```json
    {
      "placeholder_id": "image/0",
      "context": "描述AI发展的段落",
      "keywords": ["artificial intelligence", "AI technology"],
      "suggested_type": "pexels",  // pexels|mermaid|iconify|emoji|svg|nanobanana
      "reasoning": "这是一个概念性描述，适合用科技类图库照片"
    }
    ```
  - 非流式（快速分析后批量返回）
- **MIRROR**: 与其他 Agent 相同的 DI 模式
- **IMPORTS**: `App\Service\ModelProviderService`
- **GOTCHA**: 
  - 分析 prompt 需引导模型输出 JSON，方便后续配图
  - 配图方式选择逻辑：数据/流程→mermaid, 概念→pexels, 情绪→emoji, 抽象→svg, 创意→nanobanana
- **VALIDATE**: 输入含占位符的文章，返回每个占位符的分析结果

### Task 8: 创建 ParallelImageGenerator（占位）
- **ACTION**: 并行配图调度器 — Phase 3 仅占位，输出 mock 图片数据
- **IMPLEMENT**:
  - 接收 `{image_analyses[]}` 上下文
  - 当前返回占位 URL: `https://via.placeholder.com/800x400?text={keyword}`
  - 保留策略模式接口，Phase 4 接入真实配图服务
- **MIRROR**: 与其他 Agent 相同的接口
- **IMPORTS**: `App\Contract\AgentInterface`
- **GOTCHA**: 标记 TODO 注释，明确 Phase 4 替换方案
- **VALIDATE**: 返回每个占位符对应的 mock URL

### Task 9: 创建 WorkshopOrchestrator（核心）
- **ACTION**: 状态机编排器 — 管理 5 个 Agent 的执行顺序、状态流转、上下文传递
- **IMPLEMENT**:
  ```
  State Flow:
  DRAFT → TITLE_GENERATING → TITLE_SELECTING (wait user)
        → OUTLINE_GENERATING → OUTLINE_EDITING (wait user)
        → CONTENT_GENERATING → IMAGE_ANALYZING → IMAGE_GENERATING
        → COMPLETED
  ```
  - `start(int $articleId, string $topic, string $style): void` — 启动创作
  - `selectTitle(int $articleId, int $titleIndex, string $supplement): void` — 用户选择标题
  - `updateOutline(int $articleId, array $outline): void` — 用户编辑大纲
  - `confirmOutline(int $articleId): void` — 确认大纲，继续创作
  - `getStatus(int $articleId): WorkshopState` — 获取当前状态
  - `getContext(int $articleId): array` — 获取累积上下文
  - 内部使用 Redis 存储临时上下文（key: `workshop:{articleId}:context`）
- **MIRROR**: 构造函数 DI；异常处理 AppExceptionHandler 风格
- **IMPORTS**: `Hyperf\Redis\Redis`, `App\Enum\WorkshopState`, 所有 Agent 类
- **GOTCHA**:
  - 状态转换需校验合法性（不能从 COMPLETED 跳回）
  - 每个状态转换前检查 article.status 一致性
  - 错误状态 FAILED 可从任何状态进入
  - Redis 临时上下文在 COMPLETED 后持久化到 article 表
- **VALIDATE**: 单元测试覆盖所有状态转换路径

### Task 10: 创建 WorkshopController
- **ACTION**: 创作工坊 REST API
- **IMPLEMENT**:
  - `POST /api/workshop/create` → `create()`: 接收 topic/style，创建 article + 启动创作
  - `POST /api/workshop/{id}/select-title` → `selectTitle()`: 选择标题 + 补充描述
  - `PUT /api/workshop/{id}/outline` → `updateOutline()`: 编辑大纲
  - `POST /api/workshop/{id}/confirm-outline` → `confirmOutline()`: 确认大纲
  - `GET /api/workshop/{id}/status` → `status()`: 获取当前状态和文章内容
  - `GET /api/workshop/{id}/result` → `result()`: 获取完整文章
- **MIRROR**: 扩展 `AbstractController`，使用 `ApiResponse::success/error`
- **IMPORTS**: `App\Service\WorkshopOrchestrator`, `App\Helpers\ApiResponse`
- **GOTCHA**: 所有端点需要 JWT 认证（middleware），用户只能操作自己的文章
- **VALIDATE**: curl 测试完整创作流程 API

### Task 11: 创建 WorkshopSseController（SSE 端点）
- **ACTION**: SSE 流端点 — 前端 EventSource 连接此端点接收实时进度
- **IMPLEMENT**:
  - `GET /api/workshop/{id}/stream`: SSE 长连接
  - 使用 `EventStream` + Redis Pub/Sub 订阅 `workshop:{id}:events` 频道
  - Orchestrator 每个状态变更时 publish 事件到 Redis
  - SSE Controller 订阅并广播到前端
  - 检测客户端断开时清理
- **MIRROR**: 使用 `Hyperf\Engine\Http\EventStream`
- **IMPORTS**: `Hyperf\Engine\Http\EventStream`, `Hyperf\Redis\Redis`
- **GOTCHA**:
  - Nginx 需要 `proxy_buffering off`
  - 心跳机制: 每 15 秒发送 `:heartbeat`（SSE 注释行）
  - SSE 路由不能被 JWT 中间件拦截（用 URL token 参数或独立中间件）
- **VALIDATE**: EventSource 连接到 SSE，收到标题生成完成事件

### Task 12: 更新路由和配置
- **ACTION**: 注册 workshop 路由组和 SSE 路由
- **IMPLEMENT**:
  - `routes.php`: `Router::addGroup('/api/workshop/', ...)` 含 6 个路由
  - SSE 路由独立注册，绕过 JWT 中间件（用 query token 认证）
- **MIRROR**: 路由定义遵循 `routes.php:14` 格式
- **IMPORTS**: 各 Controller 的完全限定名
- **GOTCHA**: SSE 端点用 GET 方法；JWT 通过 query string 传递: `/stream?token=xxx`
- **VALIDATE**: `php bin/hyperf.php describe:routes` 显示所有 workshop 路由

### Task 13: 创建前端创作工坊页面
- **ACTION**: 实现完整的 Workshop.vue 三阶段 UI
- **IMPLEMENT**:
  - 阶段切换逻辑（基于 WorkshopState）
  - **阶段 1 — 选题输入**: 输入框（选题+风格）+ 开始按钮
  - **阶段 1.5 — 标题选择**: `TitleSelector.vue` — 3-5 个标题卡片 + 选中 + 补充描述输入
  - **阶段 2 — 大纲编辑**: `OutlineEditor.vue` — 可编辑树形大纲 + AI 优化按钮 + 确认按钮
  - **阶段 3 — 正文生成**: SSE 流式接收 + `ContentPreview.vue` 实时渲染 Markdown + 配图进度
  - **进度面板**: `ProgressPanel.vue` — 显示当前 Agent 状态动画
- **MIRROR**: 
  - Pinia: `src/stores/auth.ts` 模式
  - Ant Design: Card/Form/Button/Tag/Progress/Tree 组件
  - markdown-it: Markdown 渲染
- **IMPORTS**: `markdown-it`, `highlight.js`, `ant-design-vue`, `pinia`
- **GOTCHA**:
  - EventSource 重连机制：断线后自动重连
  - 状态同步：SSE 事件驱动状态更新，不依赖轮询
  - 大纲编辑需支持拖拽排序（可选，用 CSS + 简单实现）
- **VALIDATE**: 浏览器端到端走完三阶段创作流程

### Task 14: 创建前端状态管理
- **ACTION**: 创建 `workshop.ts` Pinia store 和 `workshop.ts` API 模块
- **IMPLEMENT**:
  - `stores/workshop.ts`: 
    - `currentState: WorkshopState`
    - `articleId: number | null`
    - `titles: Title[]`
    - `selectedTitleIndex: number`
    - `outline: OutlineNode[]`
    - `content: string` (流式累积)
    - `imageProgress: ImageProgress[]`
    - `connectSSE()` 和 `disconnectSSE()` 方法
  - `api/workshop.ts`: 所有创作 API 的 axios 调用
- **MIRROR**: `src/stores/auth.ts:8-15` 的 defineStore + ref + computed 模式
- **IMPORTS**: `axios`, `pinia`, EventSource API
- **GOTCHA**: SSE 连接需在 store 中管理生命周期，页面卸载时断开
- **VALIDATE**: TypeScript type check 通过

### Task 15: 集成验证
- **ACTION**: Docker 环境中端到端测试完整创作流程
- **IMPLEMENT**:
  - 创建文章 → SSE 收到 `title_generating` → `title_generated` 事件
  - 选择标题 → SSE 收到 `outline_generating` → `outline_generated` 事件
  - 编辑大纲 → 确认 → SSE 收到 `content_chunk` 事件 × N → `content_completed`
  - 最终文章含 Markdown 正文 + 配图占位符
- **MIRROR**: Phase 1 集成验证模式
- **IMPORTS**: N/A
- **GOTCHA**: 确保 DASHSCOPE_API_KEY 已配置，否则 Agent 调用失败
- **VALIDATE**: 完整走完标题→大纲→正文流程，数据库文章状态为 completed

---

## Testing Strategy

### Unit Tests

| Test | Input | Expected Output |
|---|---|---|
| TitleGeneratorAgent | topic="AI未来", style="科技" | 3-5 titles with analysis |
| OutlineGeneratorAgent | title="AI改变世界", supplement="" | structured outline with 3+ chapters |
| ContentGeneratorAgent | outline={...} | Markdown with image placeholders |
| ImageAnalyzerAgent | content with placeholders | analysis per placeholder |
| WorkshopOrchestrator | valid state transitions | state advances correctly |
| WorkshopOrchestrator | invalid transition (skip state) | throws BusinessException |
| SseBroadcaster | EventStream with data | SSE formatted event |

### Edge Cases Checklist
- [ ] 选题为空 → 验证错误
- [ ] 风格为空 → 使用默认风格
- [ ] 标题生成仅返回 1 个 → 降级处理，不阻塞流程
- [ ] 大纲为空 → 重试或错误
- [ ] SSE 连接中断 → 前端自动重连，后端检测断连清理
- [ ] Agent 调用超时 → 标记 FAILED，SSE 推送 error 事件
- [ ] 同时多个创作会话 → Redis key 隔离正确
- [ ] 用户关闭浏览器后重开 → 根据 article.status 恢复状态

---

## Validation Commands

### Static Analysis
```bash
php -l app/Enum/WorkshopState.php app/Contract/AgentInterface.php \
  app/Service/Agent/*.php app/Service/WorkshopOrchestrator.php \
  app/Service/SseBroadcaster.php app/Controller/Workshop*.php
```
EXPECT: No syntax errors

### Frontend Type Check
```bash
cd frontend && ./node_modules/.bin/vue-tsc --noEmit
```
EXPECT: Zero type errors

### Database Migration
```bash
docker exec nlp-backend php bin/hyperf.php migrate
```
EXPECT: articles 表新增 4 列

### SSE Test
```bash
curl -N http://localhost:9501/api/workshop/1/stream?token=$TOKEN
```
EXPECT: 持久连接，收到 SSE 事件流

### API Test
```bash
# 创建创作会话
curl -X POST http://localhost:9501/api/workshop/create \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"topic":"AI如何改变教育","style":"科普"}'
```
EXPECT: 返回 article_id 和初始状态

### Manual Validation
- [ ] 浏览器访问 `/workshop` → 输入选题 → 点击开始创作
- [ ] 标题卡片展示 → 点击选择 → 可补充描述 → 确认
- [ ] 大纲展示 → 编辑节点文本 → 确认
- [ ] 正文流式展示 → Markdown 实时渲染 → 配图进度 → 完成
- [ ] 完整文章预览 → 内容 + 配图占位符

---

## Acceptance Criteria
- [ ] 输入选题后 5 个 Agent 按序执行
- [ ] 标题生成 3-5 个选项，用户可选择 + 补充描述
- [ ] 大纲生成后可编辑，确认后继续
- [ ] 正文通过 SSE 流式推送到前端
- [ ] 正文包含配图占位符（每 2-3 段一个）
- [ ] ImageAnalyzerAgent 分析每个占位符的配图策略
- [ ] ParallelImageGenerator 占位实现（mock URL）
- [ ] 状态机覆盖所有 9 个状态的正向和错误路径
- [ ] 前端创作工坊三阶段 UI 可用
- [ ] SSE 连接支持断线重连

## Completion Checklist
- [ ] Agent 类遵循统一 AgentInterface 接口
- [ ] WorkshopOrchestrator 状态转换校验完整
- [ ] SSE 事件格式符合规范
- [ ] 前端 Pinia store 管理 SSE 生命周期
- [ ] Redis 上下文在完成后持久化
- [ ] 错误处理不泄露系统信息
- [ ] 无硬编码 prompt（使用常量或配置）
- [ ] 用户只能操作自己的文章

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| DashScope 输出格式不稳定（非 JSON） | H | M | Prompt 强约束 + 解析降级 + 重试机制 |
| SSE 消息时序错乱 | M | H | Redis Pub/Sub 单频道保证有序 |
| 状态机并发问题 | L | H | 文章级锁（Redis SETNX）防止并发操作 |
| 流式正文 token 消耗过高 | M | M | 设置 max_tokens 上限 + 按大纲章节分段生成 |
| 前端 EventSource 兼容性 | L | L | EventSource API 广泛支持，IE 不考虑 |

## Notes

- 这是整个项目的核心 Phase，XL 复杂度，Phase 4/5 均可并行开发
- ImageAnalyzerAgent 的输出是 Phase 4 配图系统的输入，注意接口契约对齐
- ParallelImageGenerator 在 Phase 3 为占位实现，Phase 4 替换为真实策略模式
- 建议使用 Redis 存储创作过程中的临时上下文，避免数据库频繁写入
- 前端 markdown-it + highlight.js 已在 Phase 1 安装，直接使用
- Agent prompt 模板建议定义为常量类 `App\Constants\AgentPrompts`，便于调优
