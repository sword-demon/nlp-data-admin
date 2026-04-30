<?php

declare(strict_types=1);

namespace App\Service\Provider;

use App\Constants\Code;
use App\Contract\ModelProviderInterface;
use App\Exception\BusinessException;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Context\ApplicationContext;
use Throwable;

/**
 * DashScope (阿里通义千问) Provider。
 *
 * 默认调用 /services/aigc/text-generation/generation 文本生成接口；
 * 流式通过 header X-DashScope-SSE: enable + parameters.incremental_output: true 获取增量文本。
 * 响应流每行以 "data:" 前缀包裹 JSON，结束信号为 "data: [DONE]"。
 */
class DashScopeProvider implements ModelProviderInterface
{
    public const NAME = 'dashscope';

    private string $apiKey;

    private string $baseUrl;

    private string $defaultModel;

    /** @var array<int, string> */
    private array $models;

    private int $timeout;

    public function __construct(array $config)
    {
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://dashscope.aliyuncs.com/api/v1'), '/');
        $this->defaultModel = (string) ($config['default_model'] ?? 'qwen-plus');
        $this->models = (array) ($config['models'] ?? ['qwen-plus']);
        $this->timeout = (int) ($config['timeout'] ?? 60);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getModels(): array
    {
        return $this->models;
    }

    public function chat(string $prompt, array $messages = [], array $options = []): string
    {
        $this->assertApiKey();

        try {
            $client = $this->makeClient(false);
            $res = $client->post($this->endpoint(), [
                'json' => $this->buildPayload($prompt, $messages, $options, false),
                'headers' => $this->buildHeaders(false),
            ]);

            $data = json_decode((string) $res->getBody(), true);
            $text = $data['output']['choices'][0]['message']['content'] ?? null;

            if (! is_string($text)) {
                throw new BusinessException(
                    Code::AI_PROVIDER_ERROR,
                    'Unexpected DashScope response: ' . json_encode($data, JSON_UNESCAPED_UNICODE),
                    502
                );
            }

            return $text;
        } catch (GuzzleException $e) {
            throw new BusinessException(Code::AI_PROVIDER_ERROR, 'DashScope request failed: ' . $e->getMessage(), 502, $e);
        }
    }

    public function chatStream(string $prompt, array $messages = [], array $options = []): Generator
    {
        $this->assertApiKey();

        try {
            $client = $this->makeClient(true);
            $response = $client->post($this->endpoint(), [
                'json' => $this->buildPayload($prompt, $messages, $options, true),
                'headers' => $this->buildHeaders(true),
                'stream' => true,
            ]);

            $body = $response->getBody();
            $buffer = '';

            while (! $body->eof()) {
                $chunk = $body->read(1024);
                if ($chunk === '') {
                    continue;
                }
                $buffer .= $chunk;

                // 按行拆分；DashScope SSE 每条消息以 \n\n 分隔
                while (($lfPos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $lfPos);
                    $buffer = substr($buffer, $lfPos + 1);
                    $line = rtrim($line, "\r");

                    if ($line === '' || ! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $payload = trim(substr($line, 5));
                    if ($payload === '' || $payload === '[DONE]') {
                        return;
                    }

                    $data = json_decode($payload, true);
                    if (! is_array($data)) {
                        continue;
                    }

                    $text = $data['output']['choices'][0]['message']['content'] ?? ($data['output']['text'] ?? null);
                    if (is_string($text) && $text !== '') {
                        yield $text;
                    }
                }
            }
        } catch (GuzzleException $e) {
            throw new BusinessException(Code::AI_STREAM_ERROR, 'DashScope stream failed: ' . $e->getMessage(), 502, $e);
        } catch (Throwable $e) {
            if ($e instanceof BusinessException) {
                throw $e;
            }
            throw new BusinessException(Code::AI_STREAM_ERROR, 'DashScope stream error: ' . $e->getMessage(), 502, $e);
        }
    }

    // ---------- internals ----------

    private function endpoint(): string
    {
        return $this->baseUrl . '/services/aigc/text-generation/generation';
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                              $options
     *
     * @return array<string, mixed>
     */
    private function buildPayload(string $prompt, array $messages, array $options, bool $stream): array
    {
        $allMessages = $messages;
        if ($prompt !== '') {
            $allMessages[] = ['role' => 'user', 'content' => $prompt];
        }

        return [
            'model' => (string) ($options['model'] ?? $this->defaultModel),
            'input' => [
                'messages' => $allMessages,
            ],
            'parameters' => array_filter([
                'result_format' => 'message',
                'incremental_output' => $stream,
                'temperature' => $options['temperature'] ?? null,
                'top_p' => $options['top_p'] ?? null,
                'max_tokens' => $options['max_tokens'] ?? null,
            ], static fn($v) => $v !== null),
        ];
    }

    /** @return array<string, string> */
    private function buildHeaders(bool $stream): array
    {
        $h = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
        if ($stream) {
            $h['X-DashScope-SSE'] = 'enable';
            $h['Accept'] = 'text/event-stream';
        }

        return $h;
    }

    private function makeClient(bool $stream): Client
    {
        $options = [
            'timeout' => $stream ? 0 : $this->timeout,
            'connect_timeout' => 10,
            'http_errors' => true,
        ];

        // 优先使用 Hyperf Guzzle 工厂（协程友好）；非 Hyperf 上下文回落到原生 Guzzle
        if (ApplicationContext::hasContainer()) {
            /** @var ClientFactory $factory */
            $factory = ApplicationContext::getContainer()->get(ClientFactory::class);

            return $factory->create($options);
        }

        return new Client($options);
    }

    private function assertApiKey(): void
    {
        if ($this->apiKey === '') {
            throw new BusinessException(
                Code::AI_PROVIDER_MISSING,
                'DASHSCOPE_API_KEY is not configured',
                500
            );
        }
    }
}
