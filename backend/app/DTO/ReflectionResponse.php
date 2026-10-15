<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * ReflectionResponse
 *
 * 反思响应，包含评估结果和改进建议
 *
 * @category Reflection
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/reflection.yaml
 */
class ReflectionResponse
{
    /**
     * 各维度评分（1-10）
     * @example {"\u76f8\u5173\u6027":9,"\u6e05\u6670\u5ea6":8,"\u5438\u5f15\u529b":7}
     * @var array<string, integer>
     */
    public array $scores;

    /**
     * 综合质量评分（1-10）
     * @example 8
     * @var int
     */
    public int $overall_quality;

    /**
     * 详细反馈意见
     * @example "\u6807\u9898\u6574\u4f53\u8d28\u91cf\u826f\u597d\uff0c\u5efa\u8bae\u589e\u5f3a\u5438\u5f15\u529b"
     * @var string
     */
    public string $feedback;

    /**
     * 改进建议列表
     * @example ["\u6dfb\u52a0\u6570\u5b57\u6216\u6570\u636e\u589e\u5f3a\u53ef\u4fe1\u5ea6","\u4f7f\u7528\u7591\u95ee\u53e5\u5f0f\u6fc0\u53d1\u597d\u5947\u5fc3"]
     * @var string[]
     */
    public array $suggestions;

    /**
     * 是否需要修订
     * @example false
     * @var bool
     */
    public bool $needs_revision;

    /**
     * Constructor
     *
     * @param array $scores 各维度评分（1-10）
     * @param int $overall_quality 综合质量评分（1-10）
     * @param string $feedback 详细反馈意见
     * @param array $suggestions 改进建议列表
     * @param bool $needs_revision 是否需要修订
     */
    public function __construct(
        array $scores,
        int $overall_quality,
        string $feedback = '',
        array $suggestions = [],
        bool $needs_revision = false,
    ) {
        $this->scores = $scores;
        $this->overall_quality = $overall_quality;
        $this->feedback = $feedback;
        $this->suggestions = $suggestions;
        $this->needs_revision = $needs_revision;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scores' => $this->scores,
            'overall_quality' => $this->overall_quality,
            'feedback' => $this->feedback,
            'suggestions' => $this->suggestions,
            'needs_revision' => $this->needs_revision,
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
            scores: $data['scores'],
            overall_quality: $data['overall_quality'],
            feedback: $data['feedback'] ?? '',
            suggestions: $data['suggestions'] ?? [],
            needs_revision: $data['needs_revision'] ?? false,
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
        if (!isset($data['scores'])) {
            throw new \InvalidArgumentException('Missing required field: scores');
        }
        if (!isset($data['overall_quality'])) {
            throw new \InvalidArgumentException('Missing required field: overall_quality');
        }

        // 类型检查
        if (isset($data['scores']) && !is_array($data['scores'])) {
            throw new \InvalidArgumentException('Field scores must be an array');
        }
        if (isset($data['overall_quality']) && !is_int($data['overall_quality'])) {
            throw new \InvalidArgumentException('Field overall_quality must be an integer');
        }
        if (isset($data['overall_quality']) && $data['overall_quality'] < 1) {
            throw new \InvalidArgumentException('Field overall_quality must be >= 1');
        }
        if (isset($data['overall_quality']) && $data['overall_quality'] > 10) {
            throw new \InvalidArgumentException('Field overall_quality must be <= 10');
        }
        if (isset($data['feedback']) && !is_string($data['feedback'])) {
            throw new \InvalidArgumentException('Field feedback must be a string');
        }
        if (isset($data['suggestions']) && !is_array($data['suggestions'])) {
            throw new \InvalidArgumentException('Field suggestions must be an array');
        }
        if (isset($data['needs_revision']) && !is_bool($data['needs_revision'])) {
            throw new \InvalidArgumentException('Field needs_revision must be a boolean');
        }
    }
}
