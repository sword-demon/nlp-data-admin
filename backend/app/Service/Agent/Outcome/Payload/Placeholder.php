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

    /**
     * 从数组创建实例
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            index: (int) ($data['index'] ?? 0),
            keyword: (string) ($data['keyword'] ?? ''),
        );
    }
}
