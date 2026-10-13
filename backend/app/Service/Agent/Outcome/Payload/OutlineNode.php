<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

final class OutlineNode
{
    public function __construct(
        public readonly int $id,
        public readonly string $text,
        public readonly int $level,
        public readonly ?int $parentId,
    ) {}

    /**
     * @return array{id: int, text: string, level: int, parent_id: ?int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'level' => $this->level,
            'parent_id' => $this->parentId,
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
            id: (int) ($data['id'] ?? 0),
            text: (string) ($data['text'] ?? ''),
            level: (int) ($data['level'] ?? 1),
            parentId: isset($data['parent_id']) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null,
        );
    }
}
