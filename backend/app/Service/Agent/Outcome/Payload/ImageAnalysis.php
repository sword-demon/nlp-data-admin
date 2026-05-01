<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

final class ImageAnalysis
{
    /**
     * @param array<int, string> $keywords
     */
    public function __construct(
        public readonly string $placeholderId,
        public readonly string $context,
        public readonly array $keywords,
        public readonly string $suggestedType,
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
