<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * ImageryRequirement
 *
 * 图片需求定义，描述所需图片的特征和用途
 *
 * @category Images
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/images.yaml
 */
class ImageryRequirement
{
    /**
     * 图片内容描述
     * @example "\u4e00\u5f20\u5c55\u793a AI \u82af\u7247\u7684\u9ad8\u6e05\u7167\u7247"
     * @var string
     */
    public string $description;

    /**
     * 使用目的
     * @example "\u6587\u7ae0\u914d\u56fe\uff0c\u8bf4\u660e\u786c\u4ef6\u57fa\u7840"
     * @var string
     */
    public string $purpose;

    /**
     * 风格要求
     * @example "\u73b0\u4ee3\u79d1\u6280\u611f\uff0c\u84dd\u8272\u8c03"
     * @var string|null
     */
    public string|null $style = null;

    /**
     * 尺寸要求
     * @var array|null
     */
    public array|null $dimensions = null;

    /**
     * 关键词列表
     * @example ["AI","\u82af\u7247","\u6280\u672f"]
     * @var string[]
     */
    public array $keywords;

    /**
     * Constructor
     *
     * @param string $description 图片内容描述
     * @param string $purpose 使用目的
     * @param string|null $style 风格要求
     * @param array|null $dimensions 尺寸要求
     * @param array $keywords 关键词列表
     */
    public function __construct(
        string $description,
        string $purpose,
        array $keywords = [],
        ?string $style = null,
        ?array $dimensions = null,
    ) {
        $this->description = $description;
        $this->purpose = $purpose;
        $this->style = $style;
        $this->dimensions = $dimensions;
        $this->keywords = $keywords;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'purpose' => $this->purpose,
            'style' => $this->style,
            'dimensions' => $this->dimensions,
            'keywords' => $this->keywords,
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
            description: $data['description'],
            purpose: $data['purpose'],
            keywords: $data['keywords'] ?? [],
            style: $data['style'] ?? null,
            dimensions: $data['dimensions'] ?? null,
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
        if (!isset($data['description'])) {
            throw new \InvalidArgumentException('Missing required field: description');
        }
        if (!isset($data['purpose'])) {
            throw new \InvalidArgumentException('Missing required field: purpose');
        }

        // 类型检查
        if (isset($data['description']) && !is_string($data['description'])) {
            throw new \InvalidArgumentException('Field description must be a string');
        }
        if (isset($data['purpose']) && !is_string($data['purpose'])) {
            throw new \InvalidArgumentException('Field purpose must be a string');
        }
        if (isset($data['style']) && !is_string($data['style']) && !is_null($data['style'])) {
            throw new \InvalidArgumentException('Field style must be a string');
        }
        if (isset($data['dimensions']) && !is_array($data['dimensions']) && !is_null($data['dimensions'])) {
            throw new \InvalidArgumentException('Field dimensions must be an array');
        }
        if (isset($data['keywords']) && !is_array($data['keywords'])) {
            throw new \InvalidArgumentException('Field keywords must be an array');
        }
    }
}
