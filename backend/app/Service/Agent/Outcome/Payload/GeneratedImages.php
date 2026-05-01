<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

/**
 * 已生成配图集 DTO（ADR-0003）。
 * ParallelImageGenerator 的 payload。
 *
 * 当任一 ImageSlot.isPlaceholder=true 时，Agent 构造 DEGRADED + STRATEGY_CHAIN_EXHAUSTED。
 */
final class GeneratedImages
{
    /**
     * @param array<int, ImageSlot> $images
     */
    public function __construct(
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
