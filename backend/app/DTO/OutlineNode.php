<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * OutlineNode
 *
 * 大纲节点，表示文章结构的一个层级
 *
 * @category Outline
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/outline.yaml
 */
class OutlineNode
{
    /**
     * 节点唯一标识
     * @example 1
     * @var int
     */
    public int $id;

    /**
     * 节点文本内容
     * @example "\u5f15\u8a00"
     * @var string
     */
    public string $text;

    /**
     * 层级深度，1 为一级标题
     * @example 1
     * @var int
     */
    public int $level;

    /**
     * 父节点 ID，顶层节点为 null
     * @var int|null
     */
    public int|null $parent_id = null;

    /**
     * 子节点列表
     * @var OutlineNode[]
     */
    public array $children;

    /**
     * Constructor
     *
     * @param int $id 节点唯一标识
     * @param string $text 节点文本内容
     * @param int $level 层级深度，1 为一级标题
     * @param int|null $parent_id 父节点 ID，顶层节点为 null
     * @param array $children 子节点列表
     */
    public function __construct(
        int $id,
        string $text,
        int $level,
        array $children = [],
        ?int $parent_id = null,
    ) {
        $this->id = $id;
        $this->text = $text;
        $this->level = $level;
        $this->parent_id = $parent_id;
        $this->children = $children;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'level' => $this->level,
            'parent_id' => $this->parent_id,
            'children' => $this->children,
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
            id: $data['id'],
            text: $data['text'],
            level: $data['level'],
            children: $data['children'] ?? [],
            parent_id: $data['parent_id'] ?? null,
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
        if (!isset($data['id'])) {
            throw new \InvalidArgumentException('Missing required field: id');
        }
        if (!isset($data['text'])) {
            throw new \InvalidArgumentException('Missing required field: text');
        }
        if (!isset($data['level'])) {
            throw new \InvalidArgumentException('Missing required field: level');
        }

        // 类型检查
        if (isset($data['id']) && !is_int($data['id'])) {
            throw new \InvalidArgumentException('Field id must be an integer');
        }
        if (isset($data['text']) && !is_string($data['text'])) {
            throw new \InvalidArgumentException('Field text must be a string');
        }
        if (isset($data['level']) && !is_int($data['level'])) {
            throw new \InvalidArgumentException('Field level must be an integer');
        }
        if (isset($data['level']) && $data['level'] < 1) {
            throw new \InvalidArgumentException('Field level must be >= 1');
        }
        if (isset($data['parent_id']) && !is_int($data['parent_id']) && !is_null($data['parent_id'])) {
            throw new \InvalidArgumentException('Field parent_id must be an integer');
        }
        if (isset($data['children']) && !is_array($data['children'])) {
            throw new \InvalidArgumentException('Field children must be an array');
        }
    }
}
