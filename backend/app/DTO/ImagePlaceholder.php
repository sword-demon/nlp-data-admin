<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * ImagePlaceholder
 *
 * 图片占位符，标记图片插入位置和属性
 *
 * @category Images
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/images.yaml
 */
class ImagePlaceholder
{
    /**
     * 插入位置（段落号或章节标识）
     * @example "\u7b2c 2 \u6bb5\u540e"
     * @var string
     */
    public string $position;

    /**
     * 占位文本
     * @example "[\u56fe\u7247\uff1aAI \u82af\u7247\u793a\u610f\u56fe]"
     * @var string
     */
    public string $placeholder_text;

    /**
     * 
     * @var ImageryRequirement
     */
    public mixed $requirement;

    /**
     * 图片 alt 文本（无障碍）
     * @example "AI \u82af\u7247\u7684\u5185\u90e8\u7ed3\u6784\u793a\u610f\u56fe"
     * @var string
     */
    public string $alt_text;

    /**
     * Constructor
     *
     * @param string $position 插入位置（段落号或章节标识）
     * @param string $placeholder_text 占位文本
     * @param ImageryRequirement $requirement 
     * @param string $alt_text 图片 alt 文本（无障碍）
     */
    public function __construct(
        string $position,
        string $placeholder_text,
        ImageryRequirement $requirement = null,
        string $alt_text = '',
    ) {
        $this->position = $position;
        $this->placeholder_text = $placeholder_text;
        $this->requirement = $requirement;
        $this->alt_text = $alt_text;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'placeholder_text' => $this->placeholder_text,
            'requirement' => $this->requirement,
            'alt_text' => $this->alt_text,
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
            position: $data['position'],
            placeholder_text: $data['placeholder_text'],
            requirement: $data['requirement'] ?? null,
            alt_text: $data['alt_text'] ?? '',
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
        if (!isset($data['position'])) {
            throw new \InvalidArgumentException('Missing required field: position');
        }
        if (!isset($data['placeholder_text'])) {
            throw new \InvalidArgumentException('Missing required field: placeholder_text');
        }

        // 类型检查
        if (isset($data['position']) && !is_string($data['position'])) {
            throw new \InvalidArgumentException('Field position must be a string');
        }
        if (isset($data['placeholder_text']) && !is_string($data['placeholder_text'])) {
            throw new \InvalidArgumentException('Field placeholder_text must be a string');
        }
        if (isset($data['alt_text']) && !is_string($data['alt_text'])) {
            throw new \InvalidArgumentException('Field alt_text must be a string');
        }
    }
}
