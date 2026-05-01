# ADR 0002 — 搜索能力抽象为 SearchProviderInterface，Agent 命名去 provider 化

- **日期**：2026-05-01
- **状态**：Accepted
- **上下文级别**：Backend

## 背景

2026-05 在创作工坊流程中新增"选题研究"步骤，需要调用外部搜索 API（首发 Exa）对选题做网页检索，产出 `research_data` 喂给下游 Agent。

可预见的未来替换需求：

- Exa 定价涨价 / 速率下降时切 Tavily、Serper、Brave Search
- 国内部署场景可能需要 Bing API / 必应 / 百度搜索
- 评测时同时对比多个 provider 的召回质量

如果 Agent 层直接 `new ExaClient()`，上述切换都要大改业务代码。

## 决策

### 1. 抽象 `SearchProviderInterface`

新建契约，职责边界**极窄**：只做"外部搜索 API 适配"，不做业务。

```php
interface SearchProviderInterface
{
    public function getName(): string;

    /**
     * @return array{
     *   results: array<int, array{title:string, url:string, snippet:string, published_date:?string, score?:float}>,
     *   meta: array<string, mixed>
     * }
     */
    public function search(string $query, array $options = []): array;
}
```

归一化关键约定：`snippet` 是**单一字符串**。Exa 的 `highlights[]` 由 `ExaProvider` 内部 `implode("\n", ...)`；Tavily 的 `content` 直接用。Agent 永远拿字符串，不感知底层差异。

### 2. 对齐现有 Provider 模式

| 用途             | Interface                     | 工厂                        | 实现目录                      | 配置                             |
| ---------------- | ----------------------------- | --------------------------- | ----------------------------- | -------------------------------- |
| AI 模型          | `ModelProviderInterface`      | `ModelProviderService`      | `Service/Provider/`           | `config/autoload/model.php`      |
| 配图策略         | `ImageStrategyInterface`      | `ImageStrategyFactory`      | `Service/ImageStrategy/`      | `config/autoload/image.php`      |
| **搜索（新增）** | **`SearchProviderInterface`** | **`SearchProviderService`** | **`Service/SearchProvider/`** | **`config/autoload/search.php`** |

### 3. Agent 命名**不得**绑定 provider

`TopicResearchAgent`（关于做什么），不是 `ExaResearchAgent`（关于用谁做）。同理：

- `AgentLog` 的 `name` 字段存 `topic_research`，不存 `exa_research`
- `AgentPrompts` 的常量叫 `RESEARCH_SUMMARY_*`，不叫 `EXA_*`

### 4. 业务/适配分层

| 层       | 类                                    | 职责                                                   |
| -------- | ------------------------------------- | ------------------------------------------------------ |
| Agent    | `TopicResearchAgent`                  | 缓存、单飞、限流、降级、**LLM 浓缩 summary**、AgentLog |
| 工厂     | `SearchProviderService`               | 按配置实例化 provider                                  |
| Provider | `ExaProvider` / 未来 `TavilyProvider` | 纯 HTTP 调用 + 响应归一化                              |

**LLM 浓缩在 Agent 层**——这是关键选择。换 provider 时浓缩逻辑零改动。

## 考虑过的备选

- **直接在 Agent 里 `new ExaClient()`**：否决。违背项目已建立的 Provider 模式（Model / ImageStrategy 都已抽象）。
- **把 LLM 浓缩放 Provider 层**：否决。Provider 职责应保持"纯适配"，混入 LLM 逻辑后换 provider 时要重写浓缩，抽象失败。
- **Agent 名叫 `ExaResearchAgent`**：否决。名字绑死 Exa 让将来替换成本隐式增加（文件重命名、AgentLog 历史数据混乱、prompt 常量名冲突）。

## 影响

- 新增文件结构见 `backend/CONTEXT.md` 的"代码落位"章节。
- `.env.example` 新增 `EXA_ENABLED` / `EXA_API_KEY` / `EXA_CACHE_TTL` / `EXA_RATE_LIMIT_PER_SECOND` 等键，未来 Tavily 追加 `TAVILY_*` 键即可。
- Phase 3 的 `WorkshopOrchestrator::startCreation` 在 `TITLE_GENERATING` 之前插入 `TOPIC_RESEARCHING` 状态，调用 `TopicResearchAgent`。
