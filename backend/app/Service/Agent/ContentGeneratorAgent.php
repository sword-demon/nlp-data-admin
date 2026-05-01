<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Annotation\AgentLog;
use App\Constants\AgentPrompts;
use App\Constants\Code;
use App\Contract\AgentInterface;
use App\Exception\BusinessException;
use App\Service\Agent\Outcome\AgentOutcome;
use App\Service\Agent\Outcome\Payload\ContentDraft;
use App\Service\Agent\Outcome\Payload\Placeholder;
use App\Service\ModelProviderService;
use Generator;
use Hyperf\Contract\StdoutLoggerInterface;

/**
 * 正文生成 Agent：根据大纲流式生成 Markdown 正文。
 *
 * 结局契约（ADR-0003）：
 * - execute()        返回 AgentOutcome（payload=ContentDraft），内部聚合流后走 归一路径
 * - executeStream()  逐 chunk yield，末尾 return AgentOutcome，由 Orchestrator 用 getReturn() 拿
 *
 * 正文中由模型直接插入配图占位符：![配图:关键词](placeholder://image/N)
 * 完成后用正则抽取所有占位符，供 ImageAnalyzerAgent 使用。
 */
class ContentGeneratorAgent extends AbstractAgent implements AgentInterface
{
    public const PLACEHOLDER_PATTERN = '/!\[配图:([^\]]+)\]\(placeholder:\/\/image\/(\d+)\)/u';

    public function __construct(
        private readonly ModelProviderService $providers,
        private readonly StdoutLoggerInterface $logger
    ) {}

    public function getName(): string
    {
        return 'content_generator';
    }

    #[AgentLog(name: 'content_generator')]
    public function execute(array $context): AgentOutcome
    {
        // 走流式接口聚合 chunk，末尾取 Generator return value（唯一的归一入口）。
        $generator = $this->executeStream($context);
        foreach ($generator as $chunk) {
            // 耗尽就好，不做业务处理
            unset($chunk);
        }

        /** @var AgentOutcome $outcome */
        $outcome = $generator->getReturn();
        return $outcome;
    }

    #[AgentLog(name: 'content_generator_stream', logOutput: false)]
    public function executeStream(array $context): Generator
    {
        $title = (string) ($context['title'] ?? '');
        $style = (string) ($context['style'] ?? '通用');
        $supplement = (string) ($context['supplement'] ?? '');
        $outlineMarkdown = (string) ($context['outline_markdown'] ?? '');

        if ($title === '' || $outlineMarkdown === '') {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'title and outline required', 422);
        }

        $userPrompt = $this->render(AgentPrompts::CONTENT_USER_TPL, [
            'title' => $title,
            'style' => $style,
            'supplement' => $supplement !== '' ? $supplement : '（无）',
            'outline_markdown' => $outlineMarkdown,
        ]);

        // Phase 3.5：research preamble 拼接（无研究资料时空串）
        $preamble = $this->buildResearchPreamble($context['research_data'] ?? null);
        $userPrompt = $preamble . $userPrompt;

        $generator = $this->providers->driver()->chatStream(
            $userPrompt,
            [
                ['role' => 'system', 'content' => AgentPrompts::CONTENT_SYSTEM],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            ['temperature' => 0.7, 'max_tokens' => 4096]
        );

        $buffer = '';
        foreach ($generator as $chunk) {
            if ($chunk === '') {
                continue;
            }
            $buffer .= $chunk;
            yield $chunk;
        }

        $placeholders = $this->extractPlaceholderObjects($buffer);
        $this->logger->debug(
            '[ContentGeneratorAgent] generated ' . mb_strlen($buffer)
                . ' chars, ' . count($placeholders) . ' placeholders'
        );

        return AgentOutcome::ok(new ContentDraft(
            content: $buffer,
            placeholders: $placeholders,
            wordCount: mb_strlen($buffer),
        ));
    }

    /**
     * 从正文中抽取所有配图占位符（array 形式，保留给老外部调用者）。
     *
     * @return array<int, array{id: string, index: int, keyword: string}>
     */
    public function extractPlaceholders(string $content): array
    {
        $placeholders = [];
        if (preg_match_all(self::PLACEHOLDER_PATTERN, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $placeholders[] = [
                    'id' => 'image/' . $m[2],
                    'index' => (int) $m[2],
                    'keyword' => trim($m[1]),
                ];
            }
        }
        return $placeholders;
    }

    /**
     * 抽取占位符为 Placeholder 对象数组，供 ContentDraft 构造。
     *
     * @return array<int, Placeholder>
     */
    private function extractPlaceholderObjects(string $content): array
    {
        $out = [];
        if (preg_match_all(self::PLACEHOLDER_PATTERN, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $out[] = new Placeholder(
                    id: 'image/' . $m[2],
                    index: (int) $m[2],
                    keyword: trim($m[1]),
                );
            }
        }
        return $out;
    }
}
