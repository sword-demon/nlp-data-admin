# AGENTS.md

This file tells coding agents (Qoder, Claude Code, Codex, Cursor, 等) how to work in this repository.

## Project overview

**NLP Data Admin** — 一个 monorepo，包含：

- `backend/` — Hyperf 3 + Swoole + PHP 8.2 API 服务端（多 Agent 编排、VIP 支付、可观测性、多策略配图）
- `frontend/` — Vue 3 + TypeScript + Pinia + Ant Design Vue 管理后台
- `docker/` — 生产环境 docker-compose（MySQL + Redis + Nginx + backend）
- `docs/` — API 与部署参考文档

规范化规则目录：`.qoder/rules/`（`backend.md` / `frontend.md`），在对应栈工作时请先阅读。

## Agent skills

### Issue tracker

Issues 和 PRD 以 markdown 文件形式存于 `.scratch/`，triage 状态由**所在顶层目录**编码（移动文件 = 改标签）。详见 [`docs/agents/issue-tracker.md`](./docs/agents/issue-tracker.md)。

### Triage labels

5 个规范角色（`needs-triage` / `needs-info` / `ready-for-agent` / `ready-for-human` / `wontfix`）映射为 `.scratch/` 下的 5 个同名子目录。详见 [`docs/agents/triage-labels.md`](./docs/agents/triage-labels.md)。

### Domain docs

Multi-context monorepo：根 [`CONTEXT-MAP.md`](./CONTEXT-MAP.md) 指向 `backend/CONTEXT.md` 和 `frontend/CONTEXT.md`；context 级 ADR 分别在 `backend/docs/adr/` 和 `frontend/docs/adr/`，系统级 ADR 在 `docs/adr/`。详见 [`docs/agents/domain.md`](./docs/agents/domain.md)。

> `backend/CONTEXT.md` / `frontend/CONTEXT.md` 尚未建立 —— `grill-with-docs` 会在领域术语首次被澄清时惰性生成。
