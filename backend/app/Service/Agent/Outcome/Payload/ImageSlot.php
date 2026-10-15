<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ImageSlot',
    description: '已生成的配图槽位，包含图片 URL 和元数据',
    type: 'object',
    required: ['placeholder_id', 'type', 'source', 'url', 'original_url', 'keyword', 'alt', 'attribution', 'mime', 'raw', 'is_placeholder']
)]
final class ImageSlot
{
    public function __construct(
        #[OA\Property(property: 'placeholder_id', description: '关联的占位符 ID', type: 'string', example: 'img_001')]
        public readonly string $placeholderId,

        #[OA\Property(property: 'type', description: '图片类型', type: 'string', enum: ['photo', 'illustration', 'diagram'], example: 'photo')]
        public readonly string $type,

        #[OA\Property(property: 'source', description: '图片来源', type: 'string', enum: ['unsplash', 'pexels', 'generated', 'placeholder'], example: 'unsplash')]
        public readonly string $source,

        #[OA\Property(property: 'url', description: '图片最终 URL（可能是 OSS）', type: 'string', example: 'https://oss.example.com/images/abc123.jpg')]
        public readonly string $url,

        #[OA\Property(property: 'original_url', description: '图片原始 URL', type: 'string', example: 'https://images.unsplash.com/photo-123')]
        public readonly string $originalUrl,

        #[OA\Property(property: 'keyword', description: '搜索关键词', type: 'string', example: 'AI芯片')]
        public readonly string $keyword,

        #[OA\Property(property: 'alt', description: '图片 alt 文本', type: 'string', example: '人工智能芯片特写')]
        public readonly string $alt,

        #[OA\Property(property: 'attribution', description: '图片署名信息', type: 'string', nullable: true, example: 'Photo by John Doe on Unsplash')]
        public readonly ?string $attribution,

        #[OA\Property(property: 'mime', description: '图片 MIME 类型', type: 'string', example: 'image/jpeg')]
        public readonly string $mime,

        #[OA\Property(property: 'raw', description: '原始 API 响应数据', type: 'object', nullable: true)]
        public readonly mixed $raw,

        #[OA\Property(property: 'is_placeholder', description: '是否为占位图（策略链耗尽）', type: 'boolean', example: false)]
        public readonly bool $isPlaceholder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'placeholder_id' => $this->placeholderId,
            'type' => $this->type,
            'source' => $this->source,
            'url' => $this->url,
            'original_url' => $this->originalUrl,
            'keyword' => $this->keyword,
            'alt' => $this->alt,
            'attribution' => $this->attribution,
            'mime' => $this->mime,
            'raw' => $this->raw,
            'is_placeholder' => $this->isPlaceholder,
        ];
    }
}
