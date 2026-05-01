<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

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
final class ResearchBundle
{
    /**
     * @param array<int, array<string, mixed>> $sources
     */
    public function __construct(
        public readonly string $topic,
        public readonly string $provider,
        public readonly string $queriedAt,
        public readonly string $summary,
        public readonly array $sources,
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
