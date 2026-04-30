<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\WorkshopState;
use Hyperf\Engine\Http\EventStream;

/**
 * SSE 事件广播器。
 *
 * SSE 格式：
 *   event: <name>
 *   data: <json>
 *   \n
 *   \n
 *
 * 心跳使用注释行 ":heartbeat\n\n"。
 */
class SseBroadcaster
{
    /**
     * 发送一个命名事件。
     *
     * @param array<string, mixed> $data
     */
    public function broadcast(EventStream $stream, string $event, array $data): void
    {
        $payload = 'event: ' . $event . "\n"
            . 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        $stream->write($payload);
    }

    /**
     * 状态机变更通知。
     *
     * @param array<string, mixed> $extra
     */
    public function broadcastStateChange(EventStream $stream, WorkshopState $state, array $extra = []): void
    {
        $this->broadcast($stream, 'state', array_merge([
            'state' => $state->value,
            'waiting_user' => $state->isWaitingUser(),
            'terminal' => $state->isTerminal(),
        ], $extra));
    }

    /** 正文流式增量。 */
    public function broadcastContentChunk(EventStream $stream, string $chunk): void
    {
        $this->broadcast($stream, 'content_chunk', ['text' => $chunk]);
    }

    /** 错误事件。 */
    public function broadcastError(EventStream $stream, string $message, string $agent = ''): void
    {
        $this->broadcast($stream, 'error', [
            'message' => $message,
            'agent' => $agent,
        ]);
    }

    /** 心跳（每 15 秒一次，保持连接）。 */
    public function heartbeat(EventStream $stream): void
    {
        $stream->write(":heartbeat\n\n");
    }

    /** 终止流。 */
    public function end(EventStream $stream): void
    {
        $stream->write("event: done\ndata: {}\n\n");
        $stream->end();
    }
}
