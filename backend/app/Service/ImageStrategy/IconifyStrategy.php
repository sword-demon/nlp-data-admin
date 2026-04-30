<?php

declare(strict_types=1);

namespace App\Service\ImageStrategy;

use App\Constants\Code;
use App\Contract\ImageStrategyInterface;
use App\Dto\ImageResult;
use App\Exception\BusinessException;
use App\Service\OssUploader;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Throwable;

/**
 * Iconify 图标策略。
 *
 * - 搜索 API: GET https://api.iconify.design/search?query=&limit=
 *   返回: {icons: ["mdi:robot", "mdi:cog", ...]}
 * - 获取 SVG: GET https://api.iconify.design/{prefix}/{name}.svg
 *
 * 无需 API Key；SVG 可直接用 CDN URL 或上传 OSS 备份。
 */
class IconifyStrategy extends AbstractImageStrategy implements ImageStrategyInterface
{
    private string $searchEndpoint;
    private string $fetchEndpoint;
    private int $limit;
    private bool $enabled;

    public function __construct(
        ConfigInterface $config,
        private readonly OssUploader $uploader,
        private readonly StdoutLoggerInterface $logger
    ) {
        $c = (array) $config->get('image.strategies.iconify', []);
        $this->enabled = (bool) ($c['enabled'] ?? true);
        $this->searchEndpoint = (string) ($c['search_endpoint'] ?? 'https://api.iconify.design/search');
        $this->fetchEndpoint = rtrim((string) ($c['fetch_endpoint'] ?? 'https://api.iconify.design'), '/');
        $this->limit = (int) ($c['limit'] ?? 5);
    }

    public function getName(): string
    {
        return 'iconify';
    }

    public function getLabel(): string
    {
        return 'Iconify 图标';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function supports(string $type): bool
    {
        return $type === 'iconify';
    }

    public function fetch(string $keyword, array $options = []): ImageResult
    {
        if (! $this->isEnabled()) {
            throw new BusinessException(Code::IMAGE_STRATEGY_DISABLED, 'Iconify disabled', 500);
        }

        $q = $this->normalizeKeyword($keyword);
        if ($q === '') {
            throw new BusinessException(Code::IMAGE_FETCH_FAILED, 'empty keyword', 422);
        }

        try {
            // 第一步：搜索图标
            $client = $this->http();
            $res = $client->get($this->searchEndpoint, [
                'query' => ['query' => $q, 'limit' => $this->limit],
            ]);
            $data = json_decode((string) $res->getBody(), true);
            $icons = $data['icons'] ?? [];

            if (empty($icons)) {
                // 降级到默认图标 mdi:image
                $iconId = 'mdi:image';
                $this->logger->debug("[IconifyStrategy] no result for [{$q}], fallback to {$iconId}");
            } else {
                $iconId = (string) $icons[0];
            }

            $svgUrl = $this->fetchEndpoint . '/' . str_replace(':', '/', $iconId) . '.svg';

            // 下载 SVG 并上传 OSS（如启用）；未启用时直接返回 CDN URL
            if ($this->uploader->isEnabled()) {
                $finalUrl = $this->uploader->uploadFromUrl($svgUrl, 'svg');
            } else {
                $finalUrl = $svgUrl;
            }

            return new ImageResult(
                source: 'iconify',
                url: $finalUrl,
                originalUrl: $svgUrl,
                alt: "图标：{$q}",
                attribution: "Iconify [{$iconId}]",
                mime: 'image/svg+xml',
                raw: $iconId,
            );
        } catch (BusinessException $e) {
            throw $e;
        } catch (GuzzleException $e) {
            throw new BusinessException(Code::IMAGE_FETCH_FAILED, 'iconify request failed: ' . $e->getMessage(), 502, $e);
        } catch (Throwable $e) {
            throw new BusinessException(Code::IMAGE_FETCH_FAILED, 'iconify unexpected: ' . $e->getMessage(), 502, $e);
        }
    }
}
