<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use InvalidArgumentException;
use OpenApi\Attributes as OA;

/**
 * 标题候选集 DTO（ADR-0003）。
 * TitleGeneratorAgent 的 OK 结局 payload。
 *
 * 不变量：titles 非空（空集应抛 WORKSHOP_AGENT_FAILED 异常 → FAILED 结局）。
 */
#[OA\Schema(
    schema: 'TitleCandidates',
    description: '标题候选集，包含多个候选标题',
    type: 'object',
    required: ['titles']
)]
final class TitleCandidates
{
    /**
     * @param array<int, TitleCandidate> $titles
     */
    public function __construct(
        #[OA\Property(
            property: 'titles',
            description: '候选标题列表',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/TitleCandidate'),
            minItems: 1
        )]
        public readonly array $titles,
    ) {
        if (empty($titles)) {
            throw new InvalidArgumentException('TitleCandidates requires at least one title');
        }
    }

    /**
     * @return array<int, array{title: string, analysis: string, score: int}>
     */
    public function toArray(): array
    {
        return array_map(static fn(TitleCandidate $t) => $t->toArray(), $this->titles);
    }

    /**
     * 从数组创建实例
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $titlesData = (array) ($data['titles'] ?? $data ?? []);
        $titles = [];

        foreach ($titlesData as $item) {
            if (is_array($item)) {
                $titles[] = TitleCandidate::fromArray($item);
            }
        }

        return new self($titles);
    }
}
