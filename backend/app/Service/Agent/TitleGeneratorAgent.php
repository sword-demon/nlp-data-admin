<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Annotation\AgentLog;
use App\Constants\AgentPrompts;
use App\Constants\Code;
use App\Contract\AgentInterface;
use App\Exception\BusinessException;
use App\Service\Agent\Outcome\AgentOutcome;
use App\Service\Agent\Outcome\Payload\TitleCandidate;
use App\Service\Agent\Outcome\Payload\TitleCandidates;
use App\Service\ModelProviderService;
use Generator;
use Hyperf\Contract\StdoutLoggerInterface;

/**
 * 标题生成 Agent：输入 topic/style，输出 3-5 个候选标题（使用 Function Calling）。
 *
 * Payload：TitleCandidates。空输出抛异常 → FAILED 结局（核心能力）。
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
    public function execute(array $context): AgentOutcome
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

        // Phase 3.5：将 research_data 渲染为 preamble 拼在 user prompt 开头
        $preamble = $this->buildResearchPreamble($context['research_data'] ?? null);
        $userPrompt = $preamble . $userPrompt;

        // 定义 Function Calling tools
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_titles',
                    'description' => '生成爆款文章标题候选',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'titles' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => [
                                            'type' => 'string',
                                            'description' => '标题文本，12-30 字',
                                        ],
                                        'analysis' => [
                                            'type' => 'string',
                                            'description' => '标题分析，说明吸引点',
                                        ],
                                        'score' => [
                                            'type' => 'integer',
                                            'description' => '推荐分值 (0-100)',
                                            'minimum' => 0,
                                            'maximum' => 100,
                                        ],
                                    ],
                                    'required' => ['title', 'analysis', 'score'],
                                ],
                                'description' => '标题候选列表',
                            ],
                        ],
                        'required' => ['titles'],
                    ],
                ],
            ],
        ];

        try {
            // 使用 Function Calling 调用
            $result = $this->providers->driver()->chatWithTools(
                AgentPrompts::TITLE_SYSTEM,
                [['role' => 'user', 'content' => $userPrompt]],
                $tools,
                ['temperature' => 0.8]
            );

            // 如果有 tool_calls，解析结构化数据；否则降级回正则解析
            if ($result['tool_calls'] !== null && count($result['tool_calls']) > 0) {
                return $this->parseToolCallsResponse($result['tool_calls']);
            } else {
                // 降级回纯文本 + 正则解析
                $this->logger->info('[TitleGeneratorAgent] no tool_calls, fallback to text parsing');
                return $this->parseTextResponse($result['content']);
            }
        } catch (\Throwable $e) {
            $this->logger->error('[TitleGeneratorAgent] Function Calling failed: ' . $e->getMessage());
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'title generation failed: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * 解析 Function Calling 返回的结构化数据
     */
    private function parseToolCallsResponse(array $toolCalls): AgentOutcome
    {
        if (empty($toolCalls)) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'no tool_calls', 500);
        }

        $toolCall = $toolCalls[0];
        $arguments = $toolCall['function']['arguments'] ?? '{}';

        // 解析 arguments（可能是 JSON 字符串）
        if (is_string($arguments)) {
            $decoded = json_decode($arguments, true);
            if ($decoded === null) {
                throw new BusinessException(
                    Code::WORKSHOP_PARSE_ERROR,
                    'tool_calls arguments is not valid JSON',
                    422
                );
            }
        } else {
            $decoded = $arguments;
        }

        if (! isset($decoded['titles']) || ! is_array($decoded['titles'])) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'titles field missing or not array', 500);
        }

        $titles = [];
        foreach ($decoded['titles'] as $item) {
            if (! is_array($item) || empty($item['title'])) {
                continue;
            }
            $titles[] = new TitleCandidate(
                title: (string) $item['title'],
                analysis: (string) ($item['analysis'] ?? ''),
                score: (int) ($item['score'] ?? 0),
            );
        }

        if (empty($titles)) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'no title generated', 500);
        }

        return AgentOutcome::ok(new TitleCandidates(titles: array_slice($titles, 0, 5)));
    }

    /**
     * 降级：解析纯文本 JSON 响应
     */
    private function parseTextResponse(?string $content): AgentOutcome
    {
        if ($content === null || $content === '') {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'no content returned', 500);
        }

        $this->logger->debug('[TitleGeneratorAgent] fallback text response: ' . $content);
        $decoded = $this->decodeJson($content, $this->getName());

        $titles = [];
        foreach ($decoded as $item) {
            if (! is_array($item) || empty($item['title'])) {
                continue;
            }
            $titles[] = new TitleCandidate(
                title: (string) $item['title'],
                analysis: (string) ($item['analysis'] ?? ''),
                score: (int) ($item['score'] ?? 0),
            );
        }

        if (empty($titles)) {
            throw new BusinessException(Code::WORKSHOP_AGENT_FAILED, 'no title generated', 500);
        }

        return AgentOutcome::ok(new TitleCandidates(titles: array_slice($titles, 0, 5)));
    }

    public function executeStream(array $context): Generator
    {
        // 标题生成使用非流式：完成后一次性 yield 整个 JSON 字符串，流尾 return Outcome
        $outcome = $this->execute($context);
        /** @var TitleCandidates $payload */
        $payload = $outcome->payload;
        yield json_encode(['titles' => $payload->toArray()], JSON_UNESCAPED_UNICODE);
        return $outcome;
    }
}
