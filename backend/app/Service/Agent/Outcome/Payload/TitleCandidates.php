<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use InvalidArgumentException;

/**
 * 标题候选集 DTO（ADR-0003）。
 * TitleGeneratorAgent 的 OK 结局 payload。
 *
 * 不变量：titles 非空（空集应抛 WORKSHOP_AGENT_FAILED 异常 → FAILED 结局）。
 */
final class TitleCandidates
{
    /**
     * @param array<int, TitleCandidate> $titles
     */
    public function __construct(
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
}
