<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Annotation\AgentLog as AgentLogAnnotation;
use App\Model\AgentLog;
use Generator;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Throwable;

/**
 * AgentLogAspect — 拦截所有标注 #[AgentLog] 的方法，自动：
 *  - 记录开始时间 / 结束时间 / 耗时 duration_ms
 *  - 截取入参 / 返回值摘要
 *  - 捕获异常 → status=failed + error_message
 *  - 从 Hyperf Context 读取 user_id 和 article_id 关联业务实体
 *
 * 设计原则：
 *  - 日志写入失败不影响业务（catch 吞并 + StdoutLogger 记录）
 *  - 流式 Generator 方法应在注解上 logOutput=false，避免触发 Generator 迭代
 *  - 异常仍然抛出，不吞业务异常
 */
class AgentLogAspect extends AbstractAspect
{
    public array $annotations = [
        AgentLogAnnotation::class,
    ];

    public function __construct(private readonly StdoutLoggerInterface $logger) {}

    public function process(ProceedingJoinPoint $proceedingJoinPoint): mixed
    {
        $annotation = $this->resolveAnnotation($proceedingJoinPoint);

        $agentName = $annotation->name !== ''
            ? $annotation->name
            : $proceedingJoinPoint->className . '::' . $proceedingJoinPoint->methodName;

        $inputSummary = $annotation->logInput
            ? $this->summarize($proceedingJoinPoint->getArguments(), $annotation->maxSummaryLength)
            : '';

        $startMs = microtime(true);
        $status = AgentLog::STATUS_SUCCESS;
        $errorMessage = null;
        $outputSummary = '';

        try {
            /** @var mixed $result */
            $result = $proceedingJoinPoint->process();

            if ($annotation->logOutput && ! ($result instanceof Generator)) {
                $outputSummary = $this->summarize($result, $annotation->maxSummaryLength);
            }

            return $result;
        } catch (Throwable $e) {
            $status = AgentLog::STATUS_FAILED;
            $errorMessage = substr($e->getMessage(), 0, 1000);
            throw $e;
        } finally {
            $durationMs = (int) round((microtime(true) - $startMs) * 1000);
            $this->persist(
                $agentName,
                $inputSummary,
                $outputSummary,
                $durationMs,
                $status,
                $errorMessage
            );
        }
    }

    private function resolveAnnotation(ProceedingJoinPoint $joinPoint): AgentLogAnnotation
    {
        $collected = AnnotationCollector::getClassMethodAnnotation(
            $joinPoint->className,
            $joinPoint->methodName
        );
        $annotation = $collected[AgentLogAnnotation::class] ?? null;

        return $annotation instanceof AgentLogAnnotation
            ? $annotation
            : new AgentLogAnnotation();
    }

    /**
     * 将任意值转为不超过 $maxLen 的摘要字符串。
     */
    private function summarize(mixed $value, int $maxLen): string
    {
        if ($maxLen <= 0) {
            return '';
        }

        try {
            if (is_string($value)) {
                $text = $value;
            } elseif (is_scalar($value) || $value === null) {
                $text = (string) ($value ?? '');
            } else {
                $text = json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
                );
                if ($text === false) {
                    $text = '';
                }
            }
        } catch (Throwable) {
            $text = '';
        }

        if (mb_strlen($text) > $maxLen) {
            $text = mb_substr($text, 0, $maxLen) . '...';
        }
        return $text;
    }

    private function persist(
        string $agentName,
        string $inputSummary,
        string $outputSummary,
        int $durationMs,
        string $status,
        ?string $errorMessage
    ): void {
        try {
            AgentLog::query()->create([
                'user_id' => $this->contextInt('user_id') ?? 0,
                'article_id' => $this->contextInt('article_id'),
                'agent_name' => $agentName,
                'input_summary' => mb_substr($inputSummary, 0, 500),
                'output_summary' => $outputSummary,
                'duration_ms' => max(0, $durationMs),
                'status' => $status,
                'error_message' => $errorMessage,
            ]);
        } catch (Throwable $e) {
            // 日志写入失败不影响业务，降级到 Stdout
            $this->logger->error(
                '[AgentLogAspect] persist failed: ' . $e->getMessage()
                    . ' agent=' . $agentName . ' status=' . $status
            );
        }
    }

    private function contextInt(string $key): ?int
    {
        $v = Context::get($key);
        if ($v === null || $v === '') {
            return null;
        }
        return (int) $v;
    }
}
