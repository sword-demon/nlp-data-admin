<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

/**
 * 正文草稿 DTO（ADR-0003）。
 * ContentGeneratorAgent 流式结束后作为 Generator return value 返回。
 */
final class ContentDraft
{
    /**
     * @param array<int, Placeholder> $placeholders
     */
    public function __construct(
        public readonly string $content,
        public readonly array $placeholders,
        public readonly int $wordCount,
    ) {}

    /**
     * @return array<int, array{id: string, index: int, keyword: string}>
     */
    public function placeholdersAsArray(): array
    {
        return array_map(static fn(Placeholder $p) => $p->toArray(), $this->placeholders);
    }
}
