<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TitleCandidate',
    description: '单个标题候选项，包含标题文本、分析和评分',
    type: 'object',
    required: ['title', 'analysis', 'score']
)]
final class TitleCandidate
{
    public function __construct(
        #[OA\Property(property: 'title', description: '候选标题', type: 'string', example: 'AI 技术如何改变世界')]
        public readonly string $title,

        #[OA\Property(property: 'analysis', description: '标题分析说明', type: 'string', example: '该标题简洁明了，突出了 AI 的影响力')]
        public readonly string $analysis,

        #[OA\Property(property: 'score', description: '标题评分 1-10', type: 'integer', minimum: 1, maximum: 10, example: 8)]
        public readonly int $score,
    ) {}

    /**
     * @return array{title: string, analysis: string, score: int}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'analysis' => $this->analysis,
            'score' => $this->score,
        ];
    }

    /**
     * 从数组创建实例
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: (string) ($data['title'] ?? ''),
            analysis: (string) ($data['analysis'] ?? ''),
            score: (int) ($data['score'] ?? 0),
        );
    }
}
