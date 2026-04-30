<?php

declare(strict_types=1);

namespace App\Service\Provider;

use App\Constants\Code;
use App\Contract\ModelProviderInterface;
use App\Exception\BusinessException;
use Generator;

/**
 * OpenAI 兼容接口的占位 Provider。
 *
 * Phase 2 预留契约，真实实现留待 Phase 3+；调用时抛 BusinessException 而非静默失败，
 * 使上层可以将其从 getAvailableProviders() 中排除并在 UI 屏蔽。
 */
class OpenAICompatibleProvider implements ModelProviderInterface
{
    public const NAME = 'openai';

    private string $apiKey;

    /** @var array<int, string> */
    private array $models;

    private string $defaultModel;

    public function __construct(array $config)
    {
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->models = (array) ($config['models'] ?? ['gpt-4o-mini']);
        $this->defaultModel = (string) ($config['default_model'] ?? 'gpt-4o-mini');
        // base_url / timeout 预留，真实实现时使用
        unset($config);
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
        throw new BusinessException(
            Code::AI_PROVIDER_ERROR,
            'OpenAICompatibleProvider is a placeholder; not implemented yet',
            501
        );
    }

    public function chatStream(string $prompt, array $messages = [], array $options = []): Generator
    {
        throw new BusinessException(
            Code::AI_PROVIDER_ERROR,
            'OpenAICompatibleProvider is a placeholder; not implemented yet',
            501
        );
        yield ''; // unreachable — satisfies Generator return type
    }

    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }
}
