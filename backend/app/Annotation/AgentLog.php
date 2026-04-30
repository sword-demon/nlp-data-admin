<?php

declare(strict_types=1);

namespace App\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * #[AgentLog] — 方法级注解，标注后方法执行时会被 AgentLogAspect 拦截，
 * 自动记录一条 agent_logs 表数据（name/duration_ms/status/input_summary/output_summary）。
 *
 * 用法：
 *   #[AgentLog(name: 'title_generator')]
 *   public function execute(array $context): array { ... }
 *
 * 属性：
 *  - name              日志记录的 agent_name；为空则回退到 className::methodName
 *  - logInput          是否记录入参摘要（默认 true）
 *  - logOutput         是否记录返回值摘要（默认 true；流式 Generator 场景应设 false）
 *  - maxSummaryLength  input/output 摘要最大字符数（默认 500）
 */
#[Attribute(Attribute::TARGET_METHOD)]
class AgentLog extends AbstractAnnotation
{
    public function __construct(
        public string $name = '',
        public bool $logInput = true,
        public bool $logOutput = true,
        public int $maxSummaryLength = 500,
    ) {}
}
