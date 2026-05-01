<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

final class Placeholder
{
    public function __construct(
        public readonly string $id,
        public readonly int $index,
        public readonly string $keyword,
    ) {}

    /**
     * @return array{id: string, index: int, keyword: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'index' => $this->index,
            'keyword' => $this->keyword,
        ];
    }
}
