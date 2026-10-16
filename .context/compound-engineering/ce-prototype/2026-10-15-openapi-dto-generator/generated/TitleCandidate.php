<?php

declare(strict_types=1);

namespace Generated;

use InvalidArgumentException;

/**
 * 标题候选项
 */
final class TitleCandidate
{
    public function __construct(
        public readonly string $title,
        public readonly string $analysis,
        public readonly int $score,
    ) {
    }

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
