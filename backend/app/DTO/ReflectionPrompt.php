<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * ReflectionPrompt
 *
 * 反思提示，引导 Agent 进行自我评估
 *
 * @category Reflection
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/reflection.yaml
 */
class ReflectionPrompt
{
    /**
     * 反思问题
     * @example "\u8fd9\u4e2a\u6807\u9898\u662f\u5426\u80fd\u5438\u5f15\u76ee\u6807\u8bfb\u8005\uff1f"
     * @var string
     */
    public string $question;

    /**
     * 上下文信息
     * @example "\u76ee\u6807\u8bfb\u8005\u662f\u6280\u672f\u7231\u597d\u8005"
     * @var string|null
     */
    public string|null $context = null;

    /**
     * 评估维度列表
     * @example ["\u76f8\u5173\u6027","\u6e05\u6670\u5ea6","\u5438\u5f15\u529b"]
     * @var string[]
     */
    public array $dimensions;

    /**
     * Constructor
     *
     * @param string $question 反思问题
     * @param string|null $context 上下文信息
     * @param array $dimensions 评估维度列表
     */
    public function __construct(
        string $question,
        array $dimensions = [],
        ?string $context = null,
    ) {
        $this->question = $question;
        $this->context = $context;
        $this->dimensions = $dimensions;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'question' => $this->question,
            'context' => $this->context,
            'dimensions' => $this->dimensions,
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
            question: $data['question'],
            dimensions: $data['dimensions'] ?? [],
            context: $data['context'] ?? null,
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
        if (!isset($data['question'])) {
            throw new \InvalidArgumentException('Missing required field: question');
        }

        // 类型检查
        if (isset($data['question']) && !is_string($data['question'])) {
            throw new \InvalidArgumentException('Field question must be a string');
        }
        if (isset($data['context']) && !is_string($data['context']) && !is_null($data['context'])) {
            throw new \InvalidArgumentException('Field context must be a string');
        }
        if (isset($data['dimensions']) && !is_array($data['dimensions'])) {
            throw new \InvalidArgumentException('Field dimensions must be an array');
        }
    }
}
