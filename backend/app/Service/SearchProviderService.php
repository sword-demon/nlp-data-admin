<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\Code;
use App\Contract\SearchProviderInterface;
use App\Exception\BusinessException;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

/**
 * 搜索 Provider 工厂：依据 config/autoload/search.php 按名取 Provider 实例。
 *
 * 与 ModelProviderService 同结构，让"搜索"成为与"模型"并列的能力维度。
 */
class SearchProviderService
{
    /** @var array<string, SearchProviderInterface> */
    private array $instances = [];

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ContainerInterface $container,
    ) {}

    public function driver(?string $name = null): SearchProviderInterface
    {
        $name ??= (string) $this->config->get('search.default', 'exa');

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $providers = (array) $this->config->get('search.providers', []);
        if (! isset($providers[$name]) || ! is_array($providers[$name])) {
            throw new BusinessException(
                Code::AI_PROVIDER_MISSING,
                "Search provider [{$name}] not configured",
                500
            );
        }

        $cfg = $providers[$name];
        $driver = (string) ($cfg['driver'] ?? '');
        if ($driver === '' || ! class_exists($driver)) {
            throw new BusinessException(
                Code::AI_PROVIDER_MISSING,
                "Search provider driver [{$driver}] not found",
                500
            );
        }

        /** @var SearchProviderInterface $instance */
        $instance = new $driver($cfg);

        return $this->instances[$name] = $instance;
    }

    /**
     * 列出已开启 (enabled=true) 且配置了 api_key 的 Provider。
     *
     * @return array<int, string>
     */
    public function getAvailableProviders(): array
    {
        $providers = (array) $this->config->get('search.providers', []);
        $available = [];
        foreach ($providers as $name => $cfg) {
            if (! is_array($cfg)) {
                continue;
            }
            if (! empty($cfg['enabled']) && ! empty($cfg['api_key'])) {
                $available[] = (string) $name;
            }
        }

        return $available;
    }
}
