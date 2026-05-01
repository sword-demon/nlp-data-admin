# Backend Context — 创作工坊领域语言

> 本文档由 `grill-with-docs` 惰性追加。术语以领域专家能理解为准，不耦合实现细节。

## 核心术语

| 术语             | 英文 / 字段                      | 含义                                                                                                    | 辨析                                     |
| ---------------- | -------------------------------- | ------------------------------------------------------------------------------------------------------- | ---------------------------------------- |
| **选题**         | `topic` / `article.topic`        | 用户一开始提交的种子主题，是整条创作链路的输入起点。                                                    | ≠ 标题、≠ 标题选择                       |
| **标题候选**     | `generated_titles`               | 标题 Agent 基于选题产出的 3-5 个候选方案（含分析、评分）。                                              | —                                        |
| **标题选择**     | `WorkshopState::TITLE_SELECTING` | human-in-the-loop 节点：用户从候选中挑一个作为正式标题。                                                | 日常口语中易与"选题"混淆，规范中必须区分 |
| **选中标题**     | `selected_title`                 | 标题选择节点完成后写入的正式标题，后续大纲/正文以此为根。                                               | —                                        |
| **大纲**         | `outline`                        | 二级结构：`{markdown, nodes[]}`，供正文逐节生成。                                                       | —                                        |
| **配图占位符**   | `placeholder://image/N`          | 正文中按约定语法插入的标记，后续由配图 Agent 替换为真实图。                                             | —                                        |
| **选题研究资料** | `research_data`                  | 针对选题执行网页检索后沉淀的事实摘要集合，作为下游各 Agent prompt 的认知基底。                          | 新增于 2026-05，TopicResearchAgent 产出  |
| **搜索提供商**   | `SearchProviderInterface`        | 对 Exa/Tavily 等外部搜索服务的适配层，负责把异构响应抹平成统一 `{results, meta}` shape。                | Agent 不感知底层 provider                |
| **研究降级**     | `research_fallback`              | 搜索服务不可用时 `research_data = null` 直接继续后续流程；响应里带 `research_fallback: true` 布尔提示。 | 外部服务失败**不得**击穿核心创作链路     |

## 状态机流转

```
DRAFT
  → TOPIC_RESEARCHING       (新增：基于选题做网页检索，产出 research_data)
  → TITLE_GENERATING
  → TITLE_SELECTING         (等待用户)
  → OUTLINE_GENERATING
  → OUTLINE_EDITING         (等待用户)
  → CONTENT_GENERATING
  → IMAGE_ANALYZING
  → IMAGE_GENERATING
  → COMPLETED
```

任意态 → `FAILED`（不可逆）。

## 研究资料结构（`article.research_data`）

两层分层序列化：

```jsonc
{
  "summary": "……Markdown 文本（非 JSON），见下方五段式契约……",
  "sources": [{ "title": "", "url": "", "snippet": "", "published_date": "" }],
}
```

`summary` 固定采用**结构化事实清单**五段式（Markdown），避免 LLM 输出作文式段落，降低下游幻觉。条数上限与 `numResults=3` 协调：

```
【核心概念】  定义性陈述 2-3 句
【关键事实】  3-5 条带数字/日期的事实，每条末尾 (来源 N)
【主流观点分歧】 1-3 条对立观点，每条末尾 (来源 N)
【可引用数据】 2-4 条带百分比/金额/时间的可复用数据点
【信息缺口】  0-3 条明确的"未知"，允许为空
```

下游 Agent 注入方式：在 user prompt 开头插入 `【选题背景研究】\n{summary}\n\n【参考来源】\n1. {title} — {url}\n...`，字符串拼接即可，无需二次解析。

## 研究链路四道防线

`TopicResearchAgent` 内部执行顺序：**缓存 → 单飞锁 → 限流闸 → Provider 搜索 → LLM 浓缩 → 失败降级**。

| 防线        | 机制                                                                 | 默认值 / Key                   |
| ----------- | -------------------------------------------------------------------- | ------------------------------ |
| 1. 缓存     | Redis `GET`，key = `research:cache:{md5(topic)}`                     | TTL 24h，`EXA_CACHE_TTL=86400` |
| 2. 单飞锁   | Redis `SET NX`，key = `research:lock:{md5(topic)}`，等待方 poll 缓存 | 锁 10s，等待最多 10s           |
| 3. 速率限流 | 令牌桶（Redis incr + expire 1s）                                     | `EXA_RATE_LIMIT_PER_SECOND=3`  |
| 4. 用户配额 | 复用 `QuotaService`（每次创作消费一次配额）                          | 不独立配                       |

触发限流 / 搜索失败 / API Key 未配置 → 全部走 `research_fallback`：不报错、不扣额外配额、正常进入 TITLE_GENERATING。

## 代码落位

```
backend/app/
├── Contract/
│   └── SearchProviderInterface.php        ← 新增 (search(query, options): {results, meta})
├── Service/
│   ├── Agent/
│   │   └── TopicResearchAgent.php         ← 业务编排（四道防线 + LLM 浓缩 + 降级）
│   ├── SearchProvider/
│   │   └── ExaProvider.php                ← MVP 唯一 provider；归一化 highlights → snippet
│   └── SearchProviderService.php          ← 工厂（类比 ModelProviderService）
├── Constants/AgentPrompts.php             追加 RESEARCH_SUMMARY_SYSTEM / _USER_TPL
└── config/autoload/search.php             ← 新增
```

命名约束：Agent 名**不含 provider 名**（避免抽象被名字拆穿）。永远是 `TopicResearchAgent`，不是 `ExaResearchAgent`。
