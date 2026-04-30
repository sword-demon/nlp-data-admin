<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\Code;
use App\Contract\ModelProviderInterface;
use App\Exception\BusinessException;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

/**
 * 模型 Provider 工厂与调度：依据 config/autoload/model.php 配置按名取 Provider。
 */
class ModelProviderService
{
    /** @var array<string, ModelProviderInterface> */
    private array $instances = [];

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ContainerInterface $container
    ) {}

    /**
     * 取指定 Provider 实例；省略时返回默认 Provider。
     */
    public function driver(?string $name = null): ModelProviderInterface
    {
        $name ??= (string) $this->config->get('model.default', 'dashscope');

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $providers = (array) $this->config->get('model.providers', []);
        if (! isset($providers[$name]) || ! is_array($providers[$name])) {
            throw new BusinessException(
                Code::AI_PROVIDER_MISSING,
                "Model provider [{$name}] not configured",
                500
            );
        }

        $cfg = $providers[$name];
        $driver = (string) ($cfg['driver'] ?? '');
        if ($driver === '' || ! class_exists($driver)) {
            throw new BusinessException(
                Code::AI_PROVIDER_MISSING,
                "Model provider driver [{$driver}] not found",
                500
            );
        }

        /** @var ModelProviderInterface $instance */
        $instance = new $driver($cfg);

        return $this->instances[$name] = $instance;
    }

    /**
     * 列出配置中存在且可用（api_key 非空）的 Provider 名称。
     *
     * @return array<int, string>
     */
    public function getAvailableProviders(): array
    {
        $providers = (array) $this->config->get('model.providers', []);
        $available = [];
        foreach ($providers as $name => $cfg) {
            if (! empty($cfg['api_key'])) {
                $available[] = (string) $name;
            }
        }

        return $available;
    }
}
