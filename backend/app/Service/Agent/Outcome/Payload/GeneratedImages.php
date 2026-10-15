<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use OpenApi\Attributes as OA;

/**
 * 已生成配图集 DTO（ADR-0003）。
 * ParallelImageGenerator 的 payload。
 *
 * 当任一 ImageSlot.isPlaceholder=true 时，Agent 构造 DEGRADED + STRATEGY_CHAIN_EXHAUSTED。
 */
#[OA\Schema(
    schema: 'GeneratedImages',
    description: '已生成的配图集合',
    type: 'object',
    required: ['images']
)]
final class GeneratedImages
{
    /**
     * @param array<int, ImageSlot> $images
     */
    public function __construct(
        #[OA\Property(
            property: 'images',
            description: '配图槽位列表',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ImageSlot')
        )]
        public readonly array $images,
    ) {}

    /**
     * 是否存在任何占位图（fallback_chain 已耗尽）。
     */
    public function hasAnyPlaceholder(): bool
    {
        foreach ($this->images as $img) {
            if ($img->isPlaceholder) {
                return true;
            }
        }
        return false;
    }

    /**
     * 供 WorkshopOrchestrator::replaceImagePlaceholders 使用的 array shape。
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn(ImageSlot $s) => $s->toArray(), $this->images);
    }
}
