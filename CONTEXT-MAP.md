# CONTEXT-MAP

本 monorepo 有两个领域 context，领域语言相互独立。

| Context                                                  | 位置        | CONTEXT.md            | ADRs                 |
| -------------------------------------------------------- | ----------- | --------------------- | -------------------- |
| Backend API（Hyperf 3 + Swoole + PHP 8.2）               | `backend/`  | `backend/CONTEXT.md`  | `backend/docs/adr/`  |
| Frontend Admin UI（Vue 3 + TS + Pinia + Ant Design Vue） | `frontend/` | `frontend/CONTEXT.md` | `frontend/docs/adr/` |

跨领域 / 部署 / 基础设施决策放根 `docs/adr/`。

## 读取规则

- **单 context 任务**：只读该 context 自身的 `CONTEXT.md` + 其 `docs/adr/`
- **跨前后端任务**：读两份 CONTEXT.md + 两侧 ADR + 根 `docs/adr/`
- 若文件尚未存在，**静默跳过** —— 不要提醒用户创建，也不要上来就要求创建

## 惰性生成

`backend/CONTEXT.md` 与 `frontend/CONTEXT.md` 会由 `grill-with-docs` 在领域术语首次被审问澄清时自动追加内容。不必提前写空壳。
