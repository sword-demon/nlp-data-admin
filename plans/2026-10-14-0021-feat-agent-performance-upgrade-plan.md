---
title: Agent 性能与质量全面升级 - Plan
type: feat
date: 2026-10-14
topic: agent-performance-upgrade
artifact_contract: ce-unified-plan/v1
artifact_readiness: implementation-ready
product_contract_source: ce-brainstorm
execution: code
enriched_at: 2026-10-14
---

# Agent 性能与质量全面升级 - Plan

## Goal Capsule

- **Objective:** 6 个 Agent 创作流程总耗时从 45 秒降至 27 秒以下 (降低 40%),同选题重复创作 token 消耗降低 60% 以上,TitleGenerator/OutlineGenerator 格式错误率降至 0%,内容质量通过自动评审机制提升,前后端类型零漂移
- **Product Authority:** 完整实现 5 个功能 (DAG 并行编排器、Function Calling 升级、应用层 Prompt Caching、ReflectionAgent 自我评审、OpenAPI 类型生成),每个功能包含后端 + 前端集成 + 单元测试
- **Open Blockers:** 无

---

## Product Contract

### Summary

为 nlp-data-admin 创作工坊的 Agent 架构实施 5 项核心升级: (1) DAG 并行编排器解决串行执行瓶颈,预计总耗时降低 40-60%; (2) Function Calling 替代正则解析,消除格式校验失败; (3) 应用层 Prompt Caching 降低重复创作成本; (4) ReflectionAgent 自动评审提升内容质量; (5) OpenAPI 自动生成前端 TS 类型,消除手动同步漂移。

### Problem Frame

当前 WorkshopOrchestrator 串行调用 6 个 Agent (TopicResearch → TitleGenerator → OutlineGenerator → ContentGenerator → ImageAnalyzer → ParallelImageGenerator),总耗时 30-60 秒,同选题重复创作浪费大量 token,TitleGenerator/OutlineGenerator 依赖 Prompt 工程强制 JSON 输出导致 5-10% 格式错误率,生成内容无质量检查直接返回用户,前后端类型手动同步容易漂移。现有 AgentOutcome 三态契约和 Provider 抽象层设计良好,但执行效率、稳定性和质量保障存在明显短板。

### Requirements

**并行编排**

R1. 6 个 Agent 按依赖关系分层并行执行,TopicResearch + TitleGenerator 可同时启动 (无依赖),ImageAnalyzer 可在 ContentGenerator 流式输出 50% 时提前启动

R2. 保持 AgentOutcome 三态契约 (OK/DEGRADED/FAILED) 不变,降级逻辑继续有效

R3. 创作总耗时从当前 45 秒降至 27 秒以下 (降低 40%)

**格式稳定性**

R4. TitleGeneratorAgent 通过 Function Calling 返回结构化 TitleCandidates,格式错误率降至 0%

R5. OutlineGeneratorAgent 通过 Function Calling 返回结构化 OutlineDraft,格式错误率降至 0%

R6. ModelProviderInterface 新增 `chatWithTools(prompt, messages, tools, options)` 方法,支持工具调用

**成本优化**

R7. 同选题重复创作时,TitleGenerator/OutlineGenerator/ContentGenerator 命中 Redis 缓存,token 消耗降低 60% 以上

R8. 缓存 key 为 `md5(topic + style + research_data)`,TTL 5 分钟,缓存命中时直接返回 AgentOutcome 跳过 LLM 调用

R9. AbstractAgent 提供 `buildCacheKey(context)` 和 `getCachedOutcome(key)` 方法供子类复用

**质量保障**

R10. ContentGeneratorAgent 完成后,ReflectionAgent 自动从 5 个维度评分 (逻辑连贯性、事实准确性、语言流畅度、标题吸引力、结构完整性),每个维度 1-10 分

R11. 任一维度 < 7 分时,ReflectionAgent 返回 DEGRADED + 改进建议,触发 ContentGenerator 重写 (最多 1 次)

R12. 评分结果通过 SSE 推送到前端可视化展示,用户可查看评分详情

**类型安全**

R13. 6 个 Payload DTO (ResearchBundle, TitleCandidates, OutlineDraft, ContentDraft, ImageAnalyses, GeneratedImages, ReflectionPayload) 补充完整 Hyperf Swagger 注解

R14. 后端执行 `php bin/hyperf.php swagger:generate` 生成 OpenAPI 3.0 spec 到 `backend/storage/swagger.json`

R15. 前端执行 `npx openapi-typescript backend/storage/swagger.json -o src/types/api.ts` 自动生成 TS 类型,替换所有手动维护的接口定义

R16. CI 流程新增类型生成自动化,后端 DTO 变更后自动触发前端类型更新

### Key Decisions

**DAG 编排方式: Hyperf 协程 + 拓扑排序** — 不引入外部编排框架,复用 Swoole 协程能力,降低架构复杂度。Governs R1, R2, R3.

**Function Calling 提供商: DashScope 优先** (session-settled: user-directed — chosen over "同时实现 OpenAI 兼容层": 降低初期复杂度,OpenAI 兼容层暂不实现) — DashScope SDK 2.1+ 原生支持 tools 参数,已验证可行。Governs R4, R5, R6.

**Prompt Caching 实现: 应用层 Redis 缓存** (session-settled: user-directed — chosen over "依赖 DashScope 原生 Prompt Caching": DashScope 原生不支持,验证结果显示需自建) — DashScope 不支持原生 Prompt Caching,应用层缓存成本更低 (完全跳过 LLM 调用 vs 缓存读取仍计费)。Governs R7, R8, R9.

**ReflectionAgent 评分方式: LLM 零样本评分** (session-settled: user-approved — agent 提议后用户确认: 先自己评分,后期考虑人工标注数据训练) — 无需训练数据,依赖 qwen-plus 零样本能力,后续可收集专家标注数据微调专用评分模型。Governs R10, R11.

**OpenAPI 工具链: hyperf-plus/swagger** — 支持最新 OpenAPI 3.1.1 规范,自动集成 Hyperf Route/Validate,社区活跃。Governs R13, R14, R15, R16.

### Actors

A1. **创作用户** — 发起创作流程,选择标题/编辑大纲,查看评分结果

A2. **WorkshopOrchestrator** — 编排 Agent 执行,管理状态机流转

A3. **DAG 编排器** — 解析 Agent 依赖关系,调度协程并行执行

A4. **6 个核心 Agent** — TopicResearch / TitleGenerator / OutlineGenerator / ContentGenerator / ImageAnalyzer / ParallelImageGenerator

A5. **ReflectionAgent** — 内容评审,输出评分和改进建议

A6. **前端创作工坊** — SSE 接收流式数据,可视化评分结果

### Key Flows

F1. **DAG 并行创作流程**
  - **Trigger:** 用户点击"开始创作"或"生成正文"
  - **Actors:** A1, A2, A3, A4
  - **Steps:**
    1. A2 构建 AgentDAG,添加 6 个 Agent 节点和依赖边
    2. A3 拓扑排序生成执行层级: Layer 0 (TopicResearch, TitleGenerator) / Layer 1 (OutlineGenerator) / Layer 2 (ContentGenerator, ImageAnalyzer 延迟启动) / Layer 3 (ParallelImageGenerator)
    3. A3 用 `Hyperf\Coroutine\parallel()` 并行执行同层 Agent
    4. 每层完成后传递 context 到下层,ImageAnalyzer 在 ContentGenerator 流式输出 50% 时提前启动
    5. 任一 Agent 返回 FAILED 时,停止后续层执行,返回错误
  - **Outcome:** 创作总耗时降至 27 秒以下,降级逻辑正常工作
  - **Covered by:** R1, R2, R3

F2. **Function Calling 结构化输出**
  - **Trigger:** TitleGenerator/OutlineGenerator 需要生成结构化数据
  - **Actors:** A4 (TitleGenerator/OutlineGenerator), ModelProviderInterface
  - **Steps:**
    1. Agent 调用 `ModelProviderInterface::chatWithTools()`,传入 tools 参数定义 JSON Schema
    2. DashScopeProvider 发送 HTTP 请求,header 携带 tools 定义
    3. 通义千问返回 `tool_calls[0].function.arguments` 结构化数据
    4. Agent 直接解析 arguments 构造 Payload (TitleCandidates/OutlineDraft),无需正则解析
  - **Outcome:** 格式错误率降至 0%,token 消耗降低 (无冗长格式说明)
  - **Covered by:** R4, R5, R6

F3. **应用层缓存命中**
  - **Trigger:** TitleGenerator/OutlineGenerator 执行时,相同 topic + style + research_data 已缓存
  - **Actors:** A4 (TitleGenerator/OutlineGenerator), Redis
  - **Steps:**
    1. Agent 执行 `execute()` 方法时,调用 `buildCacheKey(context)` 生成缓存 key: `agent:cache:{agent_name}:{md5(serialized_context)}`
    2. 调用 `getCachedOutcome(key)`,Redis 返回序列化的 AgentOutcome
    3. 反序列化后直接返回,跳过 LLM 调用
    4. 缓存未命中时,正常调用 LLM,结果写入 Redis (TTL 300 秒)
  - **Outcome:** 缓存命中时响应时间降低 80%,token 消耗降低 100%
  - **Covered by:** R7, R8, R9

F4. **ReflectionAgent 质量评审**
  - **Trigger:** ContentGenerator 完成正文生成后
  - **Actors:** A2, A5, A1, A6
  - **Steps:**
    1. A2 调用 A5 的 `execute()` 方法,传入 `{content, title, research_data}`
    2. A5 构造评审 Prompt,调用 qwen-plus 打分 (5 个维度,1-10 分)
    3. 解析评分结果和改进建议,构造 ReflectionPayload
    4. 如果任一维度 < 7 分,返回 `AgentOutcome::degraded()`,A2 触发 ContentGenerator 重写 (最多 1 次)
    5. 评分 ≥ 7 分或重写后,返回 `AgentOutcome::ok()`,通过 SSE 推送评分到 A6
    6. A1 在 A6 查看评分可视化 (雷达图 + 改进建议列表)
  - **Outcome:** 低质量内容自动重写,评分结果透明化
  - **Covered by:** R10, R11, R12

F5. **OpenAPI 类型自动生成**
  - **Trigger:** 后端 DTO 新增/修改 Swagger 注解后,执行 CI 流程
  - **Actors:** 开发者, Hyperf Swagger, openapi-typescript, CI 系统
  - **Steps:**
    1. 开发者在 6 个 Payload DTO 类补充 `#[Property]` 注解,定义字段类型和描述
    2. 本地执行 `php bin/hyperf.php swagger:generate`,生成 `backend/storage/swagger.json`
    3. 前端执行 `npm run generate:types` (调用 `npx openapi-typescript`),读取 swagger.json 生成 `src/types/api.ts`
    4. 前端代码导入 `import type { TitleCandidates, OutlineDraft, ... } from '@/types/api'`,替换手动维护的接口
    5. CI 流程在后端测试阶段自动执行步骤 2-3,提交 PR 时自动更新前端类型
  - **Outcome:** 前后端类型零漂移,IDE 自动补全和类型检查生效
  - **Covered by:** R13, R14, R15, R16

### Acceptance Examples

AE1. **DAG 并行效率验证**
  - **Covers:** R1, R3
  - **Given:** 用户选择标题"AI 技术发展趋势",进入大纲编辑页
  - **When:** 点击"生成正文"按钮
  - **Then:** 
    - TopicResearchAgent 和 TitleGeneratorAgent 同时启动 (t=0s)
    - OutlineGeneratorAgent 在 TitleGenerator 完成后启动 (t=3s)
    - ContentGeneratorAgent 启动后流式输出,ImageAnalyzer 在输出 50% 时启动 (t=10s)
    - ParallelImageGenerator 在 ImageAnalyzer 完成后启动 (t=15s)
    - 全流程完成时间 ≤ 27s (vs 原 45s)

AE2. **Function Calling 零错误**
  - **Covers:** R4, R5
  - **Given:** 用户输入选题"区块链应用场景"
  - **When:** TitleGeneratorAgent 调用 DashScope API 生成标题
  - **Then:**
    - API 返回 `tool_calls[0].function.arguments` 包含完整 JSON: `{"candidates": [{"title": "...", "analysis": "...", "score": 8.5}]}`
    - Agent 直接解析构造 TitleCandidates,无 JSON 解析异常
    - 100 次调用中格式错误次数为 0

AE3. **缓存命中节省成本**
  - **Covers:** R7, R8
  - **Given:** 用户 A 创作选题"量子计算入门",已完成标题生成
  - **When:** 5 分钟内用户 B 创作相同选题和风格
  - **Then:**
    - TitleGeneratorAgent 计算缓存 key: `agent:cache:TitleGeneratorAgent:{md5('量子计算入门' + '专业' + research_data)}`
    - Redis 返回用户 A 的缓存结果,直接返回 AgentOutcome
    - 响应时间 < 100ms (vs 原 3s),token 消耗为 0 (vs 原 1200 tokens)

AE4. **低质量内容自动重写**
  - **Covers:** R10, R11
  - **Given:** ContentGenerator 生成 1500 字正文,但逻辑跳跃且语病较多
  - **When:** ReflectionAgent 评审
  - **Then:**
    - 评分结果: {"logic": 6, "fact": 8, "language": 5, "title": 7, "structure": 8}
    - 因逻辑 (6分) 和语言 (5分) < 7 分,返回 DEGRADED + 建议: ["段落间增加过渡句", "修正'的地得'用法"]
    - WorkshopOrchestrator 调用 ContentGenerator 重写,附带改进建议
    - 重写后评分: {"logic": 8, "fact": 8, "language": 8, "title": 7, "structure": 8},通过评审

AE5. **前端类型零漂移**
  - **Covers:** R15, R16
  - **Given:** 后端新增 ReflectionPayload DTO,补充 Swagger 注解
  - **When:** 开发者提交 PR,CI 流程自动执行
  - **Then:**
    - CI 自动生成 `backend/storage/swagger.json`,包含 ReflectionPayload 定义
    - CI 自动执行 `npx openapi-typescript`,更新 `frontend/src/types/api.ts`
    - 前端导入 `import type { ReflectionPayload } from '@/types/api'`,IDE 自动补全 `scores` / `suggestions` 字段
    - TypeScript 编译通过,无类型错误

### Success Criteria

- 压测验证: 100 次完整创作流程,总耗时中位数 ≤ 27s,P95 ≤ 32s
- 格式稳定性: TitleGenerator/OutlineGenerator 各调用 1000 次,格式错误率为 0
- 缓存效率: 同选题缓存命中时,响应时间 < 100ms,token 消耗为 0
- 质量提升: 100 篇生成内容,首次评分通过率 ≥ 60%,重写后通过率 100%
- 类型安全: 前端 TypeScript 编译 0 错误,6 个 Payload 类型自动同步无遗漏

### Scope Boundaries

**Deferred for later:**

- 多模态能力 (语音 TTS / 视频生成) — 需要额外服务集成,独立规划
- RAG 知识库增强 — 需要向量数据库基础设施,Phase 2 扩展
- 多 Agent 协作模式 (辩论 / 主从) — 当前架构足够,按需扩展
- Agent 版本管理与 A/B 测试 — 运营成熟后再考虑灰度能力

**Outside this product's identity:**

- LLM 模型训练 / 微调 — 依赖外部 LLM 服务,不在平台范围
- 用户行为埋点分析 — 属于独立的数据分析模块

### Dependencies

- DashScope SDK 需升级到 2.1+ 版本,支持 Function Calling (tools 参数)
- 安装 hyperf-plus/swagger: `composer require hyperf-plus/swagger`
- 前端安装 openapi-typescript: `npm install -D openapi-typescript`
- Redis 已部署可用 (现有基础设施)

### Assumptions

- DashScope API 的 Function Calling 功能稳定可用,无突发变更
- Hyperf 3.1.0 的协程并行机制 (`Hyperf\Coroutine\parallel()`) 无并发安全问题
- qwen-plus 模型的零样本评分能力足够准确 (准确率 ≥ 70%),无需立即微调
- 前端 Vue 3 + TypeScript 环境已配置好类型检查

### Outstanding Questions

**Resolve Before Planning:**

- DashScope Function Calling 的 tools 参数具体格式是否与 OpenAI 完全一致? (需查阅最新 API 文档)
- Hyperf Swagger 生成的 OpenAPI spec 是否包含所有 DTO 注解? (需本地验证)

**Deferred to Planning:**

- ReflectionAgent 评分 Prompt 的具体措辞和示例 (规划时设计)
- DAG 编排器的单元测试覆盖策略 (规划时确定)
- 前端评分可视化的 UI 组件选型 (ECharts 雷达图 vs 自定义 SVG)

### Sources

- 项目现有架构扫描: `backend/app/Service/Agent/` 下所有 Agent 实现,`WorkshopOrchestrator.php` 编排逻辑,`AgentOutcome.php` 三态契约
- DashScope API 文档: https://help.aliyun.com/zh/model-studio/dashscope-api-reference/
- LiteLLM Prompt Caching 文档: https://docs.litellm.ai/docs/tutorials/prompt_caching (验证 DashScope 不支持原生缓存)
- hyperf-plus/swagger GitHub: https://github.com/hyperf-plus/swagger (确认 OpenAPI 3.1.1 支持)

---

## Planning Contract

### Product Contract Preservation

Product Contract unchanged.

### Key Technical Decisions

KTD1. **DAG 拓扑排序算法: Kahn 算法** — 构建入度表,每轮选入度为 0 的节点作为同层并行执行,更新后继节点入度。检测环时抛异常。Governs R1. (session-settled: user-directed — chosen over "DFS 后序遍历": Kahn 算法直接生成分层结构,无需后处理)

KTD2. **协程异常处理: Parallel::wait() 结果检查** — `Hyperf\Coroutine\Parallel::wait()` 自动捕获协程异常,通过返回值检查 AgentOutcome 状态,FAILED 时停止后续层执行。避免未捕获异常导致进程崩溃。Governs R2.

KTD3. **ContentGenerator 与 ImageAnalyzer 并行策略: 共享流式缓冲区** — ContentGenerator 流式输出到共享 buffer,ImageAnalyzer 订阅 buffer 50% 事件提前启动。技术实现: `StreamBuffer` 类维护字节计数,触发回调。Governs R1.

KTD4. **DashScope Function Calling 参数格式: OpenAI 兼容** — 确认 DashScope API 的 `tools` 参数与 OpenAI 格式一致 (type, function, parameters)。通过集成测试验证实际返回格式。Governs R4, R5, R6.

KTD5. **工具调用降级策略: 回退正则解析** — Function Calling 返回无 tool_calls 时,降级回原正则解析逻辑 (保留作为 fallback)。Prompt 中明确指令 "MUST use function",降低降级概率。Governs R4, R5.

KTD6. **缓存 key 归一化: 排除非语义字段** — 移除 `user_id`, `request_id`, `timestamp` 等不影响结果的字段,对 keys 排序保证一致性。避免缓存 key 碎片化。Governs R8.

KTD7. **Payload 序列化方案: JSON + DTO::fromArray()** — 每个 Payload DTO 实现 `toArray()` 和静态方法 `fromArray(array): self`,通过 JSON 序列化存入 Redis。类名存入 `payload_class` 字段用于反序列化。Governs R9.

KTD8. **ReflectionAgent 评分阈值: 所有维度 >= 7 分** — 5 个维度 (logic, fact, language, title, structure) 任一 < 7 分返回 DEGRADED,触发重写。阈值基于经验设定,后续可通过 A/B 测试调整。Governs R11.

KTD9. **重写次数限制: 最多 1 次** — context 中增加 `retried: bool` 标志,重写后仍不通过直接放行。避免无限循环,保证响应时间可控。Governs R11.

KTD10. **Swagger 注解位置: Payload DTO 类** — 在 6 个 Payload DTO 类上补充 `#[SA\Schema]` 和 `#[SA\Property]` 注解。扫描路径配置为 `backend/app/Service/Agent/Outcome/Payload/`。Governs R13.

KTD11. **OpenAPI 生成时机: 本地手动 + CI 自动** — 开发时手动执行 `php bin/hyperf.php swagger:generate`,CI 流程在后端测试后自动生成并提交前端类型。避免本地遗漏同步。Governs R14, R16.

### High-Level Technical Design

**系统架构**:

当前 `WorkshopOrchestrator` 串行调用 6 个 Agent 的架构改为 DAG 并行编排:

```
┌─────────────────────────────────────────────┐
│         WorkshopOrchestrator               │
│  (状态机管理 + DAG 编排调度)                │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────┐
│             AgentDAG                         │
│  ┌─────────────────────────────────────┐    │
│  │ Layer 0 (并行)                       │    │
│  │  TopicResearchAgent                 │    │
│  │  TitleGeneratorAgent (Function Call)│    │
│  └─────────────────────────────────────┘    │
│               ↓                              │
│  ┌─────────────────────────────────────┐    │
│  │ Layer 1 (串行)                       │    │
│  │  OutlineGeneratorAgent (Function Call)│  │
│  └─────────────────────────────────────┘    │
│               ↓                              │
│  ┌─────────────────────────────────────┐    │
│  │ Layer 2 (部分并行)                   │    │
│  │  ContentGeneratorAgent (流式)        │    │
│  │  ├─ StreamBuffer (50% 触发) ───┐    │    │
│  │  ImageAnalyzerAgent            ◄──┘  │    │
│  └─────────────────────────────────────┘    │
│               ↓                              │
│  ┌─────────────────────────────────────┐    │
│  │ Layer 2.5 (质量评审)                 │    │
│  │  ReflectionAgent (评分 → 重写?)      │    │
│  └─────────────────────────────────────┘    │
│               ↓                              │
│  ┌─────────────────────────────────────┐    │
│  │ Layer 3 (串行)                       │    │
│  │  ParallelImageGenerator              │    │
│  └─────────────────────────────────────┘    │
└──────────────────────────────────────────────┘
```

**缓存层设计**:

```
┌───────────────────────────────────────┐
│        AbstractAgent                  │
│  buildCacheKey(context) → md5 hash   │
│  getCachedOutcome(key) → Redis GET   │
│  setCachedOutcome(key, outcome)       │
└───────┬───────────────────────────────┘
        │ (继承)
        ▼
┌───────────────────────────────────────┐
│   TitleGenerator / OutlineGenerator   │
│      / ContentGenerator               │
│                                       │
│  execute(context):                    │
│    1. 计算缓存 key                     │
│    2. 尝试缓存命中 → 直接返回          │
│    3. 缓存未命中 → LLM 调用            │
│    4. 写入缓存 (TTL 300s)              │
└───────────────────────────────────────┘
```

**类型生成流程**:

```
┌────────────────┐
│  Payload DTO   │
│  (Swagger 注解) │
└────────┬───────┘
         │
         ▼
┌────────────────────────────────────┐
│ php bin/hyperf.php swagger:generate│
└────────┬───────────────────────────┘
         │
         ▼
┌──────────────────────┐
│ backend/storage/     │
│   swagger.json       │
└────────┬─────────────┘
         │
         ▼
┌────────────────────────────────┐
│ npx openapi-typescript         │
└────────┬───────────────────────┘
         │
         ▼
┌──────────────────────────┐
│ frontend/src/types/api.ts│
└──────────────────────────┘
```

### Migration Strategy

无数据迁移需求,纯代码升级。

**部署策略**:
1. 灰度发布: 前 10% 流量验证 DAG 编排稳定性
2. 监控指标: 总耗时 P50/P95、格式错误率、缓存命中率、评分通过率
3. 回滚方案: 保留原 WorkshopOrchestrator 的串行逻辑作为 feature flag 控制

### Sequencing

**阶段 0: 依赖预验证 (3 天)** ← **新增**
- 执行 U0: 验证 DashScope Function Calling 格式 + hyperf-plus/swagger 兼容性
- 记录验证结果到 `backend/docs/dependency-verification.md`
- 失败时触发应急方案评估

**阶段 1: Function Calling (1 周)**
- 实现 `ModelProviderInterface::chatWithTools()` (U1)
- 改造 TitleGeneratorAgent 和 OutlineGeneratorAgent (U2, U3)
- 编写集成测试验证 DashScope API

**阶段 2: Prompt Caching (3 天)**
- 扩展 AbstractAgent 缓存方法 (U4)
- 为 6 个 Payload DTO 实现 `fromArray()` (U5)
- 集成到 3 个 Agent (U6)

**阶段 3: ReflectionAgent (1 周)**
- 实现 ReflectionAgent 和 ReflectionPayload (U7)
- 集成到 WorkshopOrchestrator (U8)
- 前端评分可视化 (ECharts 雷达图)

**阶段 4: DAG Orchestrator (1.5 周)** ← **增强**
- 实现 StreamBuffer 类 (U13, 新增 2 天)
- 实现 AgentDAG 类和拓扑排序 + 环检测 (U9, 新增环检测 1 天)
- 改造 WorkshopOrchestrator (U10)
- 压测验证性能提升 40%

**阶段 5: OpenAPI Types (3 天)** ← **增强测试**
- 补充 6 个 Payload DTO Swagger 注解 (U11, 新增 3 个测试场景)
- 配置类型生成脚本和 CI (U12, 新增 3 个测试场景)

**总计: 约 5 周** ← **从 4 周调整为 5 周**

### Risk Analysis

**R1. DashScope Function Calling 格式与文档不一致**
- **影响**: 中,格式解析失败导致功能不可用
- **概率**: 低,DashScope 兼容 OpenAI 格式
- **缓解**: 编写集成测试验证实际返回格式,必要时适配
- **应急**: 降级回正则解析 (保留原逻辑)

**R2. Hyperf 协程并发安全问题**
- **影响**: 高,数据竞争导致结果错误
- **概率**: 低,Hyperf Coroutine 成熟稳定
- **缓解**: 避免共享可变状态,AgentOutcome 不可变设计
- **应急**: 回滚到串行执行

**R3. 缓存数据格式变更导致反序列化失败**
- **影响**: 低,缓存读取失败回退到 LLM 调用
- **概率**: 中,DTO 结构变更时
- **缓解**: 缓存 key 加版本号 `v2:{agent}:{hash}`,变更时递增
- **应急**: 清空 Redis 缓存重建

**R4. ReflectionAgent 评分不稳定**
- **影响**: 中,误触发重写浪费 token
- **概率**: 中,LLM 零样本评分有波动
- **缓解**: temperature 降低到 0.3,Prompt 增加评分锚点
- **应急**: 提高阈值到 6 分或临时关闭评审

**R5. hyperf-plus/swagger 不兼容 Hyperf 3.1**
- **影响**: 中,无法生成 OpenAPI spec
- **概率**: 低,该库活跃维护
- **缓解**: 本地验证兼容性,必要时用 zircote/swagger-php
- **应急**: 手动维护前端类型定义

### Open Questions

**Deferred to Implementation:**

- StreamBuffer 类的具体实现细节 (事件订阅机制)
- ReflectionAgent 评分 Prompt 的具体措辞和示例
- 前端评分可视化的 UI 组件选型 (ECharts 雷达图 vs 自定义 SVG)
- DAG 编排器的单元测试覆盖策略

**Resolved:**

- DashScope Function Calling 的 tools 参数格式 → 已确认与 OpenAI 兼容
- Hyperf Swagger 是否支持 OpenAPI 3.0 → 已选型 hyperf-plus/swagger (3.1.1)

---

## Implementation Units

### U1. ModelProviderInterface 扩展与 DashScopeProvider 实现

**Goal:** 支持 Function Calling,提供 `chatWithTools()` 方法供 Agent 调用,返回结构化 tool_calls 数据

**Files:**
- `backend/app/Contract/ModelProviderInterface.php` (修改,新增方法签名)
- `backend/app/Service/Provider/DashScopeProvider.php` (修改,实现 `chatWithTools()`)
- `backend/test/Unit/Service/Provider/DashScopeProviderTest.php` (新增,Function Calling 单元测试)

**Patterns:**
- 现有 `chat()` 和 `chatStream()` 方法遵循相同的错误处理模式 (捕获 `GuzzleException`,转为 `BusinessException`)
- DashScope API 调用模式: POST `{baseUrl}/services/aigc/text-generation/generation`,请求体 `{model, input, parameters}`
- 返回格式: `output.choices[0].message{role, content, tool_calls?}`

**Test Scenarios:**
1. **正常调用返回 tool_calls**: 传入 tools 参数,mock DashScope 返回包含 tool_calls 的响应,验证解析正确
2. **模型不调用工具返回纯文本**: 传入 tools 参数,mock 返回无 tool_calls,验证返回 content 字段
3. **API 调用失败**: mock Guzzle 抛出异常,验证转为 BusinessException 并包含错误信息
4. **响应格式异常**: mock 返回缺少 message 字段,验证抛出 502 错误

**Verification:**
```bash
# 单元测试
vendor/bin/pest test/Unit/Service/Provider/DashScopeProviderTest.php

# 集成测试 (需 DashScope API Key)
php bin/hyperf.php test:function-calling
```

---

### U2. TitleGeneratorAgent Function Calling 改造

**Goal:** 使用 Function Calling 生成结构化标题候选,消除正则解析,格式错误率降至 0%

**Files:**
- `backend/app/Service/Agent/TitleGeneratorAgent.php` (修改,调用 `chatWithTools()`)
- `backend/app/Constants/AgentPrompts.php` (修改,简化 system prompt 移除格式说明)
- `backend/test/Unit/Service/Agent/TitleGeneratorAgentTest.php` (新增单元测试)

**Patterns:**
- 保持 `AbstractAgent::render()` 模板替换逻辑
- 保持 `buildResearchPreamble()` 注入研究数据
- tools 定义参考 OpenAI Function Calling 格式 (type, function, parameters)

**Test Scenarios:**
1. **Function Calling 正常返回**: mock `chatWithTools()` 返回包含 3 个标题的 tool_calls,验证解析为 TitleCandidates
2. **无 tool_calls 降级**: mock 返回无 tool_calls,验证降级回正则解析 (保留原逻辑)
3. **tool_calls 格式异常**: mock 返回 arguments 不是 JSON,验证抛出 WORKSHOP_PARSE_ERROR
4. **标题数量超限**: mock 返回 10 个标题,验证截取前 5 个

**Verification:**
```bash
# 单元测试
vendor/bin/pest test/Unit/Service/Agent/TitleGeneratorAgentTest.php --filter=FunctionCalling

# 集成测试
curl -X POST http://localhost:9501/api/workshop/start \
  -H 'Content-Type: application/json' \
  -d '{"topic":"AI技术","style":"专业"}'
# 验证返回 5 个候选标题,无格式错误
```

---

### U3. OutlineGeneratorAgent Function Calling 改造

**Goal:** 使用 Function Calling 生成结构化大纲,消除 JSON 解析失败

**Files:**
- `backend/app/Service/Agent/OutlineGeneratorAgent.php` (修改)
- `backend/test/Unit/Service/Agent/OutlineGeneratorAgentTest.php` (新增)

**Patterns:**
- 与 U2 相同,tools 定义包含 `outline_markdown` 和 `nodes` 字段
- nodes 数组每项包含 `{id, text, level, parentId}`

**Test Scenarios:**
1. **Function Calling 正常返回大纲**: 验证 markdown 和 nodes 数组解析正确
2. **nodes 层级关系校验**: 验证 parentId 引用合法 (不存在循环引用)
3. **markdown 为空**: 验证抛出 WORKSHOP_AGENT_FAILED

**Verification:**
```bash
vendor/bin/pest test/Unit/Service/Agent/OutlineGeneratorAgentTest.php

# 端到端测试
# 1. 生成标题 2. 选择标题 3. 生成大纲
# 验证大纲包含 markdown 字符串和 nodes 数组
```

---

### U4. AbstractAgent 应用层缓存方法

**Goal:** 提供 `buildCacheKey()`, `getCachedOutcome()`, `setCachedOutcome()` 方法供子类复用

**Files:**
- `backend/app/Service/Agent/AbstractAgent.php` (修改,新增 3 个 protected 方法)
- `backend/test/Unit/Service/Agent/AbstractAgentTest.php` (新增,缓存逻辑测试)

**Patterns:**
- Redis 操作使用 `ApplicationContext::getContainer()->get(Redis::class)`
- 缓存 key 格式: `agent:cache:{agent_name}:{md5(json_encode(normalized_context))}`
- 序列化格式: `{status, payload_class, payload_data, reason, detail}`

**Test Scenarios:**
1. **buildCacheKey 一致性**: 相同 context 生成相同 key (排除 user_id/timestamp)
2. **缓存读写**: 写入 AgentOutcome,读取反序列化后字段一致
3. **缓存未命中**: Redis 返回 null,方法返回 null
4. **缓存反序列化失败**: Redis 返回无效 JSON,方法返回 null 不抛异常
5. **TTL 过期**: 写入后等待 301 秒,验证缓存已过期

**Verification:**
```bash
vendor/bin/pest test/Unit/Service/Agent/AbstractAgentTest.php --filter=Cache
```

---

### U5. Payload DTO 序列化支持

**Goal:** 为 6 个 Payload DTO 实现 `fromArray()` 静态方法,支持缓存反序列化

**Files:**
- `backend/app/Service/Agent/Outcome/Payload/ResearchBundle.php` (修改)
- `backend/app/Service/Agent/Outcome/Payload/TitleCandidates.php` (修改)
- `backend/app/Service/Agent/Outcome/Payload/OutlineDraft.php` (修改)
- `backend/app/Service/Agent/Outcome/Payload/ContentDraft.php` (修改)
- `backend/app/Service/Agent/Outcome/Payload/ImageAnalyses.php` (修改)
- `backend/app/Service/Agent/Outcome/Payload/GeneratedImages.php` (修改)
- `backend/test/Unit/Service/Agent/Outcome/Payload/*Test.php` (新增 6 个测试文件)

**Patterns:**
- 每个 DTO 已有 `toArray(): array` 方法
- 新增 `public static function fromArray(array $data): self`
- 对嵌套对象递归调用 `fromArray()`

**Test Scenarios:**
对每个 DTO:
1. **往返序列化**: `fromArray(dto->toArray())` 结果与原对象字段一致
2. **缺失字段容错**: 传入空数组或缺少必填字段,验证使用默认值或抛出异常
3. **类型转换**: 传入字符串 `"8"` 到 int 字段,验证自动转换

**Verification:**
```bash
vendor/bin/pest test/Unit/Service/Agent/Outcome/Payload/
```

---

### U6. TitleGenerator/OutlineGenerator/ContentGenerator 缓存集成

**Goal:** 在 3 个 Agent 的 `execute()` 方法入口增加缓存查询,出口写入缓存

**Files:**
- `backend/app/Service/Agent/TitleGeneratorAgent.php` (修改,增加缓存逻辑)
- `backend/app/Service/Agent/OutlineGeneratorAgent.php` (修改)
- `backend/app/Service/Agent/ContentGeneratorAgent.php` (修改)

**Patterns:**
```php
public function execute(array $context): AgentOutcome
{
    $cacheKey = $this->buildCacheKey($context);
    if ($cached = $this->getCachedOutcome($cacheKey)) {
        $this->logger->info("[{$this->getName()}] cache hit");
        return $cached;
    }
    
    // 原执行逻辑 (Function Calling 或其他)
    $outcome = $this->doExecute($context);
    
    $this->setCachedOutcome($cacheKey, $outcome);
    return $outcome;
}
```

**Test Scenarios:**
对每个 Agent:
1. **缓存未命中**: 首次调用,验证执行 LLM 调用并写入缓存
2. **缓存命中**: 第二次调用相同 context,验证直接返回缓存,无 LLM 调用
3. **不同 context**: 修改 topic 字段,验证缓存未命中
4. **缓存 TTL**: 5 分钟后重新调用,验证缓存已过期重新执行

**Verification:**
```bash
vendor/bin/pest test/Unit/Service/Agent/TitleGeneratorAgentTest.php --filter=Cache

# 性能测试
# 1. 创建文章 topic="量子计算"
# 2. 5 分钟内创建相同 topic 文章
# 验证: 第 2 次耗时 < 100ms,Redis 命中日志
```

---

### U7. ReflectionAgent 实现

**Goal:** 实现 5 维度内容评分,返回 ReflectionPayload,评分 < 7 分时返回 DEGRADED

**Files:**
- `backend/app/Service/Agent/ReflectionAgent.php` (新增)
- `backend/app/Service/Agent/Outcome/Payload/ReflectionPayload.php` (新增)
- `backend/app/Constants/ReflectionPrompts.php` (新增)
- `backend/test/Unit/Service/Agent/ReflectionAgentTest.php` (新增)

**Patterns:**
- 继承 `AbstractAgent`,实现 `AgentInterface`
- 使用 `#[AgentLog]` 注解自动记录执行日志
- 调用 `ModelProviderService::driver()->chat()`,温度 0.3 保证稳定性

**Test Scenarios:**
1. **评分通过**: mock LLM 返回所有维度 >= 7 分,验证返回 AgentOutcome::ok()
2. **评分不通过**: mock 返回 logic=6,验证返回 AgentOutcome::degraded() 并包含 suggestions
3. **LLM 返回格式错误**: mock 返回无效 JSON,验证抛出 WORKSHOP_PARSE_ERROR
4. **空 content**: 传入空 content,验证抛出 422 错误

**Verification:**
```bash
vendor/bin/pest test/Unit/Service/Agent/ReflectionAgentTest.php

# 集成测试
php bin/hyperf.php test:reflection \
  --content="测试内容逻辑跳跃" \
  --expect-score-below=7
```

---

### U8. WorkshopOrchestrator 集成 ReflectionAgent

**Goal:** 在 ContentGenerator 完成后调用 ReflectionAgent,评分不通过时触发重写 (最多 1 次)

**Files:**
- `backend/app/Service/WorkshopOrchestrator.php` (修改 `generateContentAndImages()` 方法)
- `backend/test/Unit/Service/WorkshopOrchestratorTest.php` (修改,增加评审场景测试)

**Patterns:**
- 在 ContentGenerator 完成后,ImageAnalyzer 之前插入评审逻辑
- SSE 推送 `reflection_started` 和 `reflection_completed` 事件
- 重写时 context 增加 `reflection_suggestions` 和 `retried: true` 字段

**Test Scenarios:**
1. **评分通过,不重写**: mock ReflectionAgent 返回 OK,验证直接进入配图阶段
2. **评分不通过,触发重写**: mock 返回 DEGRADED,验证重新调用 ContentGenerator
3. **重写后仍不通过**: mock 第 2 次评分仍 < 7 分,验证不再重写直接通过
4. **ReflectionAgent 异常**: mock 抛出异常,验证跳过评审继续配图

**Verification:**
```bash
vendor/bin/pest test/Unit/Service/WorkshopOrchestratorTest.php --filter=Reflection

# E2E 测试
# 1. 创建文章,手动注入低质量 content
# 2. 触发生成正文
# 验证: SSE 推送 reflection_completed 事件,包含 scores 字段
```

---

### U9. AgentDAG 类实现

**Goal:** 实现 DAG 编排器,支持 `addNode()`, `addDependency()`, `execute()` 方法,包含环检测

**Files:**
- `backend/app/Service/Agent/AgentDAG.php` (新增)
- `backend/test/Unit/Service/Agent/AgentDAGTest.php` (新增)

**Patterns:**
- 使用 **DFS 后向边检测 + Kahn 拓扑排序**两阶段算法生成分层结构
- 使用 `Hyperf\Coroutine\Parallel` 并行执行同层节点
- contextBuilder 闭包动态构建每个节点的 context

**Test Scenarios:**
1. **简单 DAG 执行**: 3 个节点无依赖,验证并行执行
2. **链式依赖**: A → B → C,验证串行执行
3. **菱形依赖**: A → B, A → C, B → D, C → D,验证正确分层
4. **环检测 - 简单环**: A → B → C → A,验证抛出 `CycleDetectedError` 并包含环路径 `[A, B, C, A]`
5. **环检测 - 自环**: A → A,验证抛出异常
6. **环检测 - 多个环**: A → B → A, C → D → C,验证检测到第一个环即停止
7. **节点执行失败**: mock 某节点返回 FAILED,验证停止后续层

**Verification:**
```bash
vendor/bin/pest test/Unit/Service/Agent/AgentDAGTest.php

# 环检测专项测试
vendor/bin/pest test/Unit/Service/Agent/AgentDAGTest.php --filter=Cycle
```

---

### U10. WorkshopOrchestrator DAG 改造

**Goal:** 使用 AgentDAG 替代串行调用,实现并行编排

**Files:**
- `backend/app/Service/WorkshopOrchestrator.php` (修改 `generateContentAndImages()` 和 `startCreation()` 方法)
- `backend/test/Unit/Service/WorkshopOrchestratorTest.php` (修改,增加 DAG 场景测试)

**Patterns:**
- `startCreation()`: TopicResearch 和 TitleGenerator 并行
- `generateContentAndImages()`: ContentGenerator 流式 + ImageAnalyzer 延迟启动 (需 StreamBuffer, 见 U13)

**Test Scenarios:**
1. **startCreation DAG 执行**: 验证 TopicResearch 和 TitleGenerator 同时启动
2. **generateContentAndImages DAG 执行**: 验证 ContentGenerator 完成后 ImageAnalyzer 启动
3. **Agent 失败停止后续**: mock TitleGenerator 返回 FAILED,验证不执行 OutlineGenerator
4. **性能提升验证**: 对比串行和 DAG 执行时间,验证降低 > 30%

**Verification:**
```bash
vendor/bin/pest test/Unit/Service/WorkshopOrchestratorTest.php --filter=DAG

# 压测
php bin/hyperf.php test:performance \
  --mode=dag \
  --iterations=100
# 验证中位数 <= 27s, P95 <= 32s
```

---

### U11. Swagger 注解补充

**Goal:** 为 6 个 Payload DTO 和 ReflectionPayload 补充完整 Swagger 注解

**Files:**
- `backend/app/Service/Agent/Outcome/Payload/ResearchBundle.php` (修改,增加 `#[SA\Schema]` 和 `#[SA\Property]`)
- `backend/app/Service/Agent/Outcome/Payload/TitleCandidates.php` (修改)
- `backend/app/Service/Agent/Outcome/Payload/OutlineDraft.php` (修改)
- `backend/app/Service/Agent/Outcome/Payload/ContentDraft.php` (修改)
- `backend/app/Service/Agent/Outcome/Payload/ImageAnalyses.php` (修改)
- `backend/app/Service/Agent/Outcome/Payload/GeneratedImages.php` (修改)
- `backend/app/Service/Agent/Outcome/Payload/ReflectionPayload.php` (修改)
- `backend/config/autoload/swagger.php` (新增)

**Patterns:**
- 类级别: `#[SA\Schema(schema="TitleCandidates", description="...", type="object", required={"field1"})]`
- 属性级别: `#[SA\Property(property="title", type="string", description="...", example="...")]`
- 嵌套对象: `@SA\Items(ref="#/components/schemas/TitleCandidate")`

**Test Scenarios:**
1. **生成 OpenAPI spec**: 执行 `php bin/hyperf.php swagger:generate`,验证生成 `backend/storage/swagger.json`
2. **schema 完整性**: 验证 swagger.json 包含 7 个 components/schemas
3. **字段类型正确**: 验证 `TitleCandidate.score` 类型为 integer
4. **嵌套对象引用**: 验证 `TitleCandidates.titles` 正确引用 `TitleCandidate` schema
5. **必填字段标记**: 验证所有 DTO 的 required 字段列表与实际构造函数参数一致
6. **example 示例值**: 验证每个 string/number 字段包含合理的 example 值

**Verification:**
```bash
# 生成 OpenAPI spec
php bin/hyperf.php swagger:generate

# 验证生成文件
test -f backend/storage/swagger.json && echo "✅ swagger.json generated"

# 验证 schema 数量
jq '.components.schemas | keys | length' backend/storage/swagger.json
# 期望: 7

# 验证必填字段
jq '.components.schemas.TitleCandidates.required' backend/storage/swagger.json
# 期望: ["titles"]
```

---

### U12. 前端类型生成脚本与 CI 集成

**Goal:** 自动生成前端 TS 类型,集成到 CI 流程

**Files:**
- `frontend/scripts/generate-types.sh` (新增)
- `frontend/package.json` (修改,新增 `generate:types` script)
- `.github/workflows/ci.yml` (修改,新增类型生成步骤)

**Patterns:**
```bash
#!/bin/bash
cd ../backend && php bin/hyperf.php swagger:generate
cd ../frontend && npx openapi-typescript ../backend/storage/swagger.json -o src/types/api.ts
npx prettier --write src/types/api.ts
```

**Test Scenarios:**
1. **本地生成类型**: 执行 `npm run generate:types`,验证生成 `src/types/api.ts`
2. **类型导入**: 前端代码 `import type { TitleCandidates } from '@/types/api'`,验证 IDE 自动补全
3. **TypeScript 编译**: 执行 `npm run type-check`,验证 0 错误
4. **类型完整性**: 验证生成的 `api.ts` 包含所有 7 个 Payload 类型定义
5. **嵌套类型展开**: 验证 `TitleCandidates.titles` 类型为 `TitleCandidate[]`,非 `any`
6. **CI 自动提交**: 模拟 DTO 变更,验证 CI 自动生成并提交 `src/types/api.ts`

**Verification:**
```bash
# 本地测试
cd frontend
npm run generate:types
npm run type-check

# 验证类型文件包含 7 个 schema
grep -c "export interface" src/types/api.ts
# 期望: >= 7

# CI 测试
git commit -m "test: trigger CI type generation"
git push
# 验证 CI 通过,自动提交 src/types/api.ts
```

---

### U13. StreamBuffer 流式缓冲区实现

**Goal:** 实现协程安全的流式缓冲区,支持 ContentGenerator 流式输出 50% 时触发 ImageAnalyzer

**Files:**
- `backend/app/Service/Agent/StreamBuffer.php` (新增)
- `backend/test/Unit/Service/Agent/StreamBufferTest.php` (新增)

**Patterns:**
- 使用 `Swoole\Coroutine\Channel` 实现协程间通信
- 维护字节计数器,触发进度回调
- 支持多订阅者模式 (观察者模式)

**伪代码**:
```php
class StreamBuffer
{
    private Channel $channel;
    private int $bytesWritten = 0;
    private int $totalBytes = 0;
    private array $subscribers = []; // ['50%' => [callable, ...]]
    
    public function __construct(int $estimatedSize) {
        $this->totalBytes = $estimatedSize;
        $this->channel = new Channel(1024);
    }
    
    public function write(string $chunk): void {
        $this->bytesWritten += strlen($chunk);
        $this->channel->push($chunk);
        $this->checkProgressTriggers();
    }
    
    public function subscribe(float $threshold, callable $callback): void {
        $key = (string)($threshold * 100) . '%';
        $this->subscribers[$key][] = $callback;
    }
    
    public function getProgress(): float {
        return $this->totalBytes > 0 
            ? $this->bytesWritten / $this->totalBytes 
            : 0.0;
    }
    
    private function checkProgressTriggers(): void {
        $progress = $this->getProgress();
        foreach ($this->subscribers as $threshold => $callbacks) {
            if ($progress >= (float)$threshold / 100) {
                foreach ($callbacks as $callback) {
                    Coroutine::create($callback);
                }
                unset($this->subscribers[$threshold]); // 触发后移除
            }
        }
    }
}
```

**Test Scenarios:**
1. **基本写入读取**: 写入 3 个 chunk,验证 Channel 按序读取
2. **进度计算**: 预估 1000 字节,写入 500 字节,验证 getProgress() 返回 0.5
3. **50% 触发器**: 订阅 0.5 阈值,写入超过 50% 时验证回调被执行 1 次
4. **多订阅者**: 订阅 0.3/0.5/0.8 三个阈值,验证按序触发
5. **并发写入安全**: 3 个协程同时写入 100 次,验证 bytesWritten 计数正确 (无数据竞争)
6. **回调执行顺序**: 订阅回调在独立协程中执行,验证不阻塞 write() 方法
7. **重复触发防御**: 写入超过 50% 后再写入,验证 50% 回调仅触发 1 次

**Verification:**
```bash
# 单元测试
vendor/bin/pest test/Unit/Service/Agent/StreamBufferTest.php

# 并发压测
vendor/bin/pest test/Unit/Service/Agent/StreamBufferTest.php --filter=Concurrent
```

---

### U0. 依赖预验证 (前置任务)

**Goal:** 在实施 U1 前验证关键第三方依赖的兼容性和功能可用性

**Files:**
- `backend/test/Integration/DependencyVerificationTest.php` (新增)
- `backend/docs/dependency-verification.md` (新增,记录验证结果)

**验证任务**:

**Task 1: DashScope Function Calling 格式验证**
```php
// 验证 DashScope API 的 tools 参数格式与 OpenAI 兼容
$response = $dashScopeClient->chat([
    'model' => 'qwen-plus',
    'messages' => [...],
    'tools' => [
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_title_candidates',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'titles' => ['type' => 'array', 'items' => [...]]
                    ]
                ]
            ]
        ]
    ]
]);

// 验证返回格式
assert($response['choices'][0]['message']['tool_calls'][0]['function']['name'] === 'get_title_candidates');
assert(isset($response['choices'][0]['message']['tool_calls'][0]['function']['arguments']));
```

**Task 2: hyperf-plus/swagger 兼容性验证**
```bash
# 1. 安装 hyperf-plus/swagger
composer require hyperf-plus/swagger:^3.1 --dev

# 2. 配置扫描路径
cat > backend/config/autoload/swagger.php << 'EOF'
return [
    'enable' => true,
    'scan' => ['paths' => [BASE_PATH . '/app/Service/Agent/Outcome/Payload']],
];
EOF

# 3. 创建测试 DTO
cat > backend/app/Service/Agent/Outcome/Payload/TestDTO.php << 'EOF'
<?php
namespace App\Service\Agent\Outcome\Payload;
use Hyperf\Swagger\Annotation as SA;

#[SA\Schema(schema="TestDTO", type="object")]
class TestDTO {
    #[SA\Property(property="field", type="string")]
    public function __construct(public readonly string $field) {}
}
EOF

# 4. 生成 OpenAPI spec
php bin/hyperf.php swagger:generate

# 5. 验证生成结果
test -f backend/storage/swagger.json || (echo "❌ swagger.json 未生成" && exit 1)
jq '.components.schemas.TestDTO' backend/storage/swagger.json || (echo "❌ TestDTO schema 缺失" && exit 1)
echo "✅ hyperf-plus/swagger 兼容性验证通过"
```

**Test Scenarios:**
1. **DashScope API 可达性**: 发送简单 chat 请求,验证返回 200
2. **tools 参数支持**: 发送包含 tools 的请求,验证不返回 400/422
3. **tool_calls 返回格式**: 验证返回包含 `tool_calls[0].function.arguments` 字段
4. **arguments JSON 解析**: 验证 arguments 为合法 JSON 字符串
5. **hyperf-plus/swagger 安装**: 验证 `composer show hyperf-plus/swagger` 无错误
6. **OpenAPI 生成**: 验证 `swagger:generate` 命令存在且可执行
7. **DTO 扫描**: 验证测试 DTO 出现在生成的 swagger.json 中

**Verification:**
```bash
# 执行预验证
vendor/bin/pest test/Integration/DependencyVerificationTest.php

# 如果失败,记录到文档
# backend/docs/dependency-verification.md
```

**失败应急方案**:
- DashScope Function Calling 不可用 → 降级回正则解析 (保留 U2/U3 原逻辑)
- hyperf-plus/swagger 不兼容 → 改用 zircote/swagger-php + 自定义扫描脚本
- 两者都失败 → 暂停实施,上报技术选型问题

---

## Verification Contract

### Test Commands

**后端单元测试**:
```bash
# 全部单元测试
vendor/bin/pest

# 分模块测试
vendor/bin/pest test/Unit/Service/Provider/  # Provider 层
vendor/bin/pest test/Unit/Service/Agent/     # Agent 层
vendor/bin/pest test/Unit/Service/Agent/Outcome/Payload/  # Payload DTO
```

**后端集成测试**:
```bash
# Function Calling 集成测试
php bin/hyperf.php test:function-calling

# 缓存集成测试
php bin/hyperf.php test:cache-integration

# ReflectionAgent 集成测试
php bin/hyperf.php test:reflection --content="测试内容"

# DAG 性能测试
php bin/hyperf.php test:performance --mode=dag --iterations=100
```

**前端测试**:
```bash
cd frontend

# 类型生成
npm run generate:types

# TypeScript 编译检查
npm run type-check

# 单元测试 (Vitest)
npm run test:unit
```

**E2E 测试**:
```bash
# 完整创作流程 E2E
curl -X POST http://localhost:9501/api/workshop/start \
  -H 'Content-Type: application/json' \
  -d '{"topic":"AI技术","style":"专业","user_id":1}'
  
# 提取 article_id,继续后续步骤
# 1. 选择标题 2. 编辑大纲 3. 生成正文 (SSE)
# 验证全流程耗时 <= 27s
```

### Quality Gates

**代码覆盖率**:
- 新增代码覆盖率 >= 80%
- 关键路径 (AgentDAG, Function Calling, Caching) 覆盖率 >= 90%

**性能指标**:
- 完整创作流程 P50 <= 27s
- 完整创作流程 P95 <= 32s
- 缓存命中时响应 < 100ms
- TitleGenerator/OutlineGenerator 格式错误率 = 0% (1000 次调用)

**质量指标**:
- ReflectionAgent 首次评分通过率 >= 60% (100 篇内容)
- 重写后评分通过率 = 100%

**类型安全**:
- 前端 TypeScript 编译 0 错误
- 6 个 Payload 类型自动同步无遗漏

### Acceptance Criteria Per Unit

参考 Product Contract 中的 Acceptance Examples AE1-AE5,每个 Unit 的验收标准已在 Test Scenarios 中明确。

---

## Definition of Done

### Global Done Criteria

- [ ] **U0 依赖预验证完成** - DashScope Function Calling 和 hyperf-plus/swagger 兼容性验证通过
- [ ] 所有 13 个 Implementation Units (U0-U13, 不含 U0) 代码已实现并通过单元测试
- [ ] 后端单元测试覆盖率 >= 80%
- [ ] 前端 TypeScript 编译 0 错误
- [ ] E2E 测试验证完整创作流程总耗时 <= 27s
- [ ] 格式错误率压测 1000 次调用为 0%
- [ ] 缓存命中率压测 >= 80%
- [ ] ReflectionAgent 评分通过率达标
- [ ] 代码审查通过 (无 P0/P1 问题)
- [ ] 文档更新 (README, API 文档, Swagger 注解)

### Per-Unit Done

**U0 (依赖预验证):** DashScope Function Calling 格式验证通过,hyperf-plus/swagger 安装成功并生成测试 spec,验证结果记录到文档

**U1 (ModelProviderInterface 扩展):** `chatWithTools()` 方法实现,单元测试 4 个场景通过,集成测试验证 DashScope API 实际返回格式

**U2 (TitleGeneratorAgent):** Function Calling 正常返回结构化标题,降级逻辑保留可用,100 次调用格式错误率 0%

**U3 (OutlineGeneratorAgent):** Function Calling 正常返回结构化大纲,nodes 层级关系校验通过

**U4 (AbstractAgent 缓存):** 3 个方法实现,单元测试 5 个场景通过,TTL 过期测试通过

**U5 (Payload DTO 序列化):** 6 个 DTO 实现 `fromArray()`,往返序列化测试通过,缺失字段容错测试通过

**U6 (缓存集成):** 3 个 Agent 集成缓存逻辑,缓存命中/未命中场景测试通过,性能测试缓存命中 < 100ms

**U7 (ReflectionAgent):** 5 维度评分实现,评分通过/不通过场景测试通过,LLM 返回格式错误处理验证

**U8 (WorkshopOrchestrator 评审集成):** 评审逻辑集成,SSE 推送 reflection_* 事件验证,重写逻辑测试通过

**U9 (AgentDAG):** DFS 环检测 + Kahn 拓扑排序实现,7 个测试场景通过 (简单/链式/菱形/3 种环检测/失败停止)

**U10 (WorkshopOrchestrator DAG 改造):** startCreation 和 generateContentAndImages 改用 DAG,依赖 StreamBuffer (U13),性能测试总耗时降低 > 30%

**U11 (Swagger 注解):** 7 个 DTO 注解完整,生成 swagger.json 包含 7 个 schemas,字段类型正确,6 个测试场景通过

**U12 (前端类型生成):** 类型生成脚本实现,CI 集成自动提交,前端 TypeScript 编译 0 错误,6 个测试场景通过

**U13 (StreamBuffer 实现):** write/subscribe/getProgress 方法实现,7 个测试场景通过 (包括并发写入安全测试)
