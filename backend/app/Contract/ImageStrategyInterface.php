<?php

declare(strict_types=1);

namespace App\Contract;

use App\Dto\ImageResult;

/**
 * 配图策略统一契约。
 *
 * 所有配图方式（Pexels / Mermaid / Iconify / Emoji / SvgConcept / NanoBanana）
 * 均实现此接口，由 ImageStrategyFactory 按 ImageAnalyzerAgent 输出的 suggested_type 分发。
 */
interface ImageStrategyInterface
{
    /** 策略短名称，与 ImageAnalyzerAgent 的 suggested_type 对齐。 */
    public function getName(): string;

    /** 人类可读标签，用于日志与前端展示。 */
    public function getLabel(): string;

    /** 当前策略是否处于可用状态（配置完整 + API key 存在等）。 */
    public function isEnabled(): bool;

    /** 策略是否支持 Agent 给出的 suggested_type。 */
    public function supports(string $type): bool;

    /**
     * 根据关键词获取一张配图。
     *
     * @param string                $keyword 主关键词
     * @param array<string, mixed>  $options 可选参数（context / orientation / alt 等）
     */
    public function fetch(string $keyword, array $options = []): ImageResult;
}
