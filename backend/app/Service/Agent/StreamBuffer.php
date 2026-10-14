<?php

declare(strict_types=1);

namespace App\Service\Agent;

use Swoole\Coroutine\Channel;

/**
 * 协程安全流式缓冲区
 *
 * 支持:
 * - write(): 写入数据块
 * - subscribe(): 订阅进度触发器
 * - getProgress(): 获取当前进度 (0.0-1.0)
 *
 * 使用场景:
 * ContentGenerator 流式输出 50% 时触发 ImageAnalyzer 并行分析
 */
class StreamBuffer
{
    /** @var Channel 协程安全通道 */
    private Channel $channel;

    /** @var int 已写入字节数 */
    private int $written = 0;

    /** @var int 预估总字节数 */
    private int $estimatedTotal;

    /** @var array<float, array{threshold: float, callback: callable, triggered: bool}> 订阅者 */
    private array $subscribers = [];

    /** @var bool 是否已关闭 */
    private bool $closed = false;

    public function __construct(int $estimatedTotal = 1000, int $bufferSize = 64)
    {
        $this->estimatedTotal = max(1, $estimatedTotal);
        $this->channel = new Channel($bufferSize);
    }

    /**
     * 写入数据块
     */
    public function write(string $chunk): void
    {
        if ($this->closed) {
            return;
        }

        $chunkSize = strlen($chunk);
        $this->written += $chunkSize;
        $this->channel->push($chunk);

        // 检查并触发订阅者
        $this->checkSubscribers();
    }

    /**
     * 订阅进度触发器
     *
     * @param float $threshold 进度阈值 (0.0-1.0)
     * @param callable $callback 回调函数
     */
    public function subscribe(float $threshold, callable $callback): void
    {
        $threshold = max(0.0, min(1.0, $threshold));
        $this->subscribers[] = [
            'threshold' => $threshold,
            'callback' => $callback,
            'triggered' => false,
        ];

        // 按阈值排序，方便按序触发
        usort($this->subscribers, fn($a, $b) => $a['threshold'] <=> $b['threshold']);

        // 如果当前进度已超过阈值，立即触发
        if ($this->getProgress() >= $threshold) {
            $this->triggerCallback($callback);
            $this->subscribers[array_key_last($this->subscribers)]['triggered'] = true;
        }
    }

    /**
     * 获取当前进度 (0.0-1.0)
     */
    public function getProgress(): float
    {
        if ($this->estimatedTotal <= 0) {
            return 0.0;
        }
        return min(1.0, (float) $this->written / (float) $this->estimatedTotal);
    }

    /**
     * 读取一个数据块 (阻塞直到有数据或关闭)
     *
     * @return string|false 返回数据块，关闭时返回 false
     */
    public function read(): string|false
    {
        return $this->channel->pop();
    }

    /**
     * 关闭缓冲区
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->channel->close();
    }

    /**
     * 获取已写入字节数
     */
    public function getWritten(): int
    {
        return $this->written;
    }

    /**
     * 获取预估总字节数
     */
    public function getEstimatedTotal(): int
    {
        return $this->estimatedTotal;
    }

    /**
     * 检查并触发订阅者
     */
    private function checkSubscribers(): void
    {
        $currentProgress = $this->getProgress();

        foreach ($this->subscribers as &$subscriber) {
            if ($subscriber['triggered']) {
                continue;
            }

            if ($currentProgress >= $subscriber['threshold']) {
                $this->triggerCallback($subscriber['callback']);
                $subscriber['triggered'] = true;
            }
        }
    }

    /**
     * 在独立协程中触发回调
     */
    private function triggerCallback(callable $callback): void
    {
        go(function () use ($callback) {
            try {
                $callback($this->getProgress());
            } catch (\Throwable $e) {
                // 回调异常不应影响主流程
                // 日志记录由调用方处理
            }
        });
    }
}
