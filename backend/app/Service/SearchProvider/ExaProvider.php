<?php

declare(strict_types=1);

namespace App\Service\SearchProvider;

use App\Constants\Code;
use App\Contract\SearchProviderInterface;
use App\Exception\BusinessException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Context\ApplicationContext;
use Hyperf\Guzzle\ClientFactory;

/**
 * Exa Search Provider（https://exa.ai/docs/reference/search-api-guide）。
 *
 * 调用约定：
 *   POST {base_url}/search
 *   Header  x-api-key: <KEY>
 *   Body    { query, type, useAutoprompt, numResults, startPublishedDate, contents: { highlights: { numSentences, highlightsPerUrl } } }
 *
 * 响应归一化：把 highlights[] 用换行拼接为单一 snippet 字符串，避免下游处理两种结构。
 */
class ExaProvider implements SearchProviderInterface
{
    public const NAME = 'exa';

    private string $apiKey;

    private string $baseUrl;

    private int $numResults;

    private string $startPublishedDate;

    private int $timeout;

    public function __construct(array $config)
    {
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.exa.ai'), '/');
        $this->numResults = (int) ($config['num_results'] ?? 3);
        $this->startPublishedDate = (string) ($config['start_published_date'] ?? '');
        $this->timeout = (int) ($config['timeout'] ?? 15);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function search(string $query, array $options = []): array
    {
        $this->assertApiKey();

        $payload = array_filter([
            'query' => $query,
            'type' => (string) ($options['type'] ?? 'auto'),
            'useAutoprompt' => (bool) ($options['useAutoprompt'] ?? true),
            'numResults' => (int) ($options['numResults'] ?? $this->numResults),
            'startPublishedDate' => $this->startPublishedDate !== '' ? $this->startPublishedDate : null,
            'contents' => [
                'highlights' => [
                    'numSentences' => (int) ($options['highlightsNumSentences'] ?? 3),
                    'highlightsPerUrl' => (int) ($options['highlightsPerUrl'] ?? 3),
                ],
            ],
        ], static fn($v) => $v !== null);

        try {
            $client = $this->makeClient();
            $res = $client->post($this->baseUrl . '/search', [
                'json' => $payload,
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);
            $body = (string) $res->getBody();
            $data = json_decode($body, true);
            if (! is_array($data)) {
                throw new BusinessException(
                    Code::AI_PROVIDER_ERROR,
                    'Exa returned non-JSON: ' . substr($body, 0, 200),
                    502
                );
            }
        } catch (GuzzleException $e) {
            throw new BusinessException(
                Code::AI_PROVIDER_ERROR,
                'Exa request failed: ' . $e->getMessage(),
                502,
                $e
            );
        }

        $rawResults = (array) ($data['results'] ?? []);
        $results = [];
        foreach ($rawResults as $r) {
            if (! is_array($r)) {
                continue;
            }
            $highlights = (array) ($r['highlights'] ?? []);
            $snippet = implode(
                "\n",
                array_values(array_filter(array_map(
                    static fn($h) => trim((string) $h),
                    $highlights
                )))
            );

            $results[] = [
                'title' => (string) ($r['title'] ?? ''),
                'url' => (string) ($r['url'] ?? ''),
                'snippet' => $snippet,
                'published_date' => (string) ($r['publishedDate'] ?? ''),
                'score' => isset($r['score']) ? (float) $r['score'] : null,
            ];
        }

        return [
            'results' => $results,
            'meta' => [
                'autoprompt' => (string) ($data['autopromptString'] ?? ''),
                'raw_count' => count($rawResults),
            ],
        ];
    }

    private function assertApiKey(): void
    {
        if ($this->apiKey === '') {
            throw new BusinessException(
                Code::AI_PROVIDER_MISSING,
                'EXA_API_KEY is not configured',
                500
            );
        }
    }

    private function makeClient(): Client
    {
        $options = [
            'timeout' => $this->timeout,
            'connect_timeout' => 10,
            'http_errors' => true,
        ];

        if (ApplicationContext::hasContainer()) {
            /** @var ClientFactory $factory */
            $factory = ApplicationContext::getContainer()->get(ClientFactory::class);

            return $factory->create($options);
        }

        return new Client($options);
    }
}
