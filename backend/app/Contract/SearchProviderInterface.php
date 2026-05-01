<?php

declare(strict_types=1);

namespace App\Contract;

/**
 * 搜索 Provider 策略接口（Phase 3.5）。
 *
 * 抽象目标：让 TopicResearchAgent 不感知任何具体搜索厂商；
 * 当前首发 ExaProvider，未来可扩展 Tavily / Serper / 自建 ES 等。
 *
 * 详见 backend/docs/adr/0002-search-provider-abstraction.md
 */
interface SearchProviderInterface
{
    /**
     * Provider 名称（用于日志 / 工厂查找）。
     */
    public function getName(): string;

    /**
     * 对 query 做外部检索，返回归一化结构。
     *
     * @param string               $query   用户选题原文
     * @param array<string, mixed> $options 透传给 Provider 的可选项（numResults / lang 等覆盖默认）
     *
     * @return array{
     *     results: array<int, array{title:string, url:string, snippet:string, published_date:string, score?:float|null}>,
     *     meta: array<string, mixed>
     * }
     *
     * @throws \App\Exception\BusinessException Provider 未配置 / HTTP 错误 / 配额 / 限流均抛业务异常，由 Agent 层捕获转降级
     */
    public function search(string $query, array $options = []): array;
}
