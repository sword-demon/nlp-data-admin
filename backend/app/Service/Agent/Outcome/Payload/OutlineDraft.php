<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use InvalidArgumentException;

/**
 * 大纲草稿 DTO（ADR-0003）。
 * OutlineGeneratorAgent 的 OK 结局 payload。
 *
 * 不变量：markdown 非空 && nodes 非空（否则抛异常 → FAILED 结局）。
 */
final class OutlineDraft
{
    /**
     * @param array<int, OutlineNode> $nodes
     */
    public function __construct(
        public readonly string $markdown,
        public readonly array $nodes,
    ) {
        if ($markdown === '' || empty($nodes)) {
            throw new InvalidArgumentException('OutlineDraft requires non-empty markdown and nodes');
        }
    }

    /**
     * 向后兼容 WorkshopOrchestrator 写入 articles.outline JSON 的 shape。
     *
     * @return array{markdown: string, nodes: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'markdown' => $this->markdown,
            'nodes' => array_map(static fn(OutlineNode $n) => $n->toArray(), $this->nodes),
        ];
    }
}
