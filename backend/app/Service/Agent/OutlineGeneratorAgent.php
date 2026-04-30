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
 * 大纲生成 Agent：根据已选标题生成结构化大纲。
 *
 * 输出格式：
 *   {markdown: string, nodes: [{id, text, level, parent_id}]}
 */
class OutlineGeneratorAgent extends AbstractAgent implements AgentInterface
{
    public function __construct(
        private readonly ModelProviderService $providers,
        private readonly StdoutLoggerInterface $logger
    ) {}

    public function getName(): string
    {
        return 'outline_generator';
    }

    #[AgentLog(name: 'outline_generator')]
    public function execute(array $context): array
    {
        $title = (string) ($context['title'] ?? '');
        $supplement = (string) ($context['supplement'] ?? '');
        if ($title === '') {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'title is required', 422);
        }

        $userPrompt = $this->render(AgentPrompts::OUTLINE_USER_TPL, [
            'title' => $title,
            'supplement' => $supplement !== '' ? $supplement : '（无）',
        ]);

        $raw = $this->providers->driver()->chat(
            $userPrompt,
            [
                ['role' => 'system', 'content' => AgentPrompts::OUTLINE_SYSTEM],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            ['temperature' => 0.6]
        );

        $this->logger->debug("[OutlineGeneratorAgent] raw output: {$raw}");

        $decoded = $this->decodeJson($raw, $this->getName());

        $markdown = (string) ($decoded['markdown'] ?? '');
        $nodesRaw = $decoded['nodes'] ?? [];
        if ($markdown === '' || ! is_array($nodesRaw) || empty($nodesRaw)) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'invalid outline structure', 500);
        }

        $nodes = [];
        foreach ($nodesRaw as $n) {
            if (! is_array($n) || empty($n['text'])) {
                continue;
            }
            $nodes[] = [
                'id' => (int) ($n['id'] ?? count($nodes) + 1),
                'text' => (string) $n['text'],
                'level' => (int) ($n['level'] ?? 1),
                'parent_id' => isset($n['parent_id']) && $n['parent_id'] !== null ? (int) $n['parent_id'] : null,
            ];
        }

        return [
            'markdown' => $markdown,
            'nodes' => $nodes,
        ];
    }

    public function executeStream(array $context): Generator
    {
        $result = $this->execute($context);
        yield json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
