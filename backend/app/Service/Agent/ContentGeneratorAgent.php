<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Annotation\AgentLog;
use App\Constants\AgentPrompts;
use App\Constants\Code;
use App\Contract\AgentInterface;
use App\Exception\BusinessException;
use App\Service\ModelProviderService;
use Generator;
use Hyperf\Contract\StdoutLoggerInterface;

/**
 * 正文生成 Agent：根据大纲流式生成 Markdown 正文。
 *
 * - execute()：聚合所有流式 chunk 得到完整正文，返回 {content, placeholders}
 * - executeStream()：原样转发底层模型的流式增量，Orchestrator 会按 chunk 广播到 SSE
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
    public function execute(array $context): array
    {
        $buffer = '';
        foreach ($this->executeStream($context) as $chunk) {
            $buffer .= $chunk;
        }

        $placeholders = $this->extractPlaceholders($buffer);
        $this->logger->debug('[ContentGeneratorAgent] generated ' . mb_strlen($buffer) . ' chars, ' . count($placeholders) . ' placeholders');

        return [
            'content' => $buffer,
            'placeholders' => $placeholders,
            'word_count' => mb_strlen($buffer),
        ];
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

        $generator = $this->providers->driver()->chatStream(
            $userPrompt,
            [
                ['role' => 'system', 'content' => AgentPrompts::CONTENT_SYSTEM],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            ['temperature' => 0.7, 'max_tokens' => 4096]
        );

        foreach ($generator as $chunk) {
            if ($chunk === '') {
                continue;
            }
            yield $chunk;
        }
    }

    /**
     * 从正文中抽取所有配图占位符。
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
}
