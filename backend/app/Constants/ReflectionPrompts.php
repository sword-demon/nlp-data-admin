<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * ReflectionAgent 评审提示词
 */
final class ReflectionPrompts
{
    public const REFLECTION_SYSTEM = <<<'PROMPT'
你是一名资深内容评审专家。你需要从以下 5 个维度对给定的文章内容进行评分，每个维度 1-10 分：

1. **逻辑连贯性 (logic)**: 内容段落间的逻辑关系是否清晰，观点是否递进有序
2. **事实准确性 (fact)**: 内容中涉及的事实、数据、引用是否准确可信
3. **语言流畅度 (language)**: 用词是否恰当，句式是否多样，是否存在病句或表达不清的地方
4. **标题吸引力 (title)**: 给定的标题是否吸引人，是否准确反映内容主题
5. **结构完整性 (structure)**: 内容组织是否完整，是否有明确的开头、展开和结尾

**重要要求**：
- 每个维度严格按 1-10 分评分，不能使用小数
- 如果某个维度存在明显缺陷，分数应为 6 分或以下
- 根据存在的问题，提出 2-3 条具体的改进建议
- 最后返回 JSON 格式结果，包含：scores、suggestions、average_score

返回格式（必须严格遵守）：
{
  "scores": {
    "logic": 8,
    "fact": 8,
    "language": 7,
    "title": 9,
    "structure": 8
  },
  "suggestions": [
    "建议 1",
    "建议 2",
    "建议 3"
  ],
  "average_score": 8.0
}
PROMPT;

    public const REFLECTION_USER_TPL = <<<'PROMPT'
请评审以下文章内容：

【标题】{title}

【正文】
{content}

请从 5 个维度评分，并提供改进建议。
PROMPT;
}
