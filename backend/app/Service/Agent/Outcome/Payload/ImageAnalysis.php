<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ImageAnalysis',
    description: '单个配图占位符的分析结果',
    type: 'object',
    required: ['placeholder_id', 'context', 'keywords', 'suggested_type', 'reasoning']
)]
final class ImageAnalysis
{
    /**
     * @param array<int, string> $keywords
     */
    public function __construct(
        #[OA\Property(property: 'placeholder_id', description: '关联的占位符 ID', type: 'string', example: 'img_001')]
        public readonly string $placeholderId,

        #[OA\Property(property: 'context', description: '占位符上下文文本', type: 'string', example: '讨论人工智能芯片的段落')]
        public readonly string $context,

        #[OA\Property(
            property: 'keywords',
            description: '搜索关键词列表',
            type: 'array',
            items: new OA\Items(type: 'string', example: 'AI芯片'),
            minItems: 1
        )]
        public readonly array $keywords,

        #[OA\Property(property: 'suggested_type', description: '建议的配图类型', type: 'string', enum: ['photo', 'illustration', 'diagram'], example: 'photo')]
        public readonly string $suggestedType,

        #[OA\Property(property: 'reasoning', description: '选择该类型的理由', type: 'string', example: '技术产品适合使用实物照片')]
        public readonly string $reasoning,
    ) {}

    /**
     * @return array{placeholder_id: string, context: string, keywords: array<int, string>, suggested_type: string, reasoning: string}
     */
    public function toArray(): array
    {
        return [
            'placeholder_id' => $this->placeholderId,
            'context' => $this->context,
            'keywords' => $this->keywords,
            'suggested_type' => $this->suggestedType,
            'reasoning' => $this->reasoning,
        ];
    }
}
