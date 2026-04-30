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
 * Pexels 图库策略。
 *
 * - 搜索：GET {base_url}/search?query=&per_page=&orientation=&locale=
 * - 认证：Authorization: {api_key}（非 Bearer）
 * - 归属：Pexels Guidelines 要求注明 photographer
 *
 * 失败时抛 BusinessException(IMAGE_FETCH_FAILED)，由 Factory 做 fallback。
 */
class PexelsStrategy extends AbstractImageStrategy implements ImageStrategyInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $locale;
    private string $orientation;
    private int $perPage;
    private bool $enabled;

    public function __construct(
        ConfigInterface $config,
        private readonly OssUploader $uploader,
        private readonly StdoutLoggerInterface $logger
    ) {
        $c = (array) $config->get('image.strategies.pexels', []);
        $this->enabled = (bool) ($c['enabled'] ?? true);
        $this->apiKey = (string) ($c['api_key'] ?? '');
        $this->baseUrl = rtrim((string) ($c['base_url'] ?? 'https://api.pexels.com/v1'), '/');
        $this->locale = (string) ($c['locale'] ?? 'zh-CN');
        $this->orientation = (string) ($c['orientation'] ?? 'landscape');
        $this->perPage = (int) ($c['per_page'] ?? 3);
    }

    public function getName(): string
    {
        return 'pexels';
    }

    public function getLabel(): string
    {
        return 'Pexels 图库';
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->apiKey !== '';
    }

    public function supports(string $type): bool
    {
        return $type === 'pexels';
    }

    public function fetch(string $keyword, array $options = []): ImageResult
    {
        if (! $this->isEnabled()) {
            throw new BusinessException(Code::IMAGE_STRATEGY_DISABLED, 'Pexels not configured', 500);
        }

        $q = $this->normalizeKeyword($keyword);
        if ($q === '') {
            throw new BusinessException(Code::IMAGE_FETCH_FAILED, 'empty keyword', 422);
        }

        try {
            $res = $this->http()->get($this->baseUrl . '/search', [
                'headers' => [
                    'Authorization' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'query' => $q,
                    'per_page' => $this->perPage,
                    'orientation' => $this->orientation,
                    'locale' => $this->locale,
                ],
            ]);

            $data = json_decode((string) $res->getBody(), true);
            $photos = $data['photos'] ?? [];

            if (empty($photos)) {
                throw new BusinessException(Code::IMAGE_FETCH_FAILED, "no pexels results for [{$q}]", 404);
            }

            $photo = $photos[0];
            $originalUrl = (string) ($photo['src']['large'] ?? $photo['src']['original'] ?? '');
            $photographer = (string) ($photo['photographer'] ?? 'Unknown');
            $photoUrl = (string) ($photo['url'] ?? '');

            if ($originalUrl === '') {
                throw new BusinessException(Code::IMAGE_FETCH_FAILED, 'no src url in pexels response', 502);
            }

            $finalUrl = $this->uploader->uploadFromUrl($originalUrl, 'jpg');

            return new ImageResult(
                source: 'pexels',
                url: $finalUrl,
                originalUrl: $originalUrl,
                alt: $q,
                attribution: "Photo by {$photographer} on Pexels ({$photoUrl})",
                mime: 'image/jpeg',
            );
        } catch (BusinessException $e) {
            throw $e;
        } catch (GuzzleException $e) {
            $this->logger->warning("[PexelsStrategy] guzzle error: {$e->getMessage()}");
            throw new BusinessException(Code::IMAGE_FETCH_FAILED, 'pexels request failed: ' . $e->getMessage(), 502, $e);
        } catch (Throwable $e) {
            throw new BusinessException(Code::IMAGE_FETCH_FAILED, 'pexels unexpected: ' . $e->getMessage(), 502, $e);
        }
    }
}
