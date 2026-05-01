<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Annotation\AgentLog;
use App\Constants\AgentPrompts;
use App\Contract\AgentInterface;
use App\Service\Agent\Outcome\AgentOutcome;
use App\Service\Agent\Outcome\DegradationReason;
use App\Service\Agent\Outcome\Payload\ImageAnalyses;
use App\Service\Agent\Outcome\Payload\ImageAnalysis;
use App\Service\ModelProviderService;
use Generator;
use Hyperf\Contract\StdoutLoggerInterface;
use Throwable;

/**
 * 配图分析 Agent：分析正文中每个占位符的上下文并给出配图建议。
 *
 * Payload：ImageAnalyses。
 * 降级路径（ADR-0003 显式化）：
 *   - LLM JSON 解析失败 → DEGRADED + PARSE_FAILED（仍为每个占位符填默认 pexels）
 */
class ImageAnalyzerAgent extends AbstractAgent implements AgentInterface
{
    private const ALLOWED_TYPES = ['pexels', 'mermaid', 'iconify', 'emoji', 'svg', 'nanobanana'];

    public function __construct(
        private readonly ModelProviderService $providers,
        private readonly StdoutLoggerInterface $logger
    ) {}

    public function getName(): string
    {
        return 'image_analyzer';
    }

    #[AgentLog(name: 'image_analyzer')]
    public function execute(array $context): AgentOutcome
    {
        $content = (string) ($context['content'] ?? '');
        /** @var array<int, array{id: string, index: int, keyword: string}> $placeholders */
        $placeholders = (array) ($context['placeholders'] ?? []);

        if ($content === '' || empty($placeholders)) {
            // 没有占位符则直接返回空数组（非错误路径，不算降级）
            return AgentOutcome::ok(new ImageAnalyses(analyses: []));
        }

        $userPrompt = $this->render(AgentPrompts::IMAGE_ANALYZER_USER_TPL, [
            'content' => $content,
        ]);

        $preamble = $this->buildResearchPreamble($context['research_data'] ?? null);
        $userPrompt = $preamble . $userPrompt;

        $raw = $this->providers->driver()->chat(
            $userPrompt,
            [
                ['role' => 'system', 'content' => AgentPrompts::IMAGE_ANALYZER_SYSTEM],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            ['temperature' => 0.4]
        );

        $this->logger->debug("[ImageAnalyzerAgent] raw output: {$raw}");

        $parseFailed = false;
        $parseDetail = null;
        try {
            $decoded = $this->decodeJson($raw, $this->getName());
        } catch (Throwable $e) {
            // 降级：为每个占位符生成默认 pexels 策略，不阻塞流程
            $decoded = [];
            $parseFailed = true;
            $parseDetail = substr($e->getMessage(), 0, 200);
        }

        $analysesById = [];
        foreach ($decoded as $item) {
            if (! is_array($item) || empty($item['placeholder_id'])) {
                continue;
            }
            $type = (string) ($item['suggested_type'] ?? 'pexels');
            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                $type = 'pexels';
            }
            $keywords = $item['keywords'] ?? [];
            $pid = (string) $item['placeholder_id'];
            $analysesById[$pid] = new ImageAnalysis(
                placeholderId: $pid,
                context: (string) ($item['context'] ?? ''),
                keywords: is_array($keywords) ? array_values(array_map('strval', $keywords)) : [],
                suggestedType: $type,
                reasoning: (string) ($item['reasoning'] ?? ''),
            );
        }

        $missingFilled = 0;
        $final = [];
        foreach ($placeholders as $p) {
            $id = (string) $p['id'];
            if (isset($analysesById[$id])) {
                $final[] = $analysesById[$id];
                continue;
            }
            // 降级填充：微观可见（ADR-0003 目标）
            $missingFilled++;
            $final[] = new ImageAnalysis(
                placeholderId: $id,
                context: '',
                keywords: [(string) $p['keyword']],
                suggestedType: 'pexels',
                reasoning: 'fallback default',
            );
        }

        $payload = new ImageAnalyses(analyses: $final);

        if ($parseFailed) {
            return AgentOutcome::degraded(
                $payload,
                DegradationReason::PARSE_FAILED,
                $parseDetail !== null
                    ? "llm json parse failed; filled {$missingFilled} placeholders by default. err={$parseDetail}"
                    : "llm json parse failed; filled {$missingFilled} placeholders by default.",
            );
        }

        return AgentOutcome::ok($payload);
    }

    public function executeStream(array $context): Generator
    {
        $outcome = $this->execute($context);
        /** @var ImageAnalyses $payload */
        $payload = $outcome->payload;
        yield json_encode(['analyses' => $payload->toArray()], JSON_UNESCAPED_UNICODE);
        return $outcome;
    }
}
