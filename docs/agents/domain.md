# Domain Docs

描述工程类技能（`improve-codebase-architecture` / `diagnose` / `tdd` / `zoom-out` / `to-prd` / `to-issues`）在本仓库中如何消费领域文档。

## 探索前先读这些

- 根 **`CONTEXT-MAP.md`** —— 本仓库是 multi-context monorepo，必先读此文件定位到具体的 context
- 对应 context 的 **`CONTEXT.md`**：
  - Backend 任务 → `backend/CONTEXT.md`
  - Frontend 任务 → `frontend/CONTEXT.md`
  - 跨前后端任务 → 两份都读
- 相关 **ADR** 目录：
  - `backend/docs/adr/` —— Backend 上下文内的架构决策
  - `frontend/docs/adr/` —— Frontend 上下文内的架构决策
  - `docs/adr/` —— 系统级 / 部署级 / 跨前后端决策

若任一文件不存在，**静默跳过**。不要提醒用户缺失，也不要上来就要求创建。产出者技能 `/grill-with-docs` 会在术语或决策真正被澄清时惰性生成。

## 文件结构

本仓库的 multi-context 布局（monorepo，`CONTEXT-MAP.md` 存在于根）：

```
/
├── CONTEXT-MAP.md                 ← 多 context 索引
├── docs/
│   ├── adr/                       ← 系统级 / 跨前后端 ADR
│   └── agents/                    ← 本文件所在目录
├── backend/
│   ├── CONTEXT.md                 ← Backend 领域语言（惰性生成）
│   └── docs/adr/                  ← Backend context 内 ADR
└── frontend/
    ├── CONTEXT.md                 ← Frontend 领域语言（惰性生成）
    └── docs/adr/                  ← Frontend context 内 ADR
```

## 使用术语表的词汇

输出中命名领域概念（issue 标题、重构提案、假设、测试名）时，使用对应 `CONTEXT.md` 中已定义的术语。不要漂移到术语表明确回避的同义词。

若需要的概念尚未出现在术语表 —— 这是一个信号：要么你在发明项目没有使用的语言（重新考虑），要么存在一个真实缺口（记下并交给 `/grill-with-docs` 处理）。

## ADR 冲突时显式上报

若你的产出与既有 ADR 抵触，显式指出而非默默覆盖：

> _Contradicts ADR-0007 (event-sourced orders) — 但值得重开因为……_

## 快速启发

| 场景               | 先读                                         |
| ------------------ | -------------------------------------------- |
| 修 Hyperf 后端 bug | `backend/CONTEXT.md` + `backend/docs/adr/`   |
| 重构 Vue 组件      | `frontend/CONTEXT.md` + `frontend/docs/adr/` |
| 调 API 契约        | 两份 CONTEXT.md + 根 `docs/adr/`             |
| Docker / 部署      | 根 `docs/adr/` + `docs/deployment.md`        |
