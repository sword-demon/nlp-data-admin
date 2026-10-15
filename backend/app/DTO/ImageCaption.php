<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * ImageCaption
 *
 * 图片标题和说明文字
 *
 * @category Images
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/images.yaml
 */
class ImageCaption
{
    /**
     * 标题文字
     * @example "\u56fe 1\uff1aAI \u82af\u7247\u67b6\u6784"
     * @var string
     */
    public string $caption;

    /**
     * 详细说明
     * @example "\u8be5\u82af\u7247\u91c7\u7528 7nm \u5de5\u827a\uff0c\u4e13\u4e3a\u6df1\u5ea6\u5b66\u4e60\u4f18\u5316"
     * @var string|null
     */
    public string|null $description = null;

    /**
     * 图片来源
     * @example "NVIDIA \u5b98\u7f51"
     * @var string|null
     */
    public string|null $source = null;

    /**
     * 版权信息
     * @example "CC BY-SA 4.0"
     * @var string|null
     */
    public string|null $license = null;

    /**
     * Constructor
     *
     * @param string $caption 标题文字
     * @param string|null $description 详细说明
     * @param string|null $source 图片来源
     * @param string|null $license 版权信息
     */
    public function __construct(
        string $caption,
        ?string $description = null,
        ?string $source = null,
        ?string $license = null
    ) {
        $this->caption = $caption;
        $this->description = $description;
        $this->source = $source;
        $this->license = $license;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'caption' => $this->caption,
            'description' => $this->description,
            'source' => $this->source,
            'license' => $this->license,
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
            caption: $data['caption'],
            description: $data['description'] ?? null,
            source: $data['source'] ?? null,
            license: $data['license'] ?? null
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
        if (!isset($data['caption'])) {
            throw new \InvalidArgumentException('Missing required field: caption');
        }

        // 类型检查
        if (isset($data['caption']) && !is_string($data['caption'])) {
            throw new \InvalidArgumentException('Field caption must be a string');
        }
        if (isset($data['description']) && !is_string($data['description']) && !is_null($data['description'])) {
            throw new \InvalidArgumentException('Field description must be a string');
        }
        if (isset($data['source']) && !is_string($data['source']) && !is_null($data['source'])) {
            throw new \InvalidArgumentException('Field source must be a string');
        }
        if (isset($data['license']) && !is_string($data['license']) && !is_null($data['license'])) {
            throw new \InvalidArgumentException('Field license must be a string');
        }
    }
}
