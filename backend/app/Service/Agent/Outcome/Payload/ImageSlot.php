<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

final class ImageSlot
{
    public function __construct(
        public readonly string $placeholderId,
        public readonly string $type,
        public readonly string $source,
        public readonly string $url,
        public readonly string $originalUrl,
        public readonly string $keyword,
        public readonly string $alt,
        public readonly ?string $attribution,
        public readonly string $mime,
        public readonly mixed $raw,
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
