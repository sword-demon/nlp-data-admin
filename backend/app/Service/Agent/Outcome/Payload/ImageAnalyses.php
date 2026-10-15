<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use OpenApi\Attributes as OA;

/**
 * 配图分析集 DTO（ADR-0003）。
 * ImageAnalyzerAgent 的 payload。
 *
 * 空分析（无 placeholders）是合法的 OK 结局，对应正文未插入任何配图标记。
 * LLM JSON 解析失败导致的默认填充应构造为 DEGRADED 结局 + PARSE_FAILED。
 */
#[OA\Schema(
    schema: 'ImageAnalyses',
    description: '配图分析集合，包含所有占位符的分析结果',
    type: 'object',
    required: ['analyses']
)]
final class ImageAnalyses
{
    /**
     * @param array<int, ImageAnalysis> $analyses
     */
    public function __construct(
        #[OA\Property(
            property: 'analyses',
            description: '配图分析列表',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ImageAnalysis')
        )]
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
