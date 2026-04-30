<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Constants\Code;
use App\Exception\BusinessException;

/**
 * Agent 共用工具：prompt 模板渲染 + 鲁棒 JSON 解析。
 */
abstract class AbstractAgent
{
    /** 将模板中的 {key} 替换为 $vars[key]；未命中的占位符保留原样。 */
    protected function render(string $template, array $vars): string
    {
        $replace = [];
        foreach ($vars as $k => $v) {
            $replace['{' . $k . '}'] = (string) $v;
        }
        return strtr($template, $replace);
    }

    /**
     * 尽力解码模型输出的 JSON：
     * - 去除可能的 ```json / ``` 围栏
     * - 定位第一个 [ 或 { 到最后一个 ] 或 }
     * - 解析失败抛 BusinessException(WORKSHOP_PARSE_ERROR)
     *
     * @return array<mixed>
     */
    protected function decodeJson(string $raw, string $agentName): array
    {
        $text = trim($raw);

        // 去除围栏
        if (str_starts_with($text, '```')) {
            $text = (string) preg_replace('/^```(?:json)?\s*/i', '', $text);
            $text = (string) preg_replace('/\s*```$/', '', $text);
        }

        // 定位 JSON 的起止字符
        $startPositions = array_filter([strpos($text, '['), strpos($text, '{')], static fn($v) => $v !== false);
        $endPositions = array_filter([strrpos($text, ']'), strrpos($text, '}')], static fn($v) => $v !== false);
        if (! empty($startPositions) && ! empty($endPositions)) {
            $start = min($startPositions);
            $end = max($endPositions);
            if ($end > $start) {
                $text = substr($text, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new BusinessException(
                Code::WORKSHOP_PARSE_ERROR,
                "[{$agentName}] failed to parse JSON output",
                500
            );
        }

        return $decoded;
    }
}
