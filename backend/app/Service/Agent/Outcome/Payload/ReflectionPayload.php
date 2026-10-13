<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

/**
 * 评审负荷 (Reflection Payload)
 *
 * 包含 5 维度评分和改进建议
 */
final class ReflectionPayload
{
    /**
     * @param array<string, int> $scores 5 维度评分: logic/fact/language/title/structure
     * @param array<int, string> $suggestions 改进建议列表
     * @param float $averageScore 平均分数 (0-100)
     */
    public function __construct(
        public readonly array $scores,
        public readonly array $suggestions,
        public readonly float $averageScore,
    ) {}

    /**
     * 是否通过评审 (所有维度 >= 7 分)
     */
    public function passed(): bool
    {
        foreach ($this->scores as $score) {
            if ($score < 7) {
                return false;
            }
        }
        return true;
    }

    /**
     * 获取未通过的维度列表
     *
     * @return array<string, int>
     */
    public function failedDimensions(): array
    {
        $failed = [];
        foreach ($this->scores as $dimension => $score) {
            if ($score < 7) {
                $failed[$dimension] = $score;
            }
        }
        return $failed;
    }

    /**
     * 转换为数组
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scores' => $this->scores,
            'suggestions' => $this->suggestions,
            'average_score' => $this->averageScore,
            'passed' => $this->passed(),
        ];
    }

    /**
     * 从数组创建实例
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scores: (array) ($data['scores'] ?? []),
            suggestions: (array) ($data['suggestions'] ?? []),
            averageScore: (float) ($data['average_score'] ?? 0.0),
        );
    }
}
