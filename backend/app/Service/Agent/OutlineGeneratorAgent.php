<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Annotation\AgentLog;
use App\Constants\AgentPrompts;
use App\Constants\Code;
use App\Contract\AgentInterface;
use App\Exception\BusinessException;
use App\Service\Agent\Outcome\AgentOutcome;
use App\Service\Agent\Outcome\Payload\OutlineDraft;
use App\Service\Agent\Outcome\Payload\OutlineNode;
use App\Service\ModelProviderService;
use Generator;
use Hyperf\Contract\StdoutLoggerInterface;

/**
 * 大纲生成 Agent：根据已选标题生成结构化大纲。
 *
 * Payload：OutlineDraft。markdown 或 nodes 为空抛异常 → FAILED。
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
    public function execute(array $context): AgentOutcome
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

        $preamble = $this->buildResearchPreamble($context['research_data'] ?? null);
        $userPrompt = $preamble . $userPrompt;

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
            $nodes[] = new OutlineNode(
                id: (int) ($n['id'] ?? count($nodes) + 1),
                text: (string) $n['text'],
                level: (int) ($n['level'] ?? 1),
                parentId: isset($n['parent_id']) && $n['parent_id'] !== null ? (int) $n['parent_id'] : null,
            );
        }

        if (empty($nodes)) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'outline has no valid nodes', 500);
        }

        return AgentOutcome::ok(new OutlineDraft(markdown: $markdown, nodes: $nodes));
    }

    public function executeStream(array $context): Generator
    {
        $outcome = $this->execute($context);
        /** @var OutlineDraft $payload */
        $payload = $outcome->payload;
        yield json_encode($payload->toArray(), JSON_UNESCAPED_UNICODE);
        return $outcome;
    }
}
