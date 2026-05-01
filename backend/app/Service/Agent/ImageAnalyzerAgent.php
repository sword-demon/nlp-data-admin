<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Annotation\AgentLog;
use App\Constants\AgentPrompts;
use App\Contract\AgentInterface;
use App\Service\ModelProviderService;
use Generator;
use Hyperf\Contract\StdoutLoggerInterface;

/**
 * 配图分析 Agent：分析正文中每个占位符的上下文并给出配图建议。
 *
 * 输出格式：
 *   {analyses: [{placeholder_id, context, keywords, suggested_type, reasoning}]}
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
    public function execute(array $context): array
    {
        $content = (string) ($context['content'] ?? '');
        /** @var array<int, array{id: string, index: int, keyword: string}> $placeholders */
        $placeholders = (array) ($context['placeholders'] ?? []);

        if ($content === '' || empty($placeholders)) {
            // 没有占位符则直接返回空数组（非错误路径）
            return ['analyses' => []];
        }

        $userPrompt = $this->render(AgentPrompts::IMAGE_ANALYZER_USER_TPL, [
            'content' => $content,
        ]);

        // Phase 3.5：research preamble 拼接，让配图建议在主题上不偏离
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

        try {
            $decoded = $this->decodeJson($raw, $this->getName());
        } catch (\Throwable) {
            // 降级：为每个占位符生成默认 pexels 策略，不阻塞流程
            $decoded = [];
        }

        $analyses = [];
        foreach ($decoded as $item) {
            if (! is_array($item) || empty($item['placeholder_id'])) {
                continue;
            }
            $type = (string) ($item['suggested_type'] ?? 'pexels');
            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                $type = 'pexels';
            }
            $keywords = $item['keywords'] ?? [];
            $analyses[(string) $item['placeholder_id']] = [
                'placeholder_id' => (string) $item['placeholder_id'],
                'context' => (string) ($item['context'] ?? ''),
                'keywords' => is_array($keywords) ? array_values(array_map('strval', $keywords)) : [],
                'suggested_type' => $type,
                'reasoning' => (string) ($item['reasoning'] ?? ''),
            ];
        }

        // 为所有占位符确保有一条记录（降级填充）
        $final = [];
        foreach ($placeholders as $p) {
            $id = (string) $p['id'];
            $final[] = $analyses[$id] ?? [
                'placeholder_id' => $id,
                'context' => '',
                'keywords' => [(string) $p['keyword']],
                'suggested_type' => 'pexels',
                'reasoning' => 'fallback default',
            ];
        }

        return ['analyses' => $final];
    }

    public function executeStream(array $context): Generator
    {
        $result = $this->execute($context);
        yield json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
