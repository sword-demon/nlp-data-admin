<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use InvalidArgumentException;
use OpenApi\Attributes as OA;

/**
 * 大纲草稿 DTO（ADR-0003）。
 * OutlineGeneratorAgent 的 OK 结局 payload。
 *
 * 不变量：markdown 非空 && nodes 非空（否则抛异常 → FAILED 结局）。
 */
#[OA\Schema(
    schema: 'OutlineDraft',
    description: '大纲草稿，包含 Markdown 格式和结构化节点',
    type: 'object',
    required: ['markdown', 'nodes']
)]
final class OutlineDraft
{
    /**
     * @param array<int, OutlineNode> $nodes
     */
    public function __construct(
        #[OA\Property(property: 'markdown', description: '大纲的 Markdown 文本', type: 'string', example: "# 引言\n## 背景介绍\n# 正文")]
        public readonly string $markdown,

        #[OA\Property(
            property: 'nodes',
            description: '大纲节点列表',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/OutlineNode'),
            minItems: 1
        )]
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

    /**
     * 从数组创建实例
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $nodesData = (array) ($data['nodes'] ?? []);
        $nodes = [];

        foreach ($nodesData as $item) {
            if (is_array($item)) {
                $nodes[] = OutlineNode::fromArray($item);
            }
        }

        return new self(
            markdown: (string) ($data['markdown'] ?? ''),
            nodes: $nodes,
        );
    }
}
