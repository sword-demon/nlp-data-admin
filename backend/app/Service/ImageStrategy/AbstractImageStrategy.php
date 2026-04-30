<?php

declare(strict_types=1);

namespace App\Service\ImageStrategy;

use GuzzleHttp\Client;
use Hyperf\Context\ApplicationContext;
use Hyperf\Guzzle\ClientFactory;

/**
 * 策略公共基类：Guzzle 工厂封装。
 */
abstract class AbstractImageStrategy
{
    /** 构造 HTTP 客户端（协程友好）。 */
    protected function http(array $options = []): Client
    {
        $defaults = [
            'timeout' => 20,
            'connect_timeout' => 10,
            'http_errors' => true,
        ];
        $options = array_merge($defaults, $options);

        if (ApplicationContext::hasContainer()) {
            /** @var ClientFactory $factory */
            $factory = ApplicationContext::getContainer()->get(ClientFactory::class);
            return $factory->create($options);
        }
        return new Client($options);
    }

    /** 简单归一关键词：去掉空白、截断长度。 */
    protected function normalizeKeyword(string $keyword): string
    {
        $k = trim($keyword);
        return mb_substr($k, 0, 80);
    }
}
