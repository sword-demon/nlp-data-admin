<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Annotation\AgentLog;
use App\Constants\Code;
use App\Constants\ReflectionPrompts;
use App\Contract\AgentInterface;
use App\Exception\BusinessException;
use App\Service\Agent\Outcome\AgentOutcome;
use App\Service\Agent\Outcome\Payload\ReflectionPayload;
use App\Service\ModelProviderService;
use Generator;
use Hyperf\Contract\StdoutLoggerInterface;

/**
 * 评审 Agent：从 5 个维度评分内容，质量 < 7 分时返回 DEGRADED 和改进建议。
 *
 * Payload：ReflectionPayload。
 */
class ReflectionAgent extends AbstractAgent implements AgentInterface
{
    public function __construct(
        private readonly ModelProviderService $providers,
        private readonly StdoutLoggerInterface $logger
    ) {}

    public function getName(): string
    {
        return 'reflection';
    }

    #[AgentLog(name: 'reflection')]
    public function execute(array $context): AgentOutcome
    {
        $title = (string) ($context['title'] ?? '');
        $content = (string) ($context['content'] ?? '');

        if ($title === '' || $content === '') {
            throw new BusinessException(
                Code::WORKSHOP_AGENT_FAILED,
                'title and content are required',
                422
            );
        }

        $userPrompt = $this->render(ReflectionPrompts::REFLECTION_USER_TPL, [
            'title' => $title,
            'content' => $content,
        ]);

        try {
            $raw = $this->providers->driver()->chat(
                $userPrompt,
                [['role' => 'system', 'content' => ReflectionPrompts::REFLECTION_SYSTEM]],
                ['temperature' => 0.3] // 低温度确保稳定性
            );

            $this->logger->debug('[ReflectionAgent] raw response: ' . substr($raw, 0, 200) . '...');

            $decoded = $this->decodeJson($raw, $this->getName());

            // 提取评分
            $scores = $decoded['scores'] ?? [];
            if (! is_array($scores) || empty($scores)) {
                throw new BusinessException(
                    Code::WORKSHOP_PARSE_ERROR,
                    'missing scores in reflection response',
                    422
                );
            }

            // 规范化评分字段
            $normalizedScores = [
                'logic' => (int) ($scores['logic'] ?? 0),
                'fact' => (int) ($scores['fact'] ?? 0),
                'language' => (int) ($scores['language'] ?? 0),
                'title' => (int) ($scores['title'] ?? 0),
                'structure' => (int) ($scores['structure'] ?? 0),
            ];

            // 验证评分范围
            foreach ($normalizedScores as $dimension => $score) {
                if ($score < 1 || $score > 10) {
                    throw new BusinessException(
                        Code::WORKSHOP_PARSE_ERROR,
                        "invalid score for $dimension: $score",
                        422
                    );
                }
            }

            // 提取建议
            $suggestions = (array) ($decoded['suggestions'] ?? []);

            // 计算平均分
            $averageScore = array_sum($normalizedScores) / count($normalizedScores);

            $payload = new ReflectionPayload(
                scores: $normalizedScores,
                suggestions: array_map('strval', $suggestions),
                averageScore: $averageScore,
            );

            // 根据是否通过决定返回 OK 或 DEGRADED
            if ($payload->passed()) {
                return AgentOutcome::ok($payload);
            } else {
                return AgentOutcome::degraded(
                    $payload,
                    '内容在某些维度评分不足，建议重写',
                    'reflection_degraded'
                );
            }
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('[ReflectionAgent] error: ' . $e->getMessage());
            throw new BusinessException(
                Code::WORKSHOP_AGENT_FAILED,
                'reflection failed: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }

    public function executeStream(array $context): Generator
    {
        $outcome = $this->execute($context);
        /** @var ReflectionPayload $payload */
        $payload = $outcome->payload;
        yield json_encode($payload->toArray(), JSON_UNESCAPED_UNICODE);
        return $outcome;
    }
}
