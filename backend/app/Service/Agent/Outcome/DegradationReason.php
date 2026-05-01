<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome;

/**
 * 降级理由的语义化枚举（ADR-0003 第 2 节）。
 *
 * 仅后端可见，经 AgentLogAspect 写入 agent_logs.error_message 供可观测性面板聚合；
 * **不得** 出现在 HTTP 响应体中（ADR-0001 约束）。
 *
 * 新增降级理由必须同步此枚举——这是 ADR-0003 强制"显式化静默降级"的关键约束。
 */
enum DegradationReason: string
{
    /** TopicResearch：空 topic 输入。 */
    case EMPTY_INPUT = 'empty_input';

    /** TopicResearch：令牌桶拒绝。 */
    case RATE_LIMITED = 'rate_limited';

    /** TopicResearch：单飞锁等缓存超时。 */
    case LOCK_TIMEOUT = 'lock_timeout';

    /** TopicResearch：provider 未启用或 API Key 缺失。 */
    case PROVIDER_DISABLED = 'provider_disabled';

    /** TopicResearch：搜索返回 0 命中，或浓缩输出为空。 */
    case EXTERNAL_EMPTY_RESULT = 'external_empty_result';

    /** TopicResearch / Image*：上游 HTTP / 超时等异常。 */
    case EXTERNAL_FAILED = 'external_failed';

    /** ImageAnalyzer：LLM JSON 解析失败，走默认填充。 */
    case PARSE_FAILED = 'parse_failed';

    /** ParallelImageGenerator：fallback_chain 全失败，塞占位图。 */
    case STRATEGY_CHAIN_EXHAUSTED = 'strategy_chain_exhausted';
}
