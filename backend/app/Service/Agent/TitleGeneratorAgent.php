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
 * 标题生成 Agent：输入 topic/style，输出 3-5 个候选标题。
 *
 * 输出格式：
 *   [{title, analysis, score}]
 */
class TitleGeneratorAgent extends AbstractAgent implements AgentInterface
{
    public function __construct(
        private readonly ModelProviderService $providers,
        private readonly StdoutLoggerInterface $logger
    ) {}

    public function getName(): string
    {
        return 'title_generator';
    }

    #[AgentLog(name: 'title_generator')]
    public function execute(array $context): array
    {
        $topic = (string) ($context['topic'] ?? '');
        $style = (string) ($context['style'] ?? '通用');
        if ($topic === '') {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'topic is required', 422);
        }

        $userPrompt = $this->render(AgentPrompts::TITLE_USER_TPL, [
            'topic' => $topic,
            'style' => $style,
        ]);

        // Phase 3.5：将 research_data 渲染为 preamble 拼在 user prompt 开头（无研究资料时返回空串，行为回退）
        $preamble = $this->buildResearchPreamble($context['research_data'] ?? null);
        $userPrompt = $preamble . $userPrompt;

        $raw = $this->providers->driver()->chat(
            $userPrompt,
            [
                ['role' => 'system', 'content' => AgentPrompts::TITLE_SYSTEM],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            ['temperature' => 0.8]
        );

        $this->logger->debug("[TitleGeneratorAgent] raw output: {$raw}");

        $decoded = $this->decodeJson($raw, $this->getName());

        // 规整：仅保留合法对象
        $titles = [];
        foreach ($decoded as $item) {
            if (! is_array($item) || empty($item['title'])) {
                continue;
            }
            $titles[] = [
                'title' => (string) $item['title'],
                'analysis' => (string) ($item['analysis'] ?? ''),
                'score' => (int) ($item['score'] ?? 0),
            ];
        }

        if (empty($titles)) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'no title generated', 500);
        }

        return ['titles' => array_slice($titles, 0, 5)];
    }

    public function executeStream(array $context): Generator
    {
        // 标题生成使用非流式：完成后一次性 yield 整个 JSON 字符串
        $result = $this->execute($context);
        yield json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
