# Issue Tracker: Local Markdown

本仓库的 Issues 与 PRD 以 markdown 文件形式存于 `.scratch/`，triage 状态由**所在子目录**编码。

## 目录布局

```
.scratch/
├── needs-triage/          新建、待维护者评估
├── needs-info/            等待反馈者补充信息
├── ready-for-agent/       已充分描述，AFK agent 可直接领取
├── ready-for-human/       需要人工实现
├── wontfix/               不处理
└── <feature-slug>/
    ├── PRD.md
    └── issues/
        ├── 01-<slug>.md
        └── 02-<slug>.md
```

## 约定

- **每个功能一个目录**：`.scratch/<feature-slug>/`
- **PRD 位置**：`.scratch/<feature-slug>/PRD.md`
- **实现 issue**：`.scratch/<feature-slug>/issues/<NN>-<slug>.md`，编号从 `01` 起
- **Triage 状态 = 文件所在的顶层目录**。移动文件即变更标签
- 可选：在 issue 文件顶部写 `Status: <label>` 作为镜像（便于 grep）
- **评论** 追加到文件底部的 `## Comments` 段落

## 文件头建议模板

```markdown
---
Status: needs-triage
Created: 2026-05-01
---

# <Issue 标题>

## 背景

...

## 验收标准

- [ ] ...

## Comments
```

## 当技能说 "publish to the issue tracker"

- 新建独立 issue：写入 `.scratch/needs-triage/<slug>.md`
- 基于已有 feature：写入 `.scratch/<feature-slug>/issues/<NN>-<slug>.md`

## 当技能说 "fetch the relevant ticket"

读取被引用路径的文件。用户通常会直接传入路径或 slug。

## 当技能说 "move to <label>"

把文件从原顶层目录 `mv` 到新顶层目录，并同步更新文件头的 `Status:` 行（若存在）。
