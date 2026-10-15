<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * ContentDraft
 *
 * 内容草稿，包含文章正文和元信息
 *
 * @category Content
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/content.yaml
 */
class ContentDraft
{
    /**
     * 正文内容
     * @example "\u8fd9\u662f\u4e00\u7bc7\u5173\u4e8e AI \u6280\u672f\u7684\u6587\u7ae0..."
     * @var string
     */
    public string $text;

    /**
     * 字数统计
     * @example 1500
     * @var int
     */
    public int $word_count;

    /**
     * 章节列表（按顺序）
     * @example ["\u5f15\u8a00","\u6280\u672f\u80cc\u666f","\u5e94\u7528\u6848\u4f8b","\u603b\u7ed3"]
     * @var string[]
     */
    public array $sections;

    /**
     * 附加元信息
     * @example {"author":"AI Writer","created_at":"2026-10-15T10:00:00Z"}
     * @var array<string, mixed>
     */
    public array $metadata;

    /**
     * Constructor
     *
     * @param string $text 正文内容
     * @param int $word_count 字数统计
     * @param array $sections 章节列表（按顺序）
     * @param array $metadata 附加元信息
     */
    public function __construct(
        string $text,
        int $word_count,
        array $sections = [],
        array $metadata = [],
    ) {
        $this->text = $text;
        $this->word_count = $word_count;
        $this->sections = $sections;
        $this->metadata = $metadata;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'word_count' => $this->word_count,
            'sections' => $this->sections,
            'metadata' => $this->metadata,
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
            text: $data['text'],
            word_count: $data['word_count'],
            sections: $data['sections'] ?? [],
            metadata: $data['metadata'] ?? [],
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
        if (!isset($data['text'])) {
            throw new \InvalidArgumentException('Missing required field: text');
        }
        if (!isset($data['word_count'])) {
            throw new \InvalidArgumentException('Missing required field: word_count');
        }

        // 类型检查
        if (isset($data['text']) && !is_string($data['text'])) {
            throw new \InvalidArgumentException('Field text must be a string');
        }
        if (isset($data['word_count']) && !is_int($data['word_count'])) {
            throw new \InvalidArgumentException('Field word_count must be an integer');
        }
        if (isset($data['word_count']) && $data['word_count'] < 0) {
            throw new \InvalidArgumentException('Field word_count must be >= 0');
        }
        if (isset($data['sections']) && !is_array($data['sections'])) {
            throw new \InvalidArgumentException('Field sections must be an array');
        }
        if (isset($data['metadata']) && !is_array($data['metadata'])) {
            throw new \InvalidArgumentException('Field metadata must be an array');
        }
    }
}
