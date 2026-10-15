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

    /**
     * 支持工具调用的对话（Function Calling）。
     *
     * @param string $prompt 系统提示词
     * @param array<int, array{role: string, content: string}> $messages 对话历史
     * @param array<int, array{type: string, function: array}> $tools 工具定义，格式兼容 OpenAI Function Calling
     *   [
     *     ['type' => 'function', 'function' => [
     *       'name' => 'get_weather',
     *       'description' => '获取天气信息',
     *       'parameters' => [...JSON Schema...]
     *     ]]
     *   ]
     * @param array<string, mixed> $options 额外选项 (temperature, max_tokens 等)
     * @return array{content: ?string, tool_calls: ?array} 响应数据
     *   {
     *     "content": "文本回复" | null,
     *     "tool_calls": [
     *       {
     *         "id": "call_xxx",
     *         "type": "function",
     *         "function": {
     *           "name": "get_weather",
     *           "arguments": "{\"location\":\"北京\"}"
     *         }
     *       }
     *     ] | null
     *   }
     */
    public function chatWithTools(string $prompt, array $messages, array $tools, array $options = []): array;

    public function getName(): string;

    /** @return array<int, string> */
    public function getModels(): array;
}
