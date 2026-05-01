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
     * 把 TopicResearchAgent 产出的 research_data 渲染为可拼在 user prompt 开头的 preamble。
     *
     * 返回值结构（无研究资料时返回空串，下游行为与改造前一致）：
     *
     *   【选题背景研究】
     *   {结构化事实清单 Markdown}
     *
     *   【参考来源】
     *   1. {title} — {url}
     *   2. ...
     *   ---
     *
     * @param array{summary?: ?string, sources?: array<int, array<string, mixed>>}|null $researchData
     */
    protected function buildResearchPreamble(?array $researchData): string
    {
        if (empty($researchData) || empty($researchData['summary'])) {
            return '';
        }
        $summary = trim((string) $researchData['summary']);
        if ($summary === '') {
            return '';
        }

        $sourcesLines = '';
        foreach ((array) ($researchData['sources'] ?? []) as $i => $s) {
            if (! is_array($s)) {
                continue;
            }
            $n = $i + 1;
            $title = trim((string) ($s['title'] ?? ''));
            $url = trim((string) ($s['url'] ?? ''));
            $sourcesLines .= "{$n}. {$title} — {$url}\n";
        }

        $preamble = "【选题背景研究】\n{$summary}\n";
        if ($sourcesLines !== '') {
            $preamble .= "\n【参考来源】\n{$sourcesLines}";
        }
        $preamble .= "---\n\n";
        return $preamble;
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
