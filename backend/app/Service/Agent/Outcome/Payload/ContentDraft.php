<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use OpenApi\Attributes as OA;

/**
 * 正文草稿 DTO（ADR-0003）。
 * ContentGeneratorAgent 流式结束后作为 Generator return value 返回。
 */
#[OA\Schema(
    schema: 'ContentDraft',
    description: '正文草稿，包含内容、占位符和字数统计',
    type: 'object',
    required: ['content', 'placeholders', 'word_count']
)]
final class ContentDraft
{
    /**
     * @param array<int, Placeholder> $placeholders
     */
    public function __construct(
        #[OA\Property(property: 'content', description: '正文内容（Markdown 格式）', type: 'string', example: '# 引言\n\n人工智能技术...[img:img_001]...')]
        public readonly string $content,

        #[OA\Property(
            property: 'placeholders',
            description: '配图占位符列表',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Placeholder')
        )]
        public readonly array $placeholders,

        #[OA\Property(property: 'word_count', description: '正文字数', type: 'integer', example: 1500)]
        public readonly int $wordCount,
    ) {}

    /**
     * @return array<int, array{id: string, index: int, keyword: string}>
     */
    public function placeholdersAsArray(): array
    {
        return array_map(static fn(Placeholder $p) => $p->toArray(), $this->placeholders);
    }

    /**
     * 从数组创建实例
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $placeholdersData = (array) ($data['placeholders'] ?? []);
        $placeholders = [];

        foreach ($placeholdersData as $item) {
            if (is_array($item)) {
                $placeholders[] = Placeholder::fromArray($item);
            }
        }

        return new self(
            content: (string) ($data['content'] ?? ''),
            placeholders: $placeholders,
            wordCount: (int) ($data['word_count'] ?? 0),
        );
    }

    /**
     * toArray() 用于序列化
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'placeholders' => $this->placeholdersAsArray(),
            'word_count' => $this->wordCount,
        ];
    }
}
