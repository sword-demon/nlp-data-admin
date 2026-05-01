<?php

declare(strict_types=1);

namespace App\Contract;

use App\Service\Agent\Outcome\AgentOutcome;
use Generator;

/**
 * Agent 统一接口（ADR-0003）：所有创作工坊智能体均实现此接口。
 *
 * - execute(): 同步执行，返回 AgentOutcome（OK / DEGRADED 两态；FAILED 由异常冒泡产生）
 * - executeStream(): 流式执行，Generator 每次 yield 一个文本增量 chunk；
 *                    流尾通过 `$generator->getReturn()` 拿到结构化 AgentOutcome。
 *
 * $context 约定字段（按 Agent 不同使用）：
 *   topic, style, title, supplement, outline_markdown, content, placeholders, analyses, research_data
 */
interface AgentInterface
{
    public function getName(): string;

    /**
     * @param array<string, mixed> $context
     */
    public function execute(array $context): AgentOutcome;

    /**
     * @param array<string, mixed> $context
     * @return Generator<int, string, mixed, AgentOutcome>
     */
    public function executeStream(array $context): Generator;
}
