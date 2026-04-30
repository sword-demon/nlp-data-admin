# Plan: Phase 7 — 前端全流程联调

## Summary

完成前端所有页面的完整实现：创作工坊（三阶段 UI + SSE 实时展示）、Markdown 预览渲染（markdown-it + highlight.js）、VIP 会员中心、个人中心（配额/文章管理）、管理后台可观测性 Dashboard。将 Phase 2-6 所有后端能力通过前端完整串联，实现端到端的用户体验闭环。

## User Story

As a 内容创作者, I want 在浏览器中完成从选题到导出的完整创作流程，实时看到 AI 创作过程，并在完成后获得图文并茂的 Markdown 文章, So that 我可以在 15-30 分钟内完成一篇可发布的完整文章。

## Problem → Solution

Phase 1-6 的前端仅有骨架页面（Home/Workshop/Dashboard 占位符）→ 完整实现所有前端页面，SSE 事件消费驱动实时 UI 更新，Markdown 渲染展示最终文章，Dashboard 可视化可观测性数据。

## Metadata

- **Complexity**: XL
- **Source PRD**: `.claude/PRPs/prds/ai-multi-agent-content-platform.prd.md`
- **PRD Phase**: Phase 7 — 前端全流程联调
- **Estimated Files**: 25+

---

## UX Design

### Before
```
┌──────────────────────────────────┐
│  Home.vue: 占位文字              │
│  Workshop.vue: 占位文字          │
│  Dashboard.vue: 占位文字         │
│  无 SSE 事件消费                 │
│  无 Markdown 渲染               │
│  无 VIP/订单管理                 │
└──────────────────────────────────┘
```

### After
```
┌──────────────────────────────────┐
│  首页: 产品介绍 + 快速入口       │
│                                  │
│  创作工坊:                       │
│  ┌────────────────────────────┐  │
│  │ [阶段指示器: ● ● ○]        │  │
│  │ 选题输入 → 标题选择 →      │  │
│  │ 大纲编辑 → 实时正文生成 →   │  │
│  │ 配图进度 → 最终预览/导出    │  │
│  │                            │  │
│  │ 左侧: 创作交互面板         │  │
│  │ 右侧: Markdown 实时预览    │  │
│  └────────────────────────────┘  │
│                                  │
│  Dashboard: 可观测性图表         │
│  个人中心: 配额/VIP/文章/订单    │
│  VIP 中心: 套餐对比 + 支付      │
└──────────────────────────────────┘
```

### Interaction Changes

| Touchpoint | Before | After | Notes |
|---|---|---|---|
| 首页 | 空白 | 产品介绍 + "开始创作" CTA | 引导注册/创作 |
| 创作工坊 | 空白 | 三阶段完整 UI | SSE 实时更新 |
| AI 输出 | 无 | 流式 Markdown 渲染 | 逐字/逐段显示 |
| 标题选择 | 无 | 3-5 个标题卡片选择 | 单选 + 补充描述 |
| 大纲编辑 | 无 | 可编辑树形大纲 | 拖拽排序/编辑/删除 |
| 配图进度 | 无 | 6 张图逐步完成 | 进度条 + 缩略图 |
| 文章预览 | 无 | Markdown + 图片渲染 | highlight.js 代码高亮 |
| 导出 | 无 | 复制 Markdown / 下载 .md | 一键操作 |
| Dashboard | 空白 | Agent 统计图表 | ECharts 可视化 |
| 个人中心 | 不存在 | 配额/VIP/文章列表/订单 | 综合管理页 |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `frontend/src/api/client.ts` | all | Axios 配置 + EventSource 工厂 |
| P0 | `frontend/src/stores/auth.ts` | all | 认证状态管理 |
| P0 | `frontend/src/router/index.ts` | all | 路由结构 |
| P0 | `frontend/src/layouts/DefaultLayout.vue` | all | 布局组件 |
| P1 | `frontend/src/pages/Workshop.vue` | all | 待替换的占位页面 |
| P1 | `frontend/src/pages/Dashboard.vue` | all | 待替换的占位页面 |
| P1 | `frontend/vite.config.ts` | all | 代理配置 |
| P2 | `frontend/src/main.ts` | all | 入口文件（已注册组件库） |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| EventSource API | MDN | `new EventSource(url)` + `onmessage`/`onerror` |
| markdown-it | npm | `MarkdownIt({html: false, highlight})` 渲染 |
| highlight.js | npm | `hljs.highlightAuto(code)` 代码块高亮 |
| Ant Design Vue | ant-desgin-vue 官网 | Form/Table/Card/Modal/Progress/Tag 组件 |
| ECharts | echarts.apache.org | 折线图/饼图/柱状图渲染 |

---

## Patterns to Mirror

### API_CLIENT_PATTERN
```typescript
// SOURCE: frontend/src/api/client.ts
import axios from 'axios';
const api = axios.create({ baseURL: '/api' });
// JWT interceptor auto-attaches Bearer token
// createEventSource() factory for SSE connections
```

### PINIA_STORE_PATTERN
```typescript
// SOURCE: frontend/src/stores/auth.ts
import { defineStore } from 'pinia';
export const useAuthStore = defineStore('auth', {
  state: () => ({...}),
  actions: {...},
  getters: {...},
});
```

### VUE_COMPONENT_PATTERN
```vue
<!-- SOURCE: frontend/src/layouts/DefaultLayout.vue -->
<template>
  <a-layout>...</a-layout>
</template>
<script setup lang="ts">
import { ref } from 'vue';
</script>
```

### SSE_EVENT_PATTERN (new)
```typescript
// Factory for SSE with auto-reconnect
function createEventSource(url: string, token: string): EventSource {
  const es = new EventSource(url);
  es.onmessage = (event) => { /* parse JSON data */ };
  es.onerror = () => { es.close(); /* reconnect logic */ };
  return es;
}
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `frontend/src/pages/Home.vue` | UPDATE | 产品介绍首页 |
| `frontend/src/pages/Workshop.vue` | UPDATE | 完整创作工坊（三阶段） |
| `frontend/src/pages/Dashboard.vue` | UPDATE | 可观测性 Dashboard |
| `frontend/src/pages/VipCenter.vue` | UPDATE | VIP 中心（Phase 5 基础上完善） |
| `frontend/src/pages/OrderList.vue` | UPDATE | 订单列表（Phase 5 基础上完善） |
| `frontend/src/pages/Profile.vue` | CREATE | 个人中心 |
| `frontend/src/pages/ArticleList.vue` | CREATE | 我的文章列表 |
| `frontend/src/pages/ArticleDetail.vue` | CREATE | 文章详情 + Markdown 预览 |
| `frontend/src/components/workshop/TopicInput.vue` | CREATE | 选题输入组件 |
| `frontend/src/components/workshop/TitleSelector.vue` | CREATE | 标题选择组件 |
| `frontend/src/components/workshop/OutlineEditor.vue` | CREATE | 大纲编辑组件 |
| `frontend/src/components/workshop/ContentPreview.vue` | CREATE | 正文实时预览 |
| `frontend/src/components/workshop/ImageProgress.vue` | UPDATE | 配图进度（Phase 4 基础完善） |
| `frontend/src/components/workshop/StageIndicator.vue` | CREATE | 阶段指示器 |
| `frontend/src/components/workshop/ExportPanel.vue` | CREATE | 导出面板 |
| `frontend/src/components/common/MarkdownRenderer.vue` | CREATE | Markdown 渲染组件 |
| `frontend/src/components/common/SseConnector.vue` | CREATE | SSE 连接管理组件 |
| `frontend/src/components/dashboard/OverviewCards.vue` | CREATE | 概览统计卡片 |
| `frontend/src/components/dashboard/AgentChart.vue` | CREATE | Agent 统计图表 |
| `frontend/src/components/dashboard/TrendChart.vue` | CREATE | 趋势图 |
| `frontend/src/components/dashboard/LogTable.vue` | CREATE | 日志列表 |
| `frontend/src/stores/workshop.ts` | CREATE | 创作工坊状态管理 |
| `frontend/src/stores/vip.ts` | UPDATE | 完善 VIP store |
| `frontend/src/stores/article.ts` | CREATE | 文章列表状态管理 |
| `frontend/src/api/workshop.ts` | CREATE | 创作工坊 API |
| `frontend/src/api/observability.ts` | CREATE | 可观测性 API |
| `frontend/src/api/article.ts` | CREATE | 文章 API |
| `frontend/src/router/index.ts` | UPDATE | 完善所有路由 |
| `frontend/src/layouts/DefaultLayout.vue` | UPDATE | 完善 VIP 状态展示 |

## NOT Building

- 大纲拖拽排序 — v1 仅支持文本编辑
- 实时多人协作 — 后续版本
- 文章版本历史 — 后续版本
- 文章直接发布到平台 — 本项目聚焦创作
- 离线编辑/PWA — 后续版本
- 自定义主题/样式 — 后续版本

---

## Step-by-Step Tasks

### Task 1: 创建 SSE 连接管理组件和工具
- **ACTION**: 封装 SSE 连接的创建、事件监听、断线重连、状态管理
- **IMPLEMENT**:
  - `SseConnector.vue`: 可复用的 SSE 连接管理组件
    - Props: url, token
    - Events: @message, @error, @open, @close
    - 自动重连逻辑（指数退避: 1s → 2s → 4s → 8s → 最大 30s）
    - 连接状态: connecting/connected/disconnected
    - 显示连接状态指示器（小圆点）
  - 更新 `api/client.ts` 中的 `createEventSource()` 添加 token 传递
- **MIRROR**: Vue 3 Composition API + EventSource API
- **IMPORTS**: `vue`
- **GOTCHA**:
  - EventSource 不支持自定义 header，token 通过 URL query 或 cookie 传递
  - SSE 连接在页面隐藏时可能被浏览器断开，需 visibilitychange 处理
  - 后端需支持 token query 参数认证（除 header 外）
- **VALIDATE**: SSE 连接建立后能接收消息

### Task 2: 创建 Markdown 渲染组件
- **ACTION**: 封装 markdown-it + highlight.js 的 Markdown 渲染
- **IMPLEMENT**:
  - `MarkdownRenderer.vue`:
    - Props: `content: string` (Markdown 原文)
    - 使用 `markdown-it` 渲染 HTML
    - `highlight.js` 自动检测代码语言并高亮
    - 图片懒加载（`loading="lazy"`）
    - XSS 防护: `markdown-it({ html: false })`
    - 响应式样式（移动端适配）
  - 创建 `frontend/src/utils/markdown.ts`: markdown-it 实例配置
- **MIRROR**: Vue 3 `computed` 响应式 + `v-html` 渲染
- **IMPORTS**: `markdown-it`, `highlight.js`
- **GOTCHA**:
  - `v-html` 直接输出 HTML，需确保 markdown-it 关闭 html 模式防 XSS
  - highlight.js 需导入 CSS 主题: `import 'highlight.js/styles/github.css'`
  - 代码块使用 `fence` 规则，配置 `highlight` 回调
- **VALIDATE**: 输入 Markdown 文本，渲染为带高亮的 HTML

### Task 3: 创建创作工坊 Store
- **ACTION**: Pinia store 管理创作流程的完整状态
- **IMPLEMENT**:
  - `stores/workshop.ts`:
    - State: `articleId`, `topic`, `style`, `stage`(topic/titles/outline/content/images/complete), `titles[]`, `selectedTitle`, `outline`, `content`, `images[]`, `sseStatus`, `agentMessages[]`
    - Actions: `startCreation()`, `selectTitle()`, `updateOutline()`, `confirmOutline()`, `proceedStage()`, `handleSseEvent()`, `resetWorkshop()`
    - SSE 事件类型映射:
      - `agent_start` → 更新阶段状态，显示 "正在生成..."
      - `agent_chunk` → 实时追加文本（标题/大纲/正文）
      - `agent_complete` → 更新阶段完成状态
      - `image_ready` → 更新配图进度
      - `workshop_complete` → 显示完成/导出
      - `workshop_error` → 显示错误信息
- **MIRROR**: 参考 `stores/auth.ts` 的 defineStore 模式
- **IMPORTS**: `pinia`, `axios`
- **GOTCHA**: `agent_chunk` 事件高频触发（每秒多次），需避免不必要的重渲染（使用 debounce 或 requestAnimationFrame）
- **VALIDATE**: Store 状态在不同阶段正确流转

### Task 4: 创建创作工坊 API 层
- **ACTION**: 创作工坊的 REST API 调用封装
- **IMPLEMENT**:
  - `api/workshop.ts`:
    - `createWorkshop(topic, style)` → POST /api/workshop/create
    - `getWorkshop(id)` → GET /api/workshop/{id}
    - `selectTitle(id, titleIndex, description)` → POST /api/workshop/{id}/select-title
    - `updateOutline(id, outline)` → POST /api/workshop/{id}/update-outline
    - `confirmOutline(id)` → POST /api/workshop/{id}/confirm-outline
    - `proceedStage(id)` → POST /api/workshop/{id}/proceed
    - `getSseUrl(id)` → 返回 SSE 连接 URL
    - `exportArticle(id)` → GET /api/workshop/{id}/export
- **MIRROR**: 使用 `api/client.ts` 的 axios 实例
- **IMPORTS**: `axios`
- **GOTCHA**: SSE URL 需要拼接到完整的后端地址（非代理路径）
- **VALIDATE**: API 调用返回正确数据

### Task 5: 创建阶段指示器组件
- **ACTION**: 显示创作流程的当前阶段
- **IMPLEMENT**:
  - `StageIndicator.vue`:
    - 3 个大阶段: 选题 → 大纲 → 正文+配图
    - 每个大阶段包含子步骤（AI 生成中...）
    - 当前阶段高亮，已完成打勾，未完成灰色
    - 使用 Ant Design Steps 组件
- **MIRROR**: Ant Design Steps 组件
- **IMPORTS**: `ant-design-vue`
- **GOTCHA**: 阶段定义应与后端状态机一致
- **VALIDATE**: 阶段随创作流程正确推进

### Task 6: 创建选题输入组件
- **ACTION**: 阶段 1 输入选题和文章风格
- **IMPLEMENT**:
  - `TopicInput.vue`:
    - 文本输入框: 文章选题/主题
    - 下拉选择: 文章风格（专业/轻松/干货/情感/故事）
    - "开始创作" 按钮
    - 提交后触发 `createWorkshop` API
    - 自动进入标题生成等待状态
- **MIRROR**: Ant Design Input/Select/Button 组件
- **IMPORTS**: `ant-design-vue`
- **GOTCHA**: 风格选项与后端 prompt 模板对应
- **VALIDATE**: 提交选题后进入标题等待状态

### Task 7: 创建标题选择组件
- **ACTION**: 阶段 1 — 展示 AI 生成的标题并选择
- **IMPLEMENT**:
  - `TitleSelector.vue`:
    - 展示 3-5 个标题卡片（SSE 实时填充）
    - 每个标题可点击选择（单选）
    - 选中后展开补充描述输入框
    - "确认选择" 按钮
    - SSE 加载中显示骨架屏/动画
- **MIRROR**: Ant Design Card/Radio/Input 组件
- **IMPORTS**: `ant-design-vue`
- **GOTCHA**: 标题通过 SSE `agent_chunk` 事件逐字显示，需平滑动画
- **VALIDATE**: 选择标题后可提交并进入下一阶段

### Task 8: 创建大纲编辑组件
- **ACTION**: 阶段 2 — 展示和编辑 AI 生成的大纲
- **IMPLEMENT**:
  - `OutlineEditor.vue`:
    - 展示结构化大纲（一级/二级标题 + 摘要）
    - 每个节点可编辑文本
    - 可添加/删除节点
    - "确认大纲" 按钮
    - SSE 逐段填充大纲
- **MIRROR**: Ant Design Tree/Input 组件
- **IMPORTS**: `ant-design-vue`
- **GOTCHA**: 大纲结构需与后端 JSON 格式对齐（Phase 3 outline 字段）
- **VALIDATE**: 编辑大纲后提交进入正文生成

### Task 9: 创建正文实时预览组件
- **ACTION**: 阶段 3 — 实时展示 AI 生成的正文（Markdown）
- **IMPLEMENT**:
  - `ContentPreview.vue`:
    - 左右分栏: 左侧原始文本流，右侧 Markdown 渲染预览
    - SSE `agent_chunk` 事件实时追加正文内容
    - 使用 `MarkdownRenderer` 组件渲染
    - 显示字数统计和生成进度
    - 配图进度条（与 ImageProgress 组件联动）
    - 生成完成后显示 "导出文章" 按钮
- **MIRROR**: CSS Grid/Flex 分栏布局 + MarkdownRenderer 组件
- **IMPORTS**: `markdown-it`, `highlight.js`, `./MarkdownRenderer.vue`, `./ImageProgress.vue`
- **GOTCHA**: 高频 SSE 更新需使用 debounce 或 requestAnimationFrame 优化渲染性能
- **VALIDATE**: 正文逐字显示并实时渲染为 Markdown

### Task 10: 创建导出面板组件
- **ACTION**: 文章完成后提供导出操作
- **IMPLEMENT**:
  - `ExportPanel.vue`:
    - "复制 Markdown" 按钮（使用 `navigator.clipboard.writeText()`）
    - "下载 .md 文件" 按钮（使用 Blob + URL.createObjectURL）
    - 文章统计信息: 字数/配图数/Agent 执行时间
    - "开始新创作" 按钮
- **MIRROR**: Ant Design Button/Statistic 组件
- **IMPORTS**: `ant-design-vue`
- **GOTCHA**: 下载文件名建议格式: `{title}_{date}.md`
- **VALIDATE**: 复制和下载功能正常

### Task 11: 组装完整创作工坊页面
- **ACTION**: 将所有创作组件组装到 Workshop.vue
- **IMPLEMENT**:
  - `Workshop.vue`:
    - 顶部: StageIndicator 阶段指示器
    - 主体区域根据当前 stage 切换显示:
      - `topic`: TopicInput
      - `titles`: TitleSelector
      - `outline`: OutlineEditor
      - `content`: ContentPreview
      - `complete`: ExportPanel
    - 右侧常驻: MarkdownRenderer 预览（content 和 complete 阶段）
    - SSE 连接管理: 使用 SseConnector 组件
    - 底部状态栏: SSE 连接状态 + 当前 Agent 状态
  - 使用 `v-if` / `v-show` 切换阶段组件
- **MIRROR**: Ant Design Layout + 动态组件切换
- **IMPORTS**: 所有 workshop 子组件
- **GOTCHA**: 阶段切换时有短暂的 loading 状态，需平滑过渡动画
- **VALIDATE**: 完整创作流程从选题到导出

### Task 12: 创建个人中心和文章管理
- **ACTION**: 个人中心页面 + 文章列表 + 文章详情
- **IMPLEMENT**:
  - `Profile.vue`: 个人信息卡片 + 配额使用 + VIP 状态
  - `ArticleList.vue`: 文章表格（标题/状态/字数/创建时间），支持筛选和分页
  - `ArticleDetail.vue`: 文章详情页（Markdown 渲染 + 元信息 + 导出）
  - `api/article.ts`:
    - `getArticles(page, status)` → GET /api/articles
    - `getArticle(id)` → GET /api/articles/{id}
    - `deleteArticle(id)` → DELETE /api/articles/{id}
- **MIRROR**: Ant Design Table/Card/Descriptions 组件
- **IMPORTS**: `ant-design-vue`
- **GOTCHA**: 文章列表需要后端 API 支持（Phase 3 需添加文章 CRUD 端点）
- **VALIDATE**: 文章列表正确展示，详情页可查看完整文章

### Task 13: 创建可观测性 Dashboard
- **ACTION**: 管理员可观测性面板
- **IMPLEMENT**:
  - `OverviewCards.vue`: 4 个统计卡片（总调用/成功率/平均耗时/最慢 Agent）
  - `AgentChart.vue`: 各 Agent 调用次数柱状图 + 成功率饼图
  - `TrendChart.vue`: 按天趋势折线图（调用次数 + 成功率 + 平均耗时）
  - `LogTable.vue`: Agent 日志列表表格（支持筛选和分页）
  - `api/observability.ts`: 所有 Dashboard API 调用
  - 使用 ECharts 渲染图表
  - 组装到 `Dashboard.vue` 页面
- **MIRROR**: Ant Design Card/Row/Col + ECharts
- **IMPORTS**: `echarts`, `ant-design-vue`
- **GOTCHA**:
  - ECharts 需要安装: `npm install echarts`
  - 图表组件需在 `onMounted` 中初始化
  - 仅 admin 角色可见 Dashboard 菜单项
- **VALIDATE**: Dashboard 显示统计数据和图表

### Task 14: 更新首页
- **ACTION**: 产品介绍首页
- **IMPLEMENT**:
  - `Home.vue`:
    - Hero 区域: 产品名称 + slogan + "开始创作" CTA
    - 特性展示: 5 个核心能力（多 Agent/配图/human-in-the-loop/SSE/会员）
    - 创作流程演示: 3 步骤（选题→大纲→文章）
    - 定价卡片: 免费版/月费版/年费版
    - 已登录用户直接跳转到创作工坊
- **MIRROR**: Ant Design Typography/Button/Card/Row/Col
- **IMPORTS**: `ant-design-vue`
- **GOTCHA**: 首页需要响应式设计（移动端适配）
- **VALIDATE**: 首页展示正确，CTA 按钮跳转正确

### Task 15: 完善路由和布局
- **ACTION**: 添加所有新页面的路由，完善布局
- **IMPLEMENT**:
  - 路由更新:
    - `/` → Home
    - `/workshop` → Workshop（JWT 保护）
    - `/workshop/:id` → Workshop（恢复创作会话）
    - `/dashboard` → Dashboard（admin 保护）
    - `/profile` → Profile（JWT 保护）
    - `/articles` → ArticleList（JWT 保护）
    - `/articles/:id` → ArticleDetail（JWT 保护）
    - `/vip` → VipCenter（JWT 保护）
    - `/orders` → OrderList（JWT 保护）
  - DefaultLayout 更新:
    - 侧边栏菜单: 首页/创作工坊/我的文章/VIP 中心/个人中心
    - admin 额外可见: Dashboard
    - Header: VIP 标签 + 配额信息 + 用户头像 + 登出
- **MIRROR**: Vue Router 路由守卫 + Ant Design Layout
- **IMPORTS**: `vue-router`, `ant-design-vue`
- **GOTCHA**: 路由守卫需区分 admin 和普通用户
- **VALIDATE**: 所有路由可访问，权限控制正确

### Task 16: 端到端集成验证
- **ACTION**: 完整用户流程验证
- **IMPLEMENT**:
  1. 访问首页 → 注册/登录
  2. 进入创作工坊 → 输入选题 → 观察标题 SSE 生成
  3. 选择标题 → 补充描述 → 观察大纲 SSE 生成
  4. 编辑大纲 → 确认 → 观察正文 SSE 生成 + 配图进度
  5. 文章完成 → 预览 → 导出 Markdown
  6. 查看文章列表 → 查看详情
  7. 查看 VIP 中心 → 查看配额
  8. (admin) 查看 Dashboard 统计
- **MIRROR**: 完整用户体验测试
- **IMPORTS**: N/A
- **GOTCHA**: SSE 在 Vite dev server 下通过 proxy 转发，需确认 proxy 配置支持 SSE
- **VALIDATE**: 端到端完成一次完整的创作流程

---

## Testing Strategy

### Component Tests

| Component | Test Case | Expected |
|---|---|---|
| MarkdownRenderer | 输入 Markdown | 正确渲染 HTML + 代码高亮 |
| MarkdownRenderer | 输入含图片 Markdown | 图片显示 |
| TopicInput | 输入选题 + 选择风格 | 触发 create API |
| TitleSelector | 选择标题 + 描述 | 触发 selectTitle API |
| StageIndicator | stage=outline | 第 2 阶段高亮 |
| SseConnector | 连接成功 | @open 事件触发 |
| SseConnector | 收到消息 | @message 事件触发 |

### E2E Tests

| Flow | Steps | Expected |
|---|---|---|
| 完整创作流程 | 登录→选题→标题→大纲→正文→导出 | 获得完整 Markdown |
| VIP 升级 | 配额用完→VIP 中心→支付→恢复 | 配额增加 |
| 文章管理 | 创作完成→文章列表→详情→删除 | 列表更新 |

### Edge Cases Checklist
- [ ] SSE 连接中断 → 自动重连 + 状态恢复
- [ ] 配图部分失败 → 已完成的图片正常展示
- [ ] 空大纲提交 → 前端验证拦截
- [ ] 超长正文渲染性能 → 虚拟滚动或分页
- [ ] 移动端布局 → 响应式适配
- [ ] 浏览器兼容性 → Chrome/Firefox/Safari

---

## Validation Commands

### TypeScript Check
```bash
cd frontend && ./node_modules/.bin/vue-tsc --noEmit
```
EXPECT: Zero type errors

### Vite Build
```bash
cd frontend && npm run build
```
EXPECT: Build successful

### Lint Check
```bash
cd frontend && npm run lint
```
EXPECT: No lint errors

### Browser Validation
```bash
cd frontend && npm run dev
```
- [ ] 首页展示正确
- [ ] 登录/注册流程正常
- [ ] 创作工坊三阶段完整
- [ ] SSE 实时更新
- [ ] Markdown 渲染正确（含代码高亮）
- [ ] 文章导出功能正常
- [ ] VIP 中心展示套餐
- [ ] Dashboard 统计图表正确
- [ ] 响应式布局在移动端正常

---

## Acceptance Criteria
- [ ] 创作工坊三阶段 UI 完整实现
- [ ] SSE 事件消费驱动实时 UI 更新
- [ ] Markdown 渲染含代码高亮
- [ ] 标题选择和大纲编辑交互正常
- [ ] 配图进度实时展示
- [ ] 文章可导出（复制 + 下载）
- [ ] VIP 中心和订单页面正常
- [ ] 个人中心和文章管理正常
- [ ] Dashboard 统计图表展示正确
- [ ] 所有页面响应式适配

## Completion Checklist
- [ ] 所有页面使用 Ant Design Vue 组件
- [ ] SSE 连接管理完善（断线重连）
- [ ] Markdown 渲染安全（html: false）
- [ ] 配额不足提示升级 VIP
- [ ] admin 角色可见 Dashboard
- [ ] 路由守卫正确拦截
- [ ] 无硬编码 URL（使用环境变量）
- [ ] 移动端响应式

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| SSE 通过 Vite proxy 转发问题 | M | H | 配置 proxy 支持 SSE（无 buffer） |
| Markdown 渲染 XSS | L | H | markdown-it html:false + sanitize |
| 高频 SSE 更新导致性能问题 | M | M | debounce + requestAnimationFrame |
| ECharts 包体积大 | L | L | 按需导入 echarts 组件 |
| 移动端布局错位 | M | L | Tailwind/Ant Design 响应式 |

## Notes

- SSE 连接的关键问题: EventSource 不支持自定义 header，token 需通过 URL query 传递
- 后端需在 JWT 中间件中同时支持 header 和 query 参数两种 token 传递方式
- Markdown 渲染组件需处理图片 URL（Phase 4 的 OSS URL）和 mermaid 代码块（Phase 4 的前端渲染）
- ECharts 按需导入可显著减少包体积: `import * as echarts from 'echarts/core'`
- Vite proxy 需要关闭 compress 中间件以支持 SSE: `proxy: { '/api': { ..., configure: (proxy) => proxy.on('proxyRes', res => delete res.headers['content-encoding']) } }`
- 创作工坊的核心挑战是 SSE 事件与 UI 状态的同步: 使用 Pinia store 作为单一数据源
