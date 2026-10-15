<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * TitlePoll
 *
 * 标题投票结果，包含多个候选标题和用户反馈
 *
 * @category Title
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/title.yaml
 */
class TitlePoll
{
    /**
     * 候选标题列表
     * @var TitleCandidate[]
     */
    public array $candidates;

    /**
     * 总投票数
     * @example 150
     * @var int
     */
    public int $total_votes;

    /**
     * 用户整体反馈
     * @example "\u5019\u9009\u6807\u9898\u90fd\u5f88\u4e0d\u9519\uff0c\u503e\u5411\u4e8e\u7b2c\u4e00\u4e2a"
     * @var string|null
     */
    public string|null $feedback = null;

    /**
     * Constructor
     *
     * @param array $candidates 候选标题列表
     * @param int $total_votes 总投票数
     * @param string|null $feedback 用户整体反馈
     */
    public function __construct(
        array $candidates,
        int $total_votes,
        ?string $feedback = null
    ) {
        $this->candidates = $candidates;
        $this->total_votes = $total_votes;
        $this->feedback = $feedback;
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'candidates' => $this->candidates,
            'total_votes' => $this->total_votes,
            'feedback' => $this->feedback,
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
            candidates: $data['candidates'],
            total_votes: $data['total_votes'],
            feedback: $data['feedback'] ?? null
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
        if (!isset($data['candidates'])) {
            throw new \InvalidArgumentException('Missing required field: candidates');
        }
        if (!isset($data['total_votes'])) {
            throw new \InvalidArgumentException('Missing required field: total_votes');
        }

        // 类型检查
        if (isset($data['candidates']) && !is_array($data['candidates'])) {
            throw new \InvalidArgumentException('Field candidates must be an array');
        }
        if (isset($data['total_votes']) && !is_int($data['total_votes'])) {
            throw new \InvalidArgumentException('Field total_votes must be an integer');
        }
        if (isset($data['total_votes']) && $data['total_votes'] < 0) {
            throw new \InvalidArgumentException('Field total_votes must be >= 0');
        }
        if (isset($data['feedback']) && !is_string($data['feedback']) && !is_null($data['feedback'])) {
            throw new \InvalidArgumentException('Field feedback must be a string');
        }
    }
}
