<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * TitleCandidate
 *
 * 单个标题候选项，包含标题文本、分析和评分
 *
 * @category Title
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/title.yaml
 */
class TitleCandidate
{
    /**
     * 候选标题文本
     * @example "AI \u6280\u672f\u5982\u4f55\u6539\u53d8\u4e16\u754c"
     * @var string
     */
    public string $title;

    /**
     * 标题分析说明
     * @example "\u8be5\u6807\u9898\u7b80\u6d01\u660e\u4e86\uff0c\u7a81\u51fa\u4e86 AI \u7684\u5f71\u54cd\u529b"
     * @var string
     */
    public string $analysis;

    /**
     * 标题评分 1-10
     * @example 8
     * @var int
     */
    public int $score;

    /**
     * Constructor
     *
     * @param string $title 候选标题文本
     * @param string $analysis 标题分析说明
     * @param int $score 标题评分 1-10
     */
    public function __construct(
        string $title,
        string $analysis,
        int $score,
    ) {
        $this->title = $title;
        $this->analysis = $analysis;
        $this->score = $score;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'analysis' => $this->analysis,
            'score' => $this->score,
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
            title: $data['title'],
            analysis: $data['analysis'],
            score: $data['score'],
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
        if (!isset($data['title'])) {
            throw new \InvalidArgumentException('Missing required field: title');
        }
        if (!isset($data['analysis'])) {
            throw new \InvalidArgumentException('Missing required field: analysis');
        }
        if (!isset($data['score'])) {
            throw new \InvalidArgumentException('Missing required field: score');
        }

        // 类型检查
        if (isset($data['title']) && !is_string($data['title'])) {
            throw new \InvalidArgumentException('Field title must be a string');
        }
        if (isset($data['analysis']) && !is_string($data['analysis'])) {
            throw new \InvalidArgumentException('Field analysis must be a string');
        }
        if (isset($data['score']) && !is_int($data['score'])) {
            throw new \InvalidArgumentException('Field score must be an integer');
        }
        if (isset($data['score']) && $data['score'] < 1) {
            throw new \InvalidArgumentException('Field score must be >= 1');
        }
        if (isset($data['score']) && $data['score'] > 10) {
            throw new \InvalidArgumentException('Field score must be <= 10');
        }
    }
}
