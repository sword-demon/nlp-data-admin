<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Annotation\AgentLog;
use App\Contract\AgentInterface;
use App\Service\ImageStrategyFactory;
use Generator;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coroutine\Parallel;
use Throwable;

/**
 * 并行配图生成器（Phase 4 真实实现）。
 *
 * 输入：ImageAnalyzerAgent 的 analyses 数组
 * 处理：对每个分析项，通过 ImageStrategyFactory::fetchWithFallback 获取真实图片
 *       （策略失败自动降级到 fallback_chain，最终兜底到 emoji）
 *       所有项通过 Hyperf\Coroutine\Parallel 协程并发执行
 * 输出：{ images: [{placeholder_id, type, url, alt, attribution, keyword, source, is_placeholder}] }
 *
 * 注意：图片内容由策略自行处理（Pexels 直接用 src.large；Mermaid 走 mermaid.ink；
 *       NanoBanana 异步轮询；SVG/Iconify 可上传 OSS 也可直返 CDN）。
 *       本 Generator 不再关心底层细节，只做调度与聚合。
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
    public function execute(array $context): array
    {
        /** @var array<int, array<string, mixed>> $analyses */
        $analyses = (array) ($context['analyses'] ?? []);

        if (empty($analyses)) {
            return ['images' => []];
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
            // 理论上 fetchOne 内部已 catch，这里兜底
            $this->logger->error('[ParallelImageGenerator] parallel wait failed: ' . $e->getMessage());
            $results = [];
        }

        $images = [];
        foreach ($analyses as $idx => $analysis) {
            $key = (string) $idx;
            $item = $results[$key] ?? null;
            if (is_array($item) && ! empty($item['url'])) {
                $images[] = $item;
            } else {
                // 所有 fallback 都失败：返回一条占位记录，避免正文中残留 placeholder://
                $keywords = is_array($analysis['keywords'] ?? null) ? $analysis['keywords'] : [];
                $keyword = (string) ($keywords[0] ?? 'image');
                $images[] = [
                    'placeholder_id' => (string) ($analysis['placeholder_id'] ?? ''),
                    'type' => (string) ($analysis['suggested_type'] ?? 'pexels'),
                    'source' => 'placeholder',
                    'url' => 'https://via.placeholder.com/800x400?text=' . rawurlencode($keyword),
                    'original_url' => '',
                    'keyword' => $keyword,
                    'alt' => $keyword,
                    'attribution' => null,
                    'mime' => 'image/png',
                    'raw' => null,
                    'is_placeholder' => true,
                ];
            }
        }

        $this->logger->info('[ParallelImageGenerator] produced ' . count($images) . ' images');

        return ['images' => $images];
    }

    public function executeStream(array $context): Generator
    {
        $result = $this->execute($context);
        yield json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>|null
     */
    private function fetchOne(array $analysis): ?array
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
            return [
                'placeholder_id' => $placeholderId,
                'type' => $type,
                'source' => $result->source,
                'url' => $result->url,
                'original_url' => $result->originalUrl,
                'keyword' => $keyword,
                'alt' => $result->alt,
                'attribution' => $result->attribution,
                'mime' => $result->mime,
                'raw' => $result->raw,
                'is_placeholder' => false,
            ];
        } catch (Throwable $e) {
            $this->logger->error("[ParallelImageGenerator] all strategies failed for [{$placeholderId}:{$keyword}]: {$e->getMessage()}");
            return null;
        }
    }
}
