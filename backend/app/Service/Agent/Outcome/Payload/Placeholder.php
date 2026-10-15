<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Placeholder',
    description: '正文中的配图占位符',
    type: 'object',
    required: ['id', 'index', 'keyword']
)]
final class Placeholder
{
    public function __construct(
        #[OA\Property(property: 'id', description: '占位符唯一 ID', type: 'string', example: 'img_001')]
        public readonly string $id,

        #[OA\Property(property: 'index', description: '占位符在正文中的索引位置', type: 'integer', example: 150)]
        public readonly int $index,

        #[OA\Property(property: 'keyword', description: '配图关键词', type: 'string', example: '人工智能芯片')]
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
