<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * ContentRevision
 *
 * 内容修订记录，包含修改前后对比
 *
 * @category Content
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/content.yaml
 */
class ContentRevision
{
    /**
     * 原始内容
     * @example "\u539f\u59cb\u6587\u672c\u5185\u5bb9..."
     * @var string
     */
    public string $original;

    /**
     * 修订后内容
     * @example "\u4fee\u8ba2\u540e\u7684\u6587\u672c\u5185\u5bb9..."
     * @var string
     */
    public string $revised;

    /**
     * 具体修改点列表
     * @var array[]
     */
    public array $changes;

    /**
     * 修订原因
     * @example "\u6839\u636e\u7528\u6237\u53cd\u9988\u4f18\u5316\u8bed\u6c14"
     * @var string
     */
    public string $reason;

    /**
     * Constructor
     *
     * @param string $original 原始内容
     * @param string $revised 修订后内容
     * @param array $changes 具体修改点列表
     * @param string $reason 修订原因
     */
    public function __construct(
        string $original,
        string $revised,
        array $changes,
        string $reason = '',
    ) {
        $this->original = $original;
        $this->revised = $revised;
        $this->changes = $changes;
        $this->reason = $reason;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'original' => $this->original,
            'revised' => $this->revised,
            'changes' => $this->changes,
            'reason' => $this->reason,
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
            original: $data['original'],
            revised: $data['revised'],
            changes: $data['changes'],
            reason: $data['reason'] ?? '',
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
        if (!isset($data['original'])) {
            throw new \InvalidArgumentException('Missing required field: original');
        }
        if (!isset($data['revised'])) {
            throw new \InvalidArgumentException('Missing required field: revised');
        }
        if (!isset($data['changes'])) {
            throw new \InvalidArgumentException('Missing required field: changes');
        }

        // 类型检查
        if (isset($data['original']) && !is_string($data['original'])) {
            throw new \InvalidArgumentException('Field original must be a string');
        }
        if (isset($data['revised']) && !is_string($data['revised'])) {
            throw new \InvalidArgumentException('Field revised must be a string');
        }
        if (isset($data['changes']) && !is_array($data['changes'])) {
            throw new \InvalidArgumentException('Field changes must be an array');
        }
        if (isset($data['reason']) && !is_string($data['reason'])) {
            throw new \InvalidArgumentException('Field reason must be a string');
        }
    }
}
