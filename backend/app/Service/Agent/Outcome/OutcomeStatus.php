<?php

declare(strict_types=1);

namespace App\Service\Agent\Outcome;

/**
 * Agent 执行结局的三态（ADR-0003）。
 *
 * - OK：正常完成，payload 为完整 DTO。
 * - DEGRADED：降级完成，payload 仍为完整 DTO（"状态机不绕行"），reason 必须非空。
 * - FAILED：不由 Agent 直接构造；核心能力失败时 Agent 抛异常，AgentLogAspect 与
 *           WorkshopOrchestrator::markFailed 共同负责此态的记录与收口。
 */
enum OutcomeStatus: string
{
    case OK = 'ok';
    case DEGRADED = 'degraded';
    case FAILED = 'failed';
}
