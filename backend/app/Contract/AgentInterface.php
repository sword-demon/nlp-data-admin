<?php

declare(strict_types=1);

namespace App\Contract;

use Generator;

/**
 * Agent 统一接口：所有创作工坊智能体均实现此接口。
 *
 * - execute($context)：同步执行，返回结构化结果（JSON 可序列化数组）
 * - executeStream($context)：流式执行，Generator 每次 yield 一个文本增量 chunk
 *
 * $context 约定字段（按 Agent 不同使用）：
 *   topic, style, title, supplement, outline, content, placeholders, image_analyses
 */
interface AgentInterface
{
    public function getName(): string;

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function execute(array $context): array;

    /**
     * @param array<string, mixed> $context
     * @return Generator<int, string>
     */
    public function executeStream(array $context): Generator;
}
