<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * 创作工坊状态机：10 个状态覆盖从选题到完成的完整流程。
 *
 * 正向流转：
 *   DRAFT
 *     → TOPIC_RESEARCHING                         (Phase 3.5：外部检索+LLM 浓缩)
 *     → TITLE_GENERATING → TITLE_SELECTING        (等待用户选择)
 *     → OUTLINE_GENERATING → OUTLINE_EDITING      (等待用户确认)
 *     → CONTENT_GENERATING → IMAGE_ANALYZING → IMAGE_GENERATING
 *     → COMPLETED
 *
 * 任意状态都可跳转到 FAILED（不可逆）。
 */
enum WorkshopState: string
{
    case DRAFT = 'draft';
    case TOPIC_RESEARCHING = 'topic_researching';
    case TITLE_GENERATING = 'title_generating';
    case TITLE_SELECTING = 'title_selecting';
    case OUTLINE_GENERATING = 'outline_generating';
    case OUTLINE_EDITING = 'outline_editing';
    case CONTENT_GENERATING = 'content_generating';
    case IMAGE_ANALYZING = 'image_analyzing';
    case IMAGE_GENERATING = 'image_generating';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /**
     * 判断是否可从 $from 状态转到 $this。
     * 只允许：正向线性流转 / 任意态 → FAILED。
     */
    public function isReachableFrom(self $from): bool
    {
        if ($this === self::FAILED) {
            return true;
        }
        return match ($from) {
            self::DRAFT => $this === self::TOPIC_RESEARCHING,
            self::TOPIC_RESEARCHING => $this === self::TITLE_GENERATING,
            self::TITLE_GENERATING => $this === self::TITLE_SELECTING,
            self::TITLE_SELECTING => $this === self::OUTLINE_GENERATING,
            self::OUTLINE_GENERATING => $this === self::OUTLINE_EDITING,
            self::OUTLINE_EDITING => $this === self::CONTENT_GENERATING,
            self::CONTENT_GENERATING => $this === self::IMAGE_ANALYZING,
            self::IMAGE_ANALYZING => $this === self::IMAGE_GENERATING,
            self::IMAGE_GENERATING => $this === self::COMPLETED,
            self::COMPLETED, self::FAILED => false,
        };
    }

    /** 是否终态（不再流转）。 */
    public function isTerminal(): bool
    {
        return $this === self::COMPLETED || $this === self::FAILED;
    }

    /** 是否在等待用户交互的节点（human-in-the-loop）。 */
    public function isWaitingUser(): bool
    {
        return $this === self::TITLE_SELECTING || $this === self::OUTLINE_EDITING;
    }
}
