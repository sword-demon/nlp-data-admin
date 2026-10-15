<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * 配图结果 DTO（不可变）。
 *
 * - source       : 来源策略名（pexels/mermaid/iconify/emoji/svg/nanobanana）
 * - url          : 最终可访问 URL（已上传 OSS 则为 OSS URL；否则为原始 CDN URL）
 * - originalUrl  : 原始来源 URL（用于归属信息）
 * - alt          : alt 文本，用于 Markdown 占位符替换
 * - attribution  : 版权归属说明（如 "Photo by Jane Doe on Pexels"），可选
 * - mime         : 内容类型（image/jpeg, image/svg+xml, image/png 等）
 * - raw          : 策略附加信息（如 mermaid 代码），便于前端差异化渲染
 */
final class ImageResult
{
    public function __construct(
        public readonly string $source,
        public readonly string $url,
        public readonly string $originalUrl,
        public readonly string $alt,
        public readonly ?string $attribution = null,
        public readonly string $mime = 'image/jpeg',
        public readonly ?string $raw = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'url' => $this->url,
            'original_url' => $this->originalUrl,
            'alt' => $this->alt,
            'attribution' => $this->attribution,
            'mime' => $this->mime,
            'raw' => $this->raw,
        ];
    }
}
