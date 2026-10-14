<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome\Payload;

use OpenApi\Attributes as OA;

/**
 * 评审负荷 (Reflection Payload)
 *
 * 包含 5 维度评分和改进建议
 */
#[OA\Schema(
    schema: 'ReflectionPayload',
    description: '内容评审结果，包含 5 维度评分和改进建议',
    type: 'object',
    required: ['scores', 'suggestions', 'average_score']
)]
final class ReflectionPayload
{
    /**
     * @param array<string, int> $scores 5 维度评分: logic/fact/language/title/structure
     * @param array<int, string> $suggestions 改进建议列表
     * @param float $averageScore 平均分数 (0-100)
     */
    public function __construct(
        #[OA\Property(
            property: 'scores',
            description: '5 维度评分：logic(逻辑连贯性)/fact(事实准确性)/language(语言流畅度)/title(标题吸引力)/structure(结构完整性)',
            type: 'object',
            example: ['logic' => 8, 'fact' => 9, 'language' => 7, 'title' => 8, 'structure' => 9]
        )]
        public readonly array $scores,

        #[OA\Property(
            property: 'suggestions',
            description: '改进建议列表',
            type: 'array',
            items: new OA\Items(type: 'string', example: '段落间增加过渡句')
        )]
        public readonly array $suggestions,

        #[OA\Property(property: 'average_score', description: '平均分数（1-10）', type: 'number', format: 'float', minimum: 1, maximum: 10, example: 8.2)]
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
