<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome;

use InvalidArgumentException;

/**
 * Agent 执行结局（ADR-0003 第 1 节）。
 *
 * 所有 Agent 的统一返回值对象。详细语义与不变量见 backend/docs/adr/0003-agent-outcome-three-state-contract.md。
 *
 * 典型用法：
 *   return AgentOutcome::ok(new TitleCandidates(titles: [...]));
 *   return AgentOutcome::degraded(
 *       new ResearchBundle(...empty..., fallback: true),
 *       DegradationReason::RATE_LIMITED,
 *       'hit 3 req/s threshold',
 *   );
 *
 * 不变量（由构造器守护）：
 *   - status=OK    ⇒ reason=null && detail=null
 *   - status=DEGRADED ⇒ reason !== null
 *   - status=FAILED   由异常冒泡构成，本类不开放此态的静态构造
 */
final class AgentOutcome
{
    private function __construct(
        public readonly OutcomeStatus $status,
        public readonly object $payload,
        public readonly ?DegradationReason $reason = null,
        public readonly ?string $detail = null,
    ) {
        if ($status === OutcomeStatus::OK && ($reason !== null || $detail !== null)) {
            throw new InvalidArgumentException('OK outcome must not carry reason/detail');
        }
        if ($status === OutcomeStatus::DEGRADED && $reason === null) {
            throw new InvalidArgumentException('DEGRADED outcome requires a DegradationReason');
        }
        if ($status === OutcomeStatus::FAILED) {
            throw new InvalidArgumentException(
                'FAILED outcome must not be constructed directly; throw an exception instead'
            );
        }
    }

    public static function ok(object $payload): self
    {
        return new self(OutcomeStatus::OK, $payload);
    }

    public static function degraded(
        object $payload,
        DegradationReason $reason,
        ?string $detail = null,
    ): self {
        return new self(OutcomeStatus::DEGRADED, $payload, $reason, $detail);
    }

    public function isOk(): bool
    {
        return $this->status === OutcomeStatus::OK;
    }

    public function isDegraded(): bool
    {
        return $this->status === OutcomeStatus::DEGRADED;
    }

    /**
     * 转换为数组（用于缓存序列化）
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payloadArray = [];
        if (method_exists($this->payload, 'toArray')) {
            $payloadArray = $this->payload->toArray();
        }

        return [
            'status' => $this->status->value,
            'payload_class' => $this->payload::class,
            'payload_data' => $payloadArray,
            'reason' => $this->reason?->value,
            'detail' => $this->detail,
        ];
    }

    /**
     * 从数组创建实例（用于缓存反序列化）
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = OutcomeStatus::tryFrom((string) ($data['status'] ?? 'OK')) ?? OutcomeStatus::OK;
        $payloadClass = (string) ($data['payload_class'] ?? '');
        $payloadData = (array) ($data['payload_data'] ?? []);

        // 反序列化 payload
        $payload = null;
        if ($payloadClass !== '' && class_exists($payloadClass)) {
            if (method_exists($payloadClass, 'fromArray')) {
                $payload = $payloadClass::fromArray($payloadData);
            }
        }

        // 降级为空对象
        if ($payload === null) {
            $payload = (object)[];
        }

        // 反序列化 reason
        $reason = null;
        if (! empty($data['reason'])) {
            $reason = DegradationReason::tryFrom((string) $data['reason']);
        }

        if ($status === OutcomeStatus::OK) {
            return self::ok($payload);
        } elseif ($status === OutcomeStatus::DEGRADED && $reason !== null) {
            return self::degraded($payload, $reason, $data['detail'] ?? null);
        } else {
            return self::ok($payload);
        }
    }
}
