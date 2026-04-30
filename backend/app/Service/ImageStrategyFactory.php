<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\Code;
use App\Contract\ImageStrategyInterface;
use App\Dto\ImageResult;
use App\Exception\BusinessException;
use App\Service\ImageStrategy\EmojiStrategy;
use App\Service\ImageStrategy\IconifyStrategy;
use App\Service\ImageStrategy\MermaidStrategy;
use App\Service\ImageStrategy\NanoBananaStrategy;
use App\Service\ImageStrategy\PexelsStrategy;
use App\Service\ImageStrategy\SvgConceptStrategy;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Throwable;

/**
 * 配图策略工厂。
 *
 * - driver($type): 严格按名称返回策略实例；未知/禁用则抛 BusinessException
 * - fetchWithFallback($type, $keyword, $options): 按 fallback_chain 自动降级，
 *   全链失败抛 IMAGE_ALL_STRATEGIES_FAILED。调用方拿到的 ImageResult 就是可用的。
 */
class ImageStrategyFactory
{
    /** @var array<string, ImageStrategyInterface> */
    private array $strategies;

    /** @var array<string, array<int, string>> */
    private array $fallbackChain;

    public function __construct(
        PexelsStrategy $pexels,
        MermaidStrategy $mermaid,
        IconifyStrategy $iconify,
        EmojiStrategy $emoji,
        SvgConceptStrategy $svg,
        NanoBananaStrategy $nanoBanana,
        ConfigInterface $config,
        private readonly StdoutLoggerInterface $logger,
        private readonly ?VipService $vip = null
    ) {
        $this->strategies = [
            $pexels->getName() => $pexels,
            $mermaid->getName() => $mermaid,
            $iconify->getName() => $iconify,
            $emoji->getName() => $emoji,
            $svg->getName() => $svg,
            $nanoBanana->getName() => $nanoBanana,
        ];

        $this->fallbackChain = (array) $config->get('image.fallback_chain', []);
    }

    /** @return array<int, string> 当前已启用的策略名称列表 */
    public function getEnabledNames(): array
    {
        $names = [];
        foreach ($this->strategies as $name => $s) {
            if ($s->isEnabled()) {
                $names[] = $name;
            }
        }
        return $names;
    }

    /**
     * 按策略名返回实例（不做 fallback）。
     *
     * @throws BusinessException 当策略未知 / 被禁用 / VIP 无权
     */
    public function driver(string $type): ImageStrategyInterface
    {
        if (! isset($this->strategies[$type])) {
            throw new BusinessException(Code::IMAGE_STRATEGY_UNKNOWN, "unknown image strategy: {$type}", 500);
        }
        $s = $this->strategies[$type];
        if (! $s->isEnabled()) {
            throw new BusinessException(Code::IMAGE_STRATEGY_DISABLED, "strategy [{$type}] disabled", 500);
        }
        $this->assertVipAllowed($type);
        return $s;
    }

    /**
     * 校验当前用户 VIP 等级是否有权使用指定策略。
     *
     * - 非 HTTP 上下文（CLI / 定时任务）或未登录时跳过校验
     * - VipService 未注入时跳过（向后兼容）
     */
    private function assertVipAllowed(string $type): void
    {
        if ($this->vip === null) {
            return;
        }

        $userId = $this->currentUserId();
        if ($userId <= 0) {
            return;
        }

        $allowed = $this->vip->getUserAllowedStrategies($userId);
        if (empty($allowed) || in_array($type, $allowed, true)) {
            return;
        }

        throw new BusinessException(
            Code::IMAGE_STRATEGY_FORBIDDEN,
            "VIP 等级无权使用配图策略 [{$type}]，请升级会员",
            403
        );
    }

    private function currentUserId(): int
    {
        try {
            $container = ApplicationContext::getContainer();
            if (! $container->has(RequestInterface::class)) {
                return 0;
            }
            /** @var RequestInterface $request */
            $request = $container->get(RequestInterface::class);
            return (int) $request->getAttribute('user_id', 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * 按 fallback 链执行 fetch。若主策略失败，依次尝试链中下一个可用策略。
     *
     * @param string               $type     ImageAnalyzerAgent 建议的 suggested_type
     * @param string               $keyword  主关键词
     * @param array<string, mixed> $options  context / orientation 等
     *
     * @throws BusinessException IMAGE_ALL_STRATEGIES_FAILED 表示主链与降级链全部失败
     */
    public function fetchWithFallback(string $type, string $keyword, array $options = []): ImageResult
    {
        $candidates = array_values(array_unique(array_merge(
            [$type],
            $this->fallbackChain[$type] ?? [],
            // 最后兜底 emoji：它几乎无外部依赖，极少失败
            ['emoji']
        )));

        $errors = [];
        foreach ($candidates as $name) {
            if (! isset($this->strategies[$name])) {
                continue;
            }
            $strategy = $this->strategies[$name];
            if (! $strategy->isEnabled()) {
                $errors[] = "{$name}: disabled";
                continue;
            }

            try {
                $result = $strategy->fetch($keyword, $options);
                if ($name !== $type) {
                    $this->logger->info("[ImageStrategyFactory] [{$type}] failed, fallback to [{$name}] for [{$keyword}]");
                }
                return $result;
            } catch (Throwable $e) {
                $errors[] = "{$name}: " . $e->getMessage();
                $this->logger->warning("[ImageStrategyFactory] [{$name}] failed for [{$keyword}]: {$e->getMessage()}");
            }
        }

        throw new BusinessException(
            Code::IMAGE_ALL_STRATEGIES_FAILED,
            "all strategies failed for [{$type}:{$keyword}] => " . implode(' | ', $errors),
            502
        );
    }
}
