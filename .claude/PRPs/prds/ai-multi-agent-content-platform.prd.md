# AI 多智能体企业级内容创作平台

## Problem Statement

自媒体内容创作者面临选题难、写作慢、配图烦三大瓶颈：从选题到发布一篇 2000 字配图文章平均耗时 3-6 小时，涉及脑暴、写作、找图、排版多个工具来回切换。现有 AI 写作工具多为"一问一答"的单模型调用模式，缺乏多 Agent 协作带来的质量把关，也不支持用户在创作中途介入编辑（human-in-the-loop），导致 AI 生成内容往往需要大量返工。

## Evidence

- 竞品分析显示：国内 AI 写作工具（讯飞绘文、百度作家平台等）均为单模型调用模式，尚未出现真正的多智能体协作产品
- 马良写作验证了多 Agent 协作在内容创作领域的质量提升（7 Agent 协作），但仅聚焦小说场景
- 01Agent 实现了多 Agent 协同但缺乏 human-in-the-loop 编辑能力
- 腾讯云 WriteOS 重构实践验证了「技能包注入 + 平台场景解耦」的架构可行性

## Proposed Solution

基于 PHP Hyperf 协程框架构建多智能体内容创作平台。用户输入选题后，5 个专业化 Agent（标题生成、大纲生成、正文生成、配图分析、并行配图）通过状态机编排协作完成从标题→大纲→正文→配图的完整创作流程，采用 SSE 流式推送让用户实时看到 AI 创作过程。在标题选择和大纲编辑两个阶段，用户可介入修改，形成三阶段人机协作模式。配图采用策略模式支持 Pexels、Mermaid、Iconify、表情包、SVG 和 nano Banana AI 生图 6 种方式。集成 z-pay 国内支付实现 VIP 会员体系，通过 AOP 注解实现智能体可观测性。

## Key Hypothesis

我们相信多智能体协作 + human-in-the-loop 的创作模式能够为自媒体内容创作者解决 AI 生成内容质量不可控的问题。当我们看到用户完成率（进入创作到导出文章）> 60% 且人均每月生成 > 10 篇文章时，就知道假设成立。

## What We're NOT Building

- 多平台一键分发（公众号/小红书/知乎等）— 聚焦创作环节，分发属于独立产品范畴
- 团队协作/多人编辑 — v1 聚焦单人创作体验
- 私有知识库/品牌声音克隆 — 后续版本功能
- SEO 优化/关键词分析 — 非核心创作流程

## Success Metrics

| Metric | Target | How Measured |
|--------|--------|--------------|
| 文章完成率 | > 60% | 进入创作流程到最终导出的比例 |
| 月均生成文章数 | > 10 篇/人 | 后台统计 |
| 付费转化率 | > 8% | VIP 订阅数 / 注册用户数 |
| Agent 平均响应时间 | < 30s/Agent | AOP 日志统计 |
| SSE 连接稳定性 | > 99% | 流式连接异常断开率 |

## Open Questions

- [ ] DashScope 通义千问在爆款标题生成场景的质量是否满足预期？需实测验证
- [ ] 是否需要支持多模型 Provider 切换（OpenAI 兼容接口），以应对单一模型质量不足
- [ ] z-pay 支付网关的稳定性和回调可靠性需在实际对接后验证
- [ ] 6 种配图方式的 API 可用性和响应速度差异需测试

---

## Users & Context

**Primary User**
- **Who**: 自媒体工作室老板 / 企业新媒体运营专员
- **Current behavior**: 从选题到发布一篇公众号文章需要在 ChatGPT、图库网站、编辑器之间来回切换，耗时 3-6 小时
- **Trigger**: 需要发一篇公众号文章时
- **Success state**: 输入选题 → 确认标题 → 微调大纲 → 获得带配图的完整 Markdown 文章，全程 15-30 分钟

**Job to Be Done**
当有选题需要快速创作一篇配图文章时，我想要通过 AI 多智能体协作快速搜集信息、生成内容并自动配图，以便我能在 30 分钟内完成一篇可发布的完整文章。

**Non-Users**
- 纯文学小说作家 — 需要长篇小说级别的一致性管理，不在本产品范围内
- 学术论文研究者 — 需要严格的引用管理和学术规范，场景差异大
- 完全不懂 AI 的用户 — 产品需要用户理解 AI 协作的基本逻辑

---

## Solution Detail

### Core Capabilities (MoSCoW)

| Priority | Capability | Rationale |
|----------|------------|-----------|
| Must | 三阶段创作流程（标题→大纲→正文+配图） | 核心价值交付链路 |
| Must | 5 Agent 多智能体协作编排 | 产品差异化核心 |
| Must | SSE 流式推送 | 实时感知 AI 创作过程，提升体验 |
| Must | Human-in-the-loop 标题选择 + 大纲编辑 | 区别于竞品的关键交互模式 |
| Must | 6 种配图方式策略模式 | 全覆盖创作场景的配图需求 |
| Must | VIP 会员体系 + z-pay 支付 | 商业闭环 |
| Must | AOP 智能体可观测性 | 运维和产品迭代的数据基础 |
| Must | 多模型 Provider 支持 | 避免单一模型锁定，支持扩展 |
| Must | Docker Compose 一键部署 | 交付和运维标准 |

### MVP Scope

本项目不设 MVP，直接全量实现上述所有 Must Have 能力。

### User Flow

```
用户输入选题 + 风格
       ↓
[阶段1] TitleGeneratorAgent → 生成 3~5 个标题 → 用户选择 + 补充描述
       ↓
[阶段2] OutlineGeneratorAgent → 生成大纲 → 用户编辑/优化 → 确认
       ↓
[阶段3] ContentGeneratorAgent → 生成正文（Markdown + 配图占位符）
       ↓
       ImageAnalyzerAgent → 分析正文 → 确定配图位置和关键词
       ↓
       ParallelImageGenerator → 策略模式调度多种配图源 → 上传 OSS/COS
       ↓
完整 Markdown 文章（正文 + 图片 URL）→ 用户预览 → 导出
```

---

## Technical Approach

**Feasibility**: HIGH — 核心技术栈均有成熟方案

### Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                      Frontend (Vite + TS)               │
│  Pinia Store → Ant Design Vue → EventSource (SSE)       │
│  markdown-it + highlight.js                             │
└──────────────────────────┬──────────────────────────────┘
                           │ SSE / REST API
┌──────────────────────────┴──────────────────────────────┐
│                   Hyperf API Gateway                     │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────────┐  │
│  │ Auth MW  │  │ Rate MW  │  │ AOP Logging Aspect   │  │
│  └──────────┘  └──────────┘  └──────────────────────┘  │
│                                                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │              Agent Orchestrator                    │  │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────────────┐  │  │
│  │  │ Title    │ │ Outline  │ │ Content          │  │  │
│  │  │ Generator│ │ Generator│ │ Generator        │  │  │
│  │  └──────────┘ └──────────┘ └──────────────────┘  │  │
│  │  ┌──────────┐ ┌──────────────────────────────────┐ │  │
│  │  │ Image    │ │ ParallelImageGenerator           │ │  │
│  │  │ Analyzer │ │ (Strategy Pattern Dispatcher)     │ │  │
│  │  └──────────┘ └──────────────────────────────────┘ │  │
│  └──────────────────────────────────────────────────┘  │
│                                                         │
│  ┌──────────┐ ┌──────────┐ ┌──────────────────────────┐│
│  │ VIP Srv  │ │ Pay Srv  │ │ Model Provider (Strategy) ││
│  │          │ │ (z-pay)  │ │ DashScope / OpenAI / ...  ││
│  └──────────┘ └──────────┘ └──────────────────────────┘│
└──────────────────────────┬──────────────────────────────┘
                           │
┌──────────────────────────┴──────────────────────────────┐
│                   Data & Storage                         │
│  MySQL 8.0 │ Redis 7.x │ OSS/COS │ Docker Compose       │
└─────────────────────────────────────────────────────────┘
```

### Tech Stack

| 层 | 技术 | 说明 |
|----|------|------|
| 后端框架 | PHP 8.x + Hyperf 3.x | 协程、AOP、依赖注入、注解 |
| AI 模型 | DashScope 通义千问 + OpenAI 兼容扩展 | 策略模式切换 Provider |
| 数据库 | MySQL 8.0 | 用户、文章、订单、Agent 日志 |
| 缓存/队列 | Redis 7.x | Session、配额计数、异步任务队列 |
| 存储 | 阿里云 OSS / 腾讯云 COS | 配图上传、CDN 加速、防盗链 |
| 支付 | z-pay (7-pay.cn) | 国内支付聚合网关 |
| 前端 | Vite + TypeScript + Pinia + Ant Design Vue | SPA 管理后台 + 创作工坊 |
| SSE 客户端 | EventSource API | 接收流式推送 |
| 部署 | Docker Compose | 4 容器编排（MySQL/Redis/Backend/Frontend） |

### Key Design Patterns

| Pattern | Application |
|---------|-------------|
| **Strategy** | 配图方式扩展（6 种）、模型 Provider 切换 |
| **State Machine** | Agent 编排流程状态管理 |
| **Chain of Responsibility** | Agent 间数据传递流水线 |
| **Observer** | SSE 事件广播 |
| **Repository** | 数据访问层抽象 |
| **AOP** | Agent 执行日志自动记录 |

### Technical Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| DashScope 生成质量不满足爆款文章要求 | M | 预留 OpenAI 兼容接口，支持切换模型 |
| SSE 长连接在 Swoole 下的稳定性 | M | 心跳检测 + 断线重连 + 连接健康检查 |
| 多 Agent 编排在协程下的错误传播 | M | 状态机 + 超时熔断 + 每步独立错误处理 |
| z-pay 回调可靠性 | M | 主动查询订单状态 + 幂等处理 |
| 6 种配图 API 的并发调用性能 | L | 协程并行 + 超时降级 + 失败静默 |

---

## Implementation Phases

| # | Phase | Description | Status | Parallel | Depends | PRP Plan |
|---|-------|-------------|--------|----------|---------|----------|
| 1 | 基础设施搭建 | Docker 环境、Hyperf 项目骨架、数据库 Schema、前端项目初始化 | complete | - | - | [plan](../plans/completed/phase-1-infrastructure.plan.md) · [report](../reports/phase-1-infrastructure-report.md) |
| 2 | 用户认证 + 模型 Provider | 注册登录 JWT、DashScope 对接、策略模式多 Provider、SSE 基础通道 | complete | - | 1 | [plan](../plans/completed/phase-2-auth-model-provider.plan.md) |
| 3 | 三阶段创作流程 | 5 Agent 开发 + 状态机编排 + SSE 流式推送 + Human-in-the-loop 交互 | complete | - | 2 | [plan](../plans/completed/phase-3-creative-workflow.plan.md) |
| 4 | 配图系统 | 6 种配图策略 + OSS/COS 上传 + ParallelImageGenerator | pending | with 5 | 2 | [plan](../plans/phase-4-image-system.plan.md) |
| 5 | VIP 会员 + 支付 | z-pay 对接、会员等级、配额管理、配图权限控制 | pending | with 4 | 2 | [plan](../plans/phase-5-vip-payment.plan.md) |
| 6 | 可观测性系统 | AOP 注解日志、执行耗时、成功率、活跃度 Dashboard | pending | - | 3 | [plan](../plans/phase-6-observability.plan.md) |
| 7 | 前端全流程联调 | 创作工坊 UI、SSE 事件消费、Markdown 渲染、Dashboard | pending | - | 4, 5, 6 | [plan](../plans/phase-7-frontend-integration.plan.md) |
| 8 | 部署与文档 | Docker Compose 编排、环境变量、部署文档 | pending | - | 7 | [plan](../plans/phase-8-deployment.plan.md) |

### Phase Details

**Phase 1: 基础设施搭建**
- **Goal**: 可运行的开发环境
- **Scope**: Docker Compose 编排（MySQL 8.0 非 3306、Redis 7.x 非 6379、Backend、Frontend）、Hyperf 项目骨架 + 数据库连接、前端 Vite + TS + Ant Design Vue 骨架、数据库迁移（users/articles/agents 核心表）
- **Success signal**: `docker compose up` 后前后端均可访问

**Phase 2: 用户认证 + 模型 Provider**
- **Goal**: 用户可注册登录、系统可调用大模型并流式返回
- **Scope**: JWT 认证、注册/登录 API、DashScope Provider（SSE 流式）、OpenAI 兼容 Provider 接口预留、EventStream 基础通道
- **Success signal**: 前端通过 EventSource 接收到流式 AI 输出

**Phase 3: 三阶段创作流程**
- **Goal**: 核心创作链路的完整实现
- **Scope**: 5 个 Agent 类开发、Agent 状态机编排器、SSE 事件广播机制、标题选择/大纲编辑交互 API、Markdown 正文生成、配图占位符插入
- **Success signal**: 输入选题后完整走完标题→大纲→正文+占位符流程

**Phase 4: 配图系统**
- **Goal**: 正文自动配图
- **Scope**: 策略模式接口定义、Pexels/Mermaid/Iconify/表情包/SVG/nano Banana 6 种实现、ParallelImageGenerator 协程并行调度、OSS/COS 上传 + CDN URL 生成
- **Success signal**: 正文中的占位符被替换为实际图片 URL

**Phase 5: VIP 会员 + 支付**
- **Goal**: 商业闭环
- **Scope**: z-pay 支付对接（PC 扫码 + H5）、VIP 等级定义（免费/月费/年费）、配额管理（每月生成篇数）、配图方式权限控制（高级配图仅 VIP）、订单管理
- **Success signal**: 用户可完成支付并升级会员等级

**Phase 6: 可观测性系统**
- **Goal**: Agent 执行数据的全量记录与分析
- **Scope**: AOP 注解 `@AgentLog`、执行耗时/成功率统计、用户活跃度 Dashboard API、Agent 调用链路追踪
- **Success signal**: Dashboard 展示 Agent 执行统计图表

**Phase 7: 前端全流程联调**
- **Goal**: 完整可用的用户产品
- **Scope**: 创作工坊（三阶段 UI + SSE 实时展示）、Markdown 预览渲染、个人中心（配额/会员/订单/文章管理）、管理后台（可观测性 Dashboard）
- **Success signal**: 端到端完成一次完整的创作流程

**Phase 8: 部署与文档**
- **Goal**: 生产可用的交付物
- **Scope**: Docker Compose 编排文件、环境变量模板（.env.example）、Nginx 反向代理配置、API 文档、部署 README
- **Success signal**: 一键 `docker compose up -d` 启动完整服务

### Parallelism Notes

- Phase 4（配图）和 Phase 5（支付）可并行开发，两者均依赖 Phase 2 但不依赖 Phase 3
- Phase 3 是核心链路，建议单人专注开发，避免并行带来的沟通成本
- Phase 7（前端联调）必须等待所有后端 Phase 完成后进行

---

## Decisions Log

| Decision | Choice | Alternatives | Rationale |
|----------|--------|--------------|-----------|
| 后端框架 | Hyperf 3.x | Laravel Octane, Webman, FastAPI | PHP 协程原生支持 SSE + AOP 注解体系成熟 |
| AI 模型 | DashScope 通义千问 + 可扩展 Provider | OpenAI, Claude, DeepSeek | 国内访问稳定 + 策略模式预留扩展 |
| 支付 | z-pay 聚合支付 | 直接对接支付宝/微信 | 一次集成覆盖多渠道，降低对接成本 |
| 存储 | 阿里云 OSS / 腾讯云 COS | 本地存储, MinIO | CDN 加速 + 防盗链 + 生产级可靠性 |
| 部署 | Docker Compose | K8s, 裸机部署 | 简单可控 + 满足中小规模部署需求 |
| 配图架构 | 策略模式 | 工厂模式, 适配器模式 | 每种配图方式独立策略类，新增零侵入 |
| Agent 编排 | 状态机 + 协程并行 | 消息队列, 事件驱动 | 流程可控 + 充分利用 Swoole 协程能力 |

---

## Research Summary

**Market Context**
- 国内 AI 写作工具（讯飞绘文、百度作家平台、01Agent、AIWriteX）均为单模型调用或简单 Agent，尚无真正多 Agent + human-in-the-loop 产品
- 国际产品（Jasper.ai、Aethera、Werd.ai）功能强大但中文支持弱、价格高、无国内支付
- 马良写作验证了多 Agent 在中文内容创作的质量提升，但聚焦小说细分场景
- 市场存在差异化窗口：多 Agent 协作 + 人机协作 + 多配图方式的整合产品

**Technical Context**
- Hyperf 3.0+ 内置 `Hyperf\Engine\Http\EventStream` 支持 SSE 流式输出
- Hyperf AOP 体系成熟，支持基于注解的方法拦截
- DashScope API 支持 SSE 流式 + `incremental_output` 增量模式
- yansongda/hyperf-pay 提供支付宝/微信/银联的 Hyperf 适配
- Swoole 协程 hook 可自动处理 cURL 请求的非阻塞化

---

*Generated: 2026-04-30*
*Status: DRAFT - needs validation*
