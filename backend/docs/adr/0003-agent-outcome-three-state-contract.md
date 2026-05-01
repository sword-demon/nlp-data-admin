# ADR 0003 — Agent 执行结局契约：三态 Outcome + 流后归一

- **日期**：2026-05-02
- **状态**：Accepted
- **上下文级别**：Backend

## 背景

2026-04 至 2026-05 完成 Phase 3/4 与选题研究 Agent 五个 slice 后，六个 Agent（TopicResearch / TitleGenerator / OutlineGenerator / ContentGenerator / ImageAnalyzer / ParallelImageGenerator）共用 `AgentInterface`，返回 `array<string, mixed>`。

积累出的问题：

1. **返回契约是"形状约定"而非"深模块"**：`WorkshopOrchestrator` 手写 `(array) ($result['titles'] ?? [])` / `$result['research_data'] ?? null` 这样的防御式解构散落在 10+ 处，任何字段改名都要跨多个文件搜改。PHPStan 只能推到 `mixed`。
2. **降级路径不统一，且部分是"静默降级"**：TopicResearchAgent 的降级已显式冒泡到 `research_fallback`（ADR-0001 / Slice-05）；但 `ImageAnalyzerAgent` 的 JSON 解析失败默认填充、`ParallelImageGenerator` 的全链路失败塞 `via.placeholder.com` 兜底图，都对编排器**完全静默**，只能翻日志定位。ADR-0001 要求的"可观测性面板按 `agent_name + status` 聚合暴露静默降级率"因此无处落地。
3. **流式 Agent 的 Result 无处归属**：`ContentGeneratorAgent::executeStream` 是 `Generator<int, string>`，编排器 `foreach` 完拿 chunk 后要再调 `extractPlaceholders()` 才能拿到 placeholder 列表——流尾的结构化产出既不在 Generator 里也不在 `execute()` 里。

## 决策

### 1. 引入 `AgentOutcome` 值对象（三态契约）

```php
enum OutcomeStatus: string { case OK; case DEGRADED; case FAILED; }

final class AgentOutcome {
    public function __construct(
        public readonly OutcomeStatus $status,
        public readonly object $payload,             // 六个 readonly DTO 之一
        public readonly ?DegradationReason $reason = null,
        public readonly ?string $detail = null,
    ) {}

    public static function ok(object $payload): self;
    public static function degraded(object $payload, DegradationReason $r, ?string $d = null): self;
}
```

**不变量**：

- `status === OK` ⇒ `reason === null && detail === null`
- `status === DEGRADED` ⇒ `reason !== null`；`payload` 仍须是可供下游使用的完整 shape（"状态机不绕行" —— ADR-0001 的翻译）
- `status === FAILED` 不由 Agent 构造：Agent 遇到核心能力失败抛异常，AgentLogAspect 捕获后记录，异常自然冒泡到 `WorkshopOrchestrator::markFailed`

### 2. `DegradationReason` 枚举（8 个初始值）

| 枚举                       | 出处                    | 语义                                                         |
| -------------------------- | ----------------------- | ------------------------------------------------------------ |
| `EMPTY_INPUT`              | TopicResearch           | 空 topic                                                     |
| `RATE_LIMITED`             | TopicResearch           | 令牌桶拒绝                                                   |
| `LOCK_TIMEOUT`             | TopicResearch           | 单飞锁等缓存超时                                             |
| `PROVIDER_DISABLED`        | TopicResearch           | API Key 缺失 / 全局开关 off                                  |
| `EXTERNAL_EMPTY_RESULT`    | TopicResearch           | 搜索 0 命中                                                  |
| `EXTERNAL_FAILED`          | TopicResearch / Image\* | 上游 HTTP / 超时                                             |
| `PARSE_FAILED`             | ImageAnalyzer           | LLM JSON 解析失败，走默认填充                                |
| `STRATEGY_CHAIN_EXHAUSTED` | ParallelImageGenerator  | 任一占位图 fallback_chain 耗尽，回落到 `via.placeholder.com` |

新增降级理由 **必须** 同步 enum；这是"强制显式化静默降级"的关键约束。

### 3. 六个 Payload DTO（每个 Agent 一个）

| DTO               | 产出 Agent             | 关键字段                                                   |
| ----------------- | ---------------------- | ---------------------------------------------------------- |
| `ResearchBundle`  | TopicResearch          | `topic, provider, queriedAt, summary, sources[], fallback` |
| `TitleCandidates` | TitleGenerator         | `titles: TitleCandidate[]`                                 |
| `OutlineDraft`    | OutlineGenerator       | `markdown, nodes: OutlineNode[]`                           |
| `ContentDraft`    | ContentGenerator       | `content, placeholders: Placeholder[], wordCount`          |
| `ImageAnalyses`   | ImageAnalyzer          | `analyses: ImageAnalysis[]`                                |
| `GeneratedImages` | ParallelImageGenerator | `images: ImageSlot[]`                                      |

DTO 内部字段全为 `readonly`，构造器强制守护关键不变量（如 `TitleCandidates::titles` 非空）。

**DB JSON 映射策略**（ResearchBundle ↔ `articles.research_data`）：由 **Orchestrator** 负责单向序列化，不强制下游 Agent 持有 DTO。

- **写入**：`$article->research_data = $outcome->payload->toJsonColumn();`（降级结局返回 `null`，保持与历史 `{summary, sources}` shape 完全兼容）。
- **读取（下游 Agent）**：继续从 `$context['research_data']` 读取 `array|null`，经 `AbstractAgent::buildResearchPreamble(?array $researchData)` 拼 preamble。下游 Agent 不感知 `ResearchBundle` DTO，保留与 Phase 3 同形的轻量 context 传递路径。
- **为什么不提供 `fromArray()`**：实现中仅提供 `toJsonColumn()`（写 DB）和 `toPreambleArray()`（读 DTO 自身拼 preamble）；后者目前无调用点，作为未来灵活性预留。当 Orchestrator 转为"从 DB 重建 DTO 并透传给下游"时，再补 `fromArray()` 形成完整往返。

### 4. 流式 Agent 回归契约：PHP Generator 的 return value

`AgentInterface::executeStream` 升级为 `@return Generator<int, string, mixed, AgentOutcome>`：

```php
foreach ($agent->executeStream($ctx) as $chunk) {
    $this->sse->push($stream, $chunk);  // 继续推 SSE chunk
}
$outcome = $generator->getReturn();     // 流尾拿结构化 Outcome
```

零破坏式归一：Title/Outline/ImageAnalyzer/ParallelImage 的 `executeStream` 本就是"execute 完一次性 yield JSON"，改造成末尾 `return $outcome` 即可；只有 ContentGenerator 是真流式。

### 5. `AgentLogAspect` 三态持久化

迁移 `agent_logs.status` 枚举：`enum('running','success','failed')` → `enum('running','success','degraded','failed')`。零数据回填（旧数据全是 success/failed）。

Aspect 识别返回值：

- `AgentOutcome` 且 `status === DEGRADED` ⇒ 写 `status=degraded`；`reason + detail` 拼入 `error_message` 列（复用列，不新增字段）
- `AgentOutcome` 且 `status === OK` ⇒ 写 `status=success`，`output_summary = json_encode($payload)`
- 抛异常 ⇒ 写 `status=failed`（维持原行为）

可观测性面板 `GROUP BY agent_name, status` 即出**降级率曲线**——ADR-0001 承诺落地。

### 6. 可见性边界

`AgentOutcome` 是 **Agent ↔ Orchestrator 的私有契约**，**不**穿透到 HTTP 响应：

- 前端继续只看 `research_fallback: boolean` 等业务布尔（ADR-0001 约定）
- `DegradationReason` / `detail` 永远不进入 HTTP 响应体

## 考虑过的备选

- **二态 `{ok, failed}` + payload 里的业务布尔（research_fallback / is_placeholder）**：否决。无法让 ImageAnalyzer / ParallelImage 的静默降级被 Aspect 感知；ADR-0001 的"可观测性面板暴露静默降级率"无处落地。
- **只给关键 DTO 做强类型，其余留 `array`（C3 妥协方案）**：否决。契约层面分裂，新人读 Orchestrator 要问"哪个 Agent 是 DTO 哪个是 array"。
- **新旧接口并行共存（`executeAsOutcome()`）**：否决。唯一消费者是 `WorkshopOrchestrator`，无外部兼容负担；双契约期反而让代码库短期存在两套真理，AI/新人读起来更累。
- **把 Outcome 穿到 HTTP 响应**：否决。前端不需要知道 `STRATEGY_CHAIN_EXHAUSTED` 这种后端词汇；ADR-0001 已约定响应不暴露后端实现细节。

## 影响

- `backend/app/Service/Agent/` 新增 `Outcome/` 子目录（`AgentOutcome.php`、`OutcomeStatus.php`、`DegradationReason.php` + 6 个 payload DTO）
- `AgentInterface` 签名升级，六个 Agent 与 `WorkshopOrchestrator` 一次性迁移
- 新增迁移 `2026_05_02_000001_add_degraded_status_to_agent_logs.php`
- `AgentLogAspect` 感知 Outcome 三态；`AgentLog::STATUS_DEGRADED = 'degraded'` 常量
- `backend/CONTEXT.md` 增补"Agent 执行结局 / 降级理由 / 六个 DTO"术语
- 与 ADR-0001（降级策略）/ ADR-0002（搜索抽象）交叉引用
