<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Annotation\AgentLog;
use App\Contract\AgentInterface;
use App\Service\Agent\Outcome\AgentOutcome;
use App\Service\Agent\Outcome\DegradationReason;
use App\Service\Agent\Outcome\Payload\GeneratedImages;
use App\Service\Agent\Outcome\Payload\ImageSlot;
use App\Service\ImageStrategyFactory;
use Generator;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coroutine\Parallel;
use Throwable;

/**
 * 并行配图生成器（Phase 4 真实实现）。
 *
 * 输入：ImageAnalyzerAgent 的 analyses 数组
 * 输出 Payload：GeneratedImages
 * 降级（ADR-0003 显式化）：任一 ImageSlot.isPlaceholder=true → DEGRADED + STRATEGY_CHAIN_EXHAUSTED
 */
class ParallelImageGenerator extends AbstractAgent implements AgentInterface
{
    public function __construct(
        private readonly ImageStrategyFactory $factory,
        private readonly StdoutLoggerInterface $logger
    ) {}

    public function getName(): string
    {
        return 'parallel_image_generator';
    }

    #[AgentLog(name: 'parallel_image_generator', logOutput: false)]
    public function execute(array $context): AgentOutcome
    {
        /** @var array<int, array<string, mixed>> $analyses */
        $analyses = (array) ($context['analyses'] ?? []);

        if (empty($analyses)) {
            return AgentOutcome::ok(new GeneratedImages(images: []));
        }

        // 协程并发调度所有策略
        $parallel = new Parallel();
        foreach ($analyses as $idx => $analysis) {
            $parallel->add(function () use ($analysis) {
                return $this->fetchOne($analysis);
            }, (string) $idx);
        }

        try {
            $results = $parallel->wait();
        } catch (Throwable $e) {
            $this->logger->error('[ParallelImageGenerator] parallel wait failed: ' . $e->getMessage());
            $results = [];
        }

        $slots = [];
        $placeholderCount = 0;
        foreach ($analyses as $idx => $analysis) {
            $key = (string) $idx;
            $item = $results[$key] ?? null;
            if ($item instanceof ImageSlot) {
                $slots[] = $item;
                if ($item->isPlaceholder) {
                    $placeholderCount++;
                }
            } else {
                // 所有 fallback 都失败：返回一条占位记录，避免正文中残留 placeholder://
                $keywords = is_array($analysis['keywords'] ?? null) ? $analysis['keywords'] : [];
                $keyword = (string) ($keywords[0] ?? 'image');
                $slots[] = new ImageSlot(
                    placeholderId: (string) ($analysis['placeholder_id'] ?? ''),
                    type: (string) ($analysis['suggested_type'] ?? 'pexels'),
                    source: 'placeholder',
                    url: 'https://via.placeholder.com/800x400?text=' . rawurlencode($keyword),
                    originalUrl: '',
                    keyword: $keyword,
                    alt: $keyword,
                    attribution: null,
                    mime: 'image/png',
                    raw: null,
                    isPlaceholder: true,
                );
                $placeholderCount++;
            }
        }

        $this->logger->info('[ParallelImageGenerator] produced ' . count($slots) . ' images');

        $payload = new GeneratedImages(images: $slots);

        if ($placeholderCount > 0) {
            return AgentOutcome::degraded(
                $payload,
                DegradationReason::STRATEGY_CHAIN_EXHAUSTED,
                "{$placeholderCount}/" . count($slots) . ' images fell through to via.placeholder.com',
            );
        }

        return AgentOutcome::ok($payload);
    }

    public function executeStream(array $context): Generator
    {
        $outcome = $this->execute($context);
        /** @var GeneratedImages $payload */
        $payload = $outcome->payload;
        yield json_encode(['images' => $payload->toArray()], JSON_UNESCAPED_UNICODE);
        return $outcome;
    }

    /**
     * @param array<string, mixed> $analysis
     */
    private function fetchOne(array $analysis): ?ImageSlot
    {
        $placeholderId = (string) ($analysis['placeholder_id'] ?? '');
        if ($placeholderId === '') {
            return null;
        }
        $type = (string) ($analysis['suggested_type'] ?? 'pexels');
        $keywords = is_array($analysis['keywords'] ?? null) ? $analysis['keywords'] : [];
        $keyword = (string) ($keywords[0] ?? 'image');
        $options = ['context' => (string) ($analysis['context'] ?? '')];

        try {
            $result = $this->factory->fetchWithFallback($type, $keyword, $options);
            return new ImageSlot(
                placeholderId: $placeholderId,
                type: $type,
                source: $result->source,
                url: $result->url,
                originalUrl: $result->originalUrl,
                keyword: $keyword,
                alt: $result->alt,
                attribution: $result->attribution,
                mime: $result->mime,
                raw: $result->raw,
                isPlaceholder: false,
            );
        } catch (Throwable $e) {
            $this->logger->error("[ParallelImageGenerator] all strategies failed for [{$placeholderId}:{$keyword}]: {$e->getMessage()}");
            return null;
        }
    }
}
