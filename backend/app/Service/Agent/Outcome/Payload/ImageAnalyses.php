<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

/**
 * 配图分析集 DTO（ADR-0003）。
 * ImageAnalyzerAgent 的 payload。
 *
 * 空分析（无 placeholders）是合法的 OK 结局，对应正文未插入任何配图标记。
 * LLM JSON 解析失败导致的默认填充应构造为 DEGRADED 结局 + PARSE_FAILED。
 */
final class ImageAnalyses
{
    /**
     * @param array<int, ImageAnalysis> $analyses
     */
    public function __construct(
        public readonly array $analyses,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (ImageAnalysis $a) => $a->toArray(), $this->analyses);
    }
}
