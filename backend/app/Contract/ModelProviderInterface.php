<?php

declare(strict_types=1);

namespace App\Contract;

use Generator;

/**
 * 模型 Provider 策略接口：供 DashScope / OpenAI-compatible 等具体 Provider 实现。
 */
interface ModelProviderInterface
{
    /**
     * 非流式补全。
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                              $options model/temperature/top_p 等
     */
    public function chat(string $prompt, array $messages = [], array $options = []): string;

    /**
     * 流式补全，逐 chunk 通过 Generator 产出文本增量。
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                              $options
     *
     * @return Generator<int, string>
     */
    public function chatStream(string $prompt, array $messages = [], array $options = []): Generator;

    public function getName(): string;

    /** @return array<int, string> */
    public function getModels(): array;
}
