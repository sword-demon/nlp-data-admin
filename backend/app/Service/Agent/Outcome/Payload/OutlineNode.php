<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OutlineNode',
    description: '大纲节点，表示大纲中的一个条目',
    type: 'object',
    required: ['id', 'text', 'level', 'parent_id']
)]
final class OutlineNode
{
    public function __construct(
        #[OA\Property(property: 'id', description: '节点 ID', type: 'integer', example: 1)]
        public readonly int $id,

        #[OA\Property(property: 'text', description: '节点文本内容', type: 'string', example: '引言')]
        public readonly string $text,

        #[OA\Property(property: 'level', description: '层级深度，1 为一级标题', type: 'integer', minimum: 1, example: 1)]
        public readonly int $level,

        #[OA\Property(property: 'parent_id', description: '父节点 ID，顶层节点为 null', type: 'integer', nullable: true, example: null)]
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
