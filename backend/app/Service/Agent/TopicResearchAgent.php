<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Annotation\AgentLog;
use App\Constants\AgentPrompts;
use App\Contract\AgentInterface;
use App\Service\Agent\Outcome\AgentOutcome;
use App\Service\Agent\Outcome\DegradationReason;
use App\Service\Agent\Outcome\Payload\ResearchBundle;
use App\Service\ModelProviderService;
use App\Service\SearchProviderService;
use Generator;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Redis\Redis;
use Throwable;

/**
 * 选题研究 Agent（Phase 3.5 / slice 03 完整化）
 *
 * 链路：缓存 → 单飞锁 → 令牌桶 → SearchProvider → LLM 浓缩 → 写缓存 → 返回
 *      ↓任一环节失败/超限/未启用 → 降级 (research_data=null, fallback=true)
 *
 * 输出契约（ADR-0003）：
 *   AgentOutcome(status, payload: ResearchBundle, reason?, detail?)
 *     - OK       => ResearchBundle{summary, sources, fallback=false}
 *     - DEGRADED => ResearchBundle::degradedEmpty(...) + DegradationReason
 *   下游用 $outcome->payload->toJsonColumn() 写 articles.research_data（降级 => null），
 *   用 ->toPreambleArray() 拼 preamble。
 *
 * 四道防线（详见 backend/CONTEXT.md "研究链路四道防线"）：
 *   1. Redis 缓存 24h —— 同选题秒级命中
 *   2. Redis SETNX 单飞锁 10s —— 同选题并发只放 1 个真实调用
 *   3. 令牌桶 N req/s —— 防短时间打爆 Exa 配额
 *   4. 用户配额（QuotaCheckMiddleware 已保证，此处不重复）
 *
 * 降级哲学见 backend/docs/adr/0001-external-service-failure-degradation.md：
 *   研究是"增强能力"，失败不得阻塞核心创作链路。
 */
class TopicResearchAgent extends AbstractAgent implements AgentInterface
{
    private const CACHE_KEY_PREFIX = 'research:cache:';
    private const LOCK_KEY_PREFIX = 'research:lock:';
    private const RATE_KEY_PREFIX = 'research:rate:';

    /** 单飞等待轮询间隔（毫秒） */
    private const WAIT_POLL_INTERVAL_MS = 500;
    /** 单飞最长等待秒数（与锁 TTL 对齐） */
    private const WAIT_MAX_SECONDS = 10;
    /** 锁 TTL（秒） */
    private const LOCK_TTL_SECONDS = 10;

    public function __construct(
        private readonly SearchProviderService $providers,
        private readonly ModelProviderService $models,
        private readonly Redis $redis,
        private readonly ConfigInterface $config,
        private readonly StdoutLoggerInterface $logger,
    ) {}

    public function getName(): string
    {
        return 'topic_research';
    }

    #[AgentLog(name: 'topic_research')]
    public function execute(array $context): AgentOutcome
    {
        $topic = trim((string) ($context['topic'] ?? ''));
        $defaultName = (string) $this->config->get('search.default', 'exa');

        if ($topic === '') {
            return $this->degradedOutcome($topic, $defaultName, DegradationReason::EMPTY_INPUT, 'empty topic');
        }

        $cacheKey = self::CACHE_KEY_PREFIX . md5($topic);
        $lockKey = self::LOCK_KEY_PREFIX . md5($topic);

        // 防线 1：缓存命中直接返回
        if ($cached = $this->readCache($cacheKey)) {
            $this->logger->info("[TopicResearchAgent] cache hit topic={$topic}");
            return AgentOutcome::ok($this->bundleFromCache($topic, $defaultName, $cached));
        }

        // 全局开关 + Provider 可用性预检（任意一项不通过 → 直接降级，不消耗令牌桶）
        $providerCfg = (array) $this->config->get("search.providers.{$defaultName}", []);
        if (empty($providerCfg['enabled']) || empty($providerCfg['api_key'])) {
            return $this->degradedOutcome(
                $topic,
                $defaultName,
                DegradationReason::PROVIDER_DISABLED,
                "provider [{$defaultName}] disabled or missing api key",
            );
        }

        // 防线 3：令牌桶（在拿锁前判，避免锁内排队）
        if (! $this->rateLimitPass()) {
            return $this->degradedOutcome($topic, $defaultName, DegradationReason::RATE_LIMITED, 'token bucket exceeded');
        }

        // 防线 2：单飞锁。拿不到锁说明同选题正在被另一个请求计算，转为等待缓存
        if (! $this->acquireLock($lockKey)) {
            $awaited = $this->waitForCache($cacheKey);
            if ($awaited !== null) {
                $this->logger->info("[TopicResearchAgent] cache awaited topic={$topic}");
                return AgentOutcome::ok($this->bundleFromCache($topic, $defaultName, $awaited));
            }
            return $this->degradedOutcome($topic, $defaultName, DegradationReason::LOCK_TIMEOUT, 'wait-for-cache timed out');
        }

        try {
            // —— 真实调用：搜索 + LLM 浓缩 ——
            $searchRes = $this->providers->driver($defaultName)->search($topic, []);
            $sources = (array) ($searchRes['results'] ?? []);
            if (empty($sources)) {
                return $this->degradedOutcome($topic, $defaultName, DegradationReason::EXTERNAL_EMPTY_RESULT, 'zero search results');
            }

            $summary = $this->summarize($topic, $sources);
            if ($summary === '') {
                return $this->degradedOutcome($topic, $defaultName, DegradationReason::EXTERNAL_EMPTY_RESULT, 'summarize empty output');
            }

            $data = ['summary' => $summary, 'sources' => $sources];
            $this->writeCache($cacheKey, $data);

            $this->logger->info(sprintf(
                '[TopicResearchAgent] computed topic=%s sources=%d summary_len=%d via=%s',
                $topic,
                count($sources),
                mb_strlen($summary),
                $defaultName
            ));

            return AgentOutcome::ok(new ResearchBundle(
                topic: $topic,
                provider: $defaultName,
                queriedAt: date('c'),
                summary: $summary,
                sources: $sources,
                fallback: false,
            ));
        } catch (Throwable $e) {
            $this->logger->warning(
                '[TopicResearchAgent] pipeline failed, degraded: ' . $e->getMessage()
            );
            return $this->degradedOutcome(
                $topic,
                $defaultName,
                DegradationReason::EXTERNAL_FAILED,
                'pipeline exception: ' . $e->getMessage(),
            );
        } finally {
            $this->releaseLock($lockKey);
        }
    }

    public function executeStream(array $context): Generator
    {
        $outcome = $this->execute($context);
        yield json_encode([
            'status' => $outcome->status->value,
            'fallback' => $outcome->isDegraded(),
        ], JSON_UNESCAPED_UNICODE);
        return $outcome;
    }

    /**
     * 调用 LLM 把 sources[] 浓缩成五段式 Markdown。
     * 任何异常都向上抛，由 execute() 统一接管降级。
     */
    private function summarize(string $topic, array $sources): string
    {
        $snippetLines = [];
        foreach ($sources as $i => $s) {
            $n = $i + 1;
            $title = trim((string) ($s['title'] ?? ''));
            $url = trim((string) ($s['url'] ?? ''));
            $snippet = trim((string) ($s['snippet'] ?? ''));
            // 单源 snippet 截断，控制 prompt 长度（每源最多 ~1.5k 字符）
            if (mb_strlen($snippet) > 1500) {
                $snippet = mb_substr($snippet, 0, 1500) . '...';
            }
            $snippetLines[] = "【来源 {$n}】{$title}\n{$url}\n{$snippet}";
        }
        $snippets = implode("\n\n", $snippetLines);

        $userPrompt = $this->render(AgentPrompts::RESEARCH_SUMMARY_USER_TPL, [
            'topic' => $topic,
            'snippets' => $snippets,
        ]);

        $raw = $this->models->driver()->chat(
            $userPrompt,
            [
                ['role' => 'system', 'content' => AgentPrompts::RESEARCH_SUMMARY_SYSTEM],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            ['temperature' => 0.3]   // 浓缩任务追求稳定性
        );

        $text = trim($raw);
        // 去围栏：LLM 偶尔会包 ```markdown
        if (str_starts_with($text, '```')) {
            $text = (string) preg_replace('/^```(?:markdown)?\s*/i', '', $text);
            $text = (string) preg_replace('/\s*```$/', '', $text);
            $text = trim($text);
        }
        return $text;
    }

    // —————— 防线 1：缓存 ——————

    private function readCache(string $key): ?array
    {
        try {
            $raw = $this->redis->get($key);
            if (! is_string($raw) || $raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            $this->logger->warning('[TopicResearchAgent] cache read failed: ' . $e->getMessage());
            return null;
        }
    }

    private function writeCache(string $key, array $data): void
    {
        $ttl = (int) $this->config->get('search.research.cache_ttl', 86400);
        try {
            $this->redis->setex($key, $ttl, json_encode($data, JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            $this->logger->warning('[TopicResearchAgent] cache write failed: ' . $e->getMessage());
        }
    }

    // —————— 防线 2：单飞锁 ——————

    private function acquireLock(string $lockKey): bool
    {
        try {
            // SET key 1 EX 10 NX —— 原子的锁获取
            $ok = $this->redis->set($lockKey, '1', ['NX', 'EX' => self::LOCK_TTL_SECONDS]);
            return $ok === true;
        } catch (Throwable $e) {
            $this->logger->warning('[TopicResearchAgent] acquireLock failed: ' . $e->getMessage());
            return false;
        }
    }

    private function releaseLock(string $lockKey): void
    {
        try {
            $this->redis->del($lockKey);
        } catch (Throwable) {
            // 忽略，TTL 兜底
        }
    }

    /**
     * 单飞等待：每 WAIT_POLL_INTERVAL_MS 轮询缓存，最多等 WAIT_MAX_SECONDS。
     */
    private function waitForCache(string $cacheKey): ?array
    {
        $deadline = microtime(true) + self::WAIT_MAX_SECONDS;
        while (microtime(true) < $deadline) {
            usleep(self::WAIT_POLL_INTERVAL_MS * 1000);
            if ($cached = $this->readCache($cacheKey)) {
                return $cached;
            }
        }
        return null;
    }

    // —————— 防线 3：令牌桶（按秒滑动窗口）——————

    private function rateLimitPass(): bool
    {
        $limit = (int) $this->config->get('search.research.rate_limit_per_second', 3);
        if ($limit <= 0) {
            return true;
        }
        $key = self::RATE_KEY_PREFIX . time();
        try {
            $count = (int) $this->redis->incr($key);
            if ($count === 1) {
                // 仅在首次创建时设 TTL，避免重置
                $this->redis->expire($key, 2);
            }
            return $count <= $limit;
        } catch (Throwable $e) {
            // Redis 故障不阻塞，回退为放行（让上游异常自然冒泡）
            $this->logger->warning('[TopicResearchAgent] rateLimit failed open: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * 统一降级出口：记日志 + 返回 DEGRADED 结局（payload 仍是完整 DTO，保证状态机不绕行）。
     */
    private function degradedOutcome(
        string $topic,
        string $provider,
        DegradationReason $reason,
        ?string $detail = null,
    ): AgentOutcome {
        $this->logger->info(sprintf(
            '[TopicResearchAgent] fallback reason=%s detail=%s',
            $reason->value,
            $detail ?? '',
        ));
        return AgentOutcome::degraded(
            ResearchBundle::degradedEmpty($topic, $provider),
            $reason,
            $detail,
        );
    }

    /**
     * 缓存中只存了 {summary, sources}，读出来时补齐 topic/provider/queriedAt 字段。
     *
     * @param array<string, mixed> $cached
     */
    private function bundleFromCache(string $topic, string $provider, array $cached): ResearchBundle
    {
        return new ResearchBundle(
            topic: $topic,
            provider: $provider,
            queriedAt: date('c'),
            summary: (string) ($cached['summary'] ?? ''),
            sources: (array) ($cached['sources'] ?? []),
            fallback: false,
        );
    }
}
