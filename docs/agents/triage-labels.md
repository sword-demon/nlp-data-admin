# Triage Labels

技能内部以 5 个规范 triage 角色描述状态。本仓库采用**目录形式**实现这些标签 —— 标签 `X` 对应 `.scratch/X/` 子目录，移动文件即变更标签。

## 映射表

| Label in mattpocock/skills | Label in our tracker | 对应目录                    | 含义                             |
| -------------------------- | -------------------- | --------------------------- | -------------------------------- |
| `needs-triage`             | `needs-triage`       | `.scratch/needs-triage/`    | 新建、待维护者评估               |
| `needs-info`               | `needs-info`         | `.scratch/needs-info/`      | 等待反馈者补充信息               |
| `ready-for-agent`          | `ready-for-agent`    | `.scratch/ready-for-agent/` | 已充分描述，AFK agent 可直接领取 |
| `ready-for-human`          | `ready-for-human`    | `.scratch/ready-for-human/` | 需要人工实现                     |
| `wontfix`                  | `wontfix`            | `.scratch/wontfix/`         | 不处理                           |

## 使用规则

- 技能提到 "apply the AFK-ready label" → 把 issue 文件移到 `.scratch/ready-for-agent/`
- 技能提到 "apply the needs-info label" → 把 issue 文件移到 `.scratch/needs-info/`
- 若 issue 文件顶部 frontmatter 有 `Status:` 行，移动后**同步更新**该行
- 新建 issue 默认进入 `.scratch/needs-triage/`
- 跨 feature 目录的 issue（位于 `.scratch/<feature>/issues/`）不受 triage 状态机管理，其状态由文件头 `Status:` 行声明
