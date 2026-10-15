<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use OpenApi\Attributes as OA;

/**
 * 选题研究资料 DTO（ADR-0003）。
 *
 * 与 articles.research_data JSON 列双向映射：
 * - OK 结局：fallback=false，summary/sources 非空
 * - DEGRADED 结局：fallback=true，summary/sources 可能为空
 *
 * toArray()/fromArray() 保持与历史数据兼容的 snake_case shape，
 * 下游 AbstractAgent::buildResearchPreamble() 仍然从 array 读取。
 */
#[OA\Schema(
    schema: 'ResearchBundle',
    description: '选题研究资料包，包含研究摘要和来源列表',
    type: 'object',
    required: ['topic', 'provider', 'queried_at', 'summary', 'sources', 'fallback']
)]
final class ResearchBundle
{
    /**
     * @param array<int, array<string, mixed>> $sources
     */
    public function __construct(
        #[OA\Property(property: 'topic', description: '选题标题', type: 'string', example: 'AI 技术发展趋势')]
        public readonly string $topic,

        #[OA\Property(property: 'provider', description: '研究数据提供商', type: 'string', example: 'exa')]
        public readonly string $provider,

        #[OA\Property(property: 'queried_at', description: '查询时间 ISO8601', type: 'string', format: 'date-time', example: '2026-10-14T12:00:00+00:00')]
        public readonly string $queriedAt,

        #[OA\Property(property: 'summary', description: '研究摘要', type: 'string', example: '人工智能技术近年来取得重大突破...')]
        public readonly string $summary,

        #[OA\Property(
            property: 'sources',
            description: '参考来源列表',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'AI 研究报告'),
                    new OA\Property(property: 'url', type: 'string', example: 'https://example.com/report'),
                    new OA\Property(property: 'snippet', type: 'string', example: '摘录内容...')
                ]
            )
        )]
        public readonly array $sources,

        #[OA\Property(property: 'fallback', description: '是否降级到空数据', type: 'boolean', example: false)]
        public readonly bool $fallback,
    ) {}

    public static function degradedEmpty(string $topic, string $provider = ''): self
    {
        return new self(
            topic: $topic,
            provider: $provider,
            queriedAt: date('c'),
            summary: '',
            sources: [],
            fallback: true,
        );
    }

    /**
     * 序列化到 articles.research_data。
     * fallback=true 时返回 null（兼容历史行为：降级路径 DB 存 null）。
     *
     * @return array<string, mixed>|null
     */
    public function toJsonColumn(): ?array
    {
        if ($this->fallback || $this->summary === '') {
            return null;
        }
        return [
            'summary' => $this->summary,
            'sources' => $this->sources,
        ];
    }

    /**
     * 用于下游 Agent 拼 preamble 时读取的 array shape（兼容 AbstractAgent::buildResearchPreamble）。
     *
     * @return array{summary: string, sources: array<int, array<string, mixed>>}|null
     */
    public function toPreambleArray(): ?array
    {
        if ($this->fallback || $this->summary === '') {
            return null;
        }
        return [
            'summary' => $this->summary,
            'sources' => $this->sources,
        ];
    }
}
