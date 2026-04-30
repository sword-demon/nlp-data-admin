<?php

declare(strict_types=1);

namespace App\Service\ImageStrategy;

use App\Constants\Code;
use App\Contract\ImageStrategyInterface;
use App\Dto\ImageResult;
use App\Exception\BusinessException;
use App\Service\OssUploader;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;

/**
 * Mermaid 流程图策略。
 *
 * 实现路径：根据关键词构造一个简单流程图（起点→关键词→终点），
 * 编码为 base64 后交给 mermaid.ink 公共渲染服务返回 PNG URL。
 *
 * 优势：零额外依赖；前端拿到的就是可直接 <img src=...> 的 URL。
 * 局限：mermaid.ink 免费、但有并发限制；不可控生成错误 → 降级由 Factory 处理。
 */
class MermaidStrategy extends AbstractImageStrategy implements ImageStrategyInterface
{
    private string $renderEndpoint;
    private string $theme;
    private bool $enabled;

    public function __construct(
        ConfigInterface $config,
        private readonly StdoutLoggerInterface $logger,
        private readonly OssUploader $uploader,
    ) {
        $c = (array) $config->get('image.strategies.mermaid', []);
        $this->enabled = (bool) ($c['enabled'] ?? true);
        $this->renderEndpoint = rtrim((string) ($c['render_endpoint'] ?? 'https://mermaid.ink/img'), '/');
        $this->theme = (string) ($c['theme'] ?? 'default');
    }

    public function getName(): string
    {
        return 'mermaid';
    }

    public function getLabel(): string
    {
        return 'Mermaid 流程图';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function supports(string $type): bool
    {
        return $type === 'mermaid';
    }

    public function fetch(string $keyword, array $options = []): ImageResult
    {
        if (! $this->isEnabled()) {
            throw new BusinessException(Code::IMAGE_STRATEGY_DISABLED, 'Mermaid disabled', 500);
        }

        $kw = $this->normalizeKeyword($keyword);
        if ($kw === '') {
            throw new BusinessException(Code::IMAGE_FETCH_FAILED, 'empty keyword', 422);
        }

        // 从 options.context 中提取相关关键词丰富流程图；没有则默认 3 节点
        $context = (string) ($options['context'] ?? '');
        $code = $this->buildMermaidCode($kw, $context);

        // base64url 编码（mermaid.ink 要求）
        $encoded = $this->base64UrlEncode($code);
        $renderUrl = $this->renderEndpoint . '/' . $encoded . '?type=png';

        $this->logger->debug("[MermaidStrategy] generated diagram for [{$kw}]");

        // 下载 PNG 内容并上传 OSS（OSS 未启用时自动降级返回原 URL）
        $finalUrl = $this->uploader->uploadFromUrl($renderUrl, 'png');

        return new ImageResult(
            source: 'mermaid',
            url: $finalUrl,
            originalUrl: $renderUrl,
            alt: "流程图：{$kw}",
            attribution: 'Rendered by mermaid.ink',
            mime: 'image/png',
            raw: $code,
        );
    }

    private function buildMermaidCode(string $keyword, string $context): string
    {
        // 极简流程图模板：输入 → 核心概念 → 输出
        // 如需更复杂结构可从 context 抽关键词，先做 MVP
        $core = str_replace(['"', "'", '[', ']', '(', ')', "\n", "\r"], ' ', $keyword);
        $core = mb_substr(trim($core), 0, 30);

        return "flowchart LR\n"
            . "    A[输入/现状] --> B{核心：{$core}}\n"
            . "    B --> C[分析/处理]\n"
            . "    C --> D[输出/结论]\n"
            . "    style B fill:#e1f5ff,stroke:#0288d1,stroke-width:2px\n";
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
