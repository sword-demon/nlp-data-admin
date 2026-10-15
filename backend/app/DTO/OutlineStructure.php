<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * OutlineStructure
 *
 * 完整的文章大纲结构
 *
 * @category Outline
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/outline.yaml
 */
class OutlineStructure
{
    /**
     * 所有大纲节点（扁平列表）
     * @var OutlineNode[]
     */
    public array $nodes;

    /**
     * 总章节数
     * @example 5
     * @var int
     */
    public int $total_sections;

    /**
     * 最大嵌套深度
     * @example 3
     * @var int
     */
    public int $max_depth;

    /**
     * Constructor
     *
     * @param array $nodes 所有大纲节点（扁平列表）
     * @param int $total_sections 总章节数
     * @param int $max_depth 最大嵌套深度
     */
    public function __construct(
        array $nodes,
        int $total_sections,
        int $max_depth = 0,
    ) {
        $this->nodes = $nodes;
        $this->total_sections = $total_sections;
        $this->max_depth = $max_depth;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodes' => $this->nodes,
            'total_sections' => $this->total_sections,
            'max_depth' => $this->max_depth,
        ];
    }

    /**
     * Create from array
     *
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            nodes: $data['nodes'],
            total_sections: $data['total_sections'],
            max_depth: $data['max_depth'] ?? 0,
        );
    }

    /**
     * Validate data
     *
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException
     * @return void
     */
    public static function validate(array $data): void
    {
        // 检查必需字段
        if (!isset($data['nodes'])) {
            throw new \InvalidArgumentException('Missing required field: nodes');
        }
        if (!isset($data['total_sections'])) {
            throw new \InvalidArgumentException('Missing required field: total_sections');
        }

        // 类型检查
        if (isset($data['nodes']) && !is_array($data['nodes'])) {
            throw new \InvalidArgumentException('Field nodes must be an array');
        }
        if (isset($data['total_sections']) && !is_int($data['total_sections'])) {
            throw new \InvalidArgumentException('Field total_sections must be an integer');
        }
        if (isset($data['total_sections']) && $data['total_sections'] < 1) {
            throw new \InvalidArgumentException('Field total_sections must be >= 1');
        }
        if (isset($data['max_depth']) && !is_int($data['max_depth'])) {
            throw new \InvalidArgumentException('Field max_depth must be an integer');
        }
        if (isset($data['max_depth']) && $data['max_depth'] < 1) {
            throw new \InvalidArgumentException('Field max_depth must be >= 1');
        }
    }
}
