<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

final class TitleCandidate
{
    public function __construct(
        public readonly string $title,
        public readonly string $analysis,
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
}
