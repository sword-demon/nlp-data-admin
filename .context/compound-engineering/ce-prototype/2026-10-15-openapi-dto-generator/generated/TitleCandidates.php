<?php

declare(strict_types=1);

namespace Generated;

use InvalidArgumentException;

/**
 * TitleCandidates
 */
final class TitleCandidates
{
    public function __construct(
        public readonly array $titles,
    ) {
        if (empty($titles)) {
            throw new InvalidArgumentException('TitleCandidates requires at least 1 item(s) in titles');
        }
    }

    /**
     * @return array{titles: array}
     */
    public function toArray(): array
    {
        return [
            'titles' => array_map(static fn($t) => $t->toArray(), $this->titles),
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
            titles: (static function() use ($data) {
                $items = (array) ($data['titles'] ?? []);
                $result = [];
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $result[] = TitleCandidate::fromArray($item);
                    }
                }
                return $result;
            })(),
        );
    }
}
