<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * TitleDecision
 *
 * 最终标题决策结果，包含选中标题和决策依据
 *
 * @category Title
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/title.yaml
 */
class TitleDecision
{
    /**
     * 最终选中的标题
     * @example "AI \u6280\u672f\u5982\u4f55\u6539\u53d8\u4e16\u754c"
     * @var string
     */
    public string $selected_title;

    /**
     * 选择该标题的原因
     * @example "\u8be5\u6807\u9898\u6700\u80fd\u5438\u5f15\u76ee\u6807\u8bfb\u8005\uff0c\u540c\u65f6\u6e05\u6670\u4f20\u8fbe\u4e3b\u9898"
     * @var string
     */
    public string $reason;

    /**
     * 备选标题列表
     * @example ["AI \u7684\u672a\u6765\u5c55\u671b","\u4eba\u5de5\u667a\u80fd\u6539\u53d8\u751f\u6d3b"]
     * @var string[]
     */
    public array $alternatives;

    /**
     * Constructor
     *
     * @param string $selected_title 最终选中的标题
     * @param string $reason 选择该标题的原因
     * @param array $alternatives 备选标题列表
     */
    public function __construct(
        string $selected_title,
        string $reason,
        array $alternatives = [],
    ) {
        $this->selected_title = $selected_title;
        $this->reason = $reason;
        $this->alternatives = $alternatives;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'selected_title' => $this->selected_title,
            'reason' => $this->reason,
            'alternatives' => $this->alternatives,
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
            selected_title: $data['selected_title'],
            reason: $data['reason'],
            alternatives: $data['alternatives'] ?? [],
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
        if (!isset($data['selected_title'])) {
            throw new \InvalidArgumentException('Missing required field: selected_title');
        }
        if (!isset($data['reason'])) {
            throw new \InvalidArgumentException('Missing required field: reason');
        }

        // 类型检查
        if (isset($data['selected_title']) && !is_string($data['selected_title'])) {
            throw new \InvalidArgumentException('Field selected_title must be a string');
        }
        if (isset($data['reason']) && !is_string($data['reason'])) {
            throw new \InvalidArgumentException('Field reason must be a string');
        }
        if (isset($data['alternatives']) && !is_array($data['alternatives'])) {
            throw new \InvalidArgumentException('Field alternatives must be an array');
        }
    }
}
