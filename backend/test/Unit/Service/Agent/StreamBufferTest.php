<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service\Agent;

use App\Service\Agent\StreamBuffer;
use HyperfTest\TestCase;
use Swoole\Coroutine;

/**
 * StreamBuffer 测试
 */
class StreamBufferTest extends TestCase
{
    public function testBasicWriteAndRead()
    {
        $buffer = new StreamBuffer(1000);

        // 写入 3 个数据块
        $buffer->write('chunk1');
        $buffer->write('chunk2');
        $buffer->write('chunk3');

        // 按序读取
        $this->assertEquals('chunk1', $buffer->read());
        $this->assertEquals('chunk2', $buffer->read());
        $this->assertEquals('chunk3', $buffer->read());
    }

    public function testProgressCalculation()
    {
        $buffer = new StreamBuffer(1000);

        // 初始进度为 0
        $this->assertEquals(0.0, $buffer->getProgress());

        // 写入 500 字节
        $buffer->write(str_repeat('a', 500));
        $this->assertEquals(0.5, $buffer->getProgress());

        // 写入 300 字节
        $buffer->write(str_repeat('b', 300));
        $this->assertEquals(0.8, $buffer->getProgress());

        // 写入 200 字节，总共 1000
        $buffer->write(str_repeat('c', 200));
        $this->assertEquals(1.0, $buffer->getProgress());

        // 超过预估值，进度仍为 1.0
        $buffer->write(str_repeat('d', 100));
        $this->assertEquals(1.0, $buffer->getProgress());
    }

    public function testThresholdTrigger()
    {
        $buffer = new StreamBuffer(1000);

        $triggered = false;
        $triggeredProgress = 0.0;

        // 订阅 50% 阈值
        $buffer->subscribe(0.5, function (float $progress) use (&$triggered, &$triggeredProgress) {
            $triggered = true;
            $triggeredProgress = $progress;
        });

        $this->assertFalse($triggered);

        // 写入 400 字节，未超过 50%
        $buffer->write(str_repeat('a', 400));
        Coroutine::sleep(0.01); // 等待协程执行
        $this->assertFalse($triggered);

        // 写入 200 字节，超过 50%
        $buffer->write(str_repeat('b', 200));
        Coroutine::sleep(0.01); // 等待协程执行
        $this->assertTrue($triggered);
        $this->assertGreaterThanOrEqual(0.5, $triggeredProgress);

        // 再次写入，回调不应再触发
        $triggerCount = 0;
        $buffer->subscribe(0.5, function () use (&$triggerCount) {
            ++$triggerCount;
        });
        $buffer->write(str_repeat('c', 100));
        Coroutine::sleep(0.01);
        $this->assertEquals(0, $triggerCount); // 已经超过 50%，新订阅者会立即触发，但原订阅者不会
    }

    public function testMultipleSubscribers()
    {
        $buffer = new StreamBuffer(1000);

        $triggers = [];

        // 订阅 30%, 50%, 80% 三个阈值
        $buffer->subscribe(0.3, function (float $progress) use (&$triggers) {
            $triggers[] = ['threshold' => 0.3, 'progress' => $progress];
        });
        $buffer->subscribe(0.5, function (float $progress) use (&$triggers) {
            $triggers[] = ['threshold' => 0.5, 'progress' => $progress];
        });
        $buffer->subscribe(0.8, function (float $progress) use (&$triggers) {
            $triggers[] = ['threshold' => 0.8, 'progress' => $progress];
        });

        // 写入 350 字节，触发 30%
        $buffer->write(str_repeat('a', 350));
        Coroutine::sleep(0.01);
        $this->assertCount(1, $triggers);
        $this->assertEquals(0.3, $triggers[0]['threshold']);

        // 写入 250 字节，触发 50%
        $buffer->write(str_repeat('b', 250));
        Coroutine::sleep(0.01);
        $this->assertCount(2, $triggers);
        $this->assertEquals(0.5, $triggers[1]['threshold']);

        // 写入 400 字节，触发 80%
        $buffer->write(str_repeat('c', 400));
        Coroutine::sleep(0.01);
        $this->assertCount(3, $triggers);
        $this->assertEquals(0.8, $triggers[2]['threshold']);
    }

    public function testConcurrentWriteSafety()
    {
        $buffer = new StreamBuffer(10000);

        $concurrency = 3;
        $writesPerCoroutine = 100;

        // 3 个协程同时写入
        $wg = new Coroutine\WaitGroup();
        for ($i = 0; $i < $concurrency; ++$i) {
            $wg->add();
            go(function () use ($buffer, $writesPerCoroutine, $wg) {
                for ($j = 0; $j < $writesPerCoroutine; ++$j) {
                    $buffer->write('x'); // 每次写入 1 字节
                }
                $wg->done();
            });
        }
        $wg->wait();

        // 验证总写入字节数
        $expectedTotal = $concurrency * $writesPerCoroutine;
        $this->assertEquals($expectedTotal, $buffer->getWritten());
    }

    public function testConcurrentSubscribeSafety()
    {
        $buffer = new StreamBuffer(10000);

        $concurrency = 5;
        $subscribersPerCoroutine = 20;
        $triggerCount = 0;
        $lock = new \Swoole\Lock(SWOOLE_MUTEX);

        // 5 个协程同时订阅
        $wg = new Coroutine\WaitGroup();
        for ($i = 0; $i < $concurrency; ++$i) {
            $wg->add();
            go(function () use ($buffer, $subscribersPerCoroutine, &$triggerCount, $lock, $wg, $i) {
                for ($j = 0; $j < $subscribersPerCoroutine; ++$j) {
                    $threshold = ($i * $subscribersPerCoroutine + $j + 1) * 0.01; // 0.01 到 1.0
                    $buffer->subscribe($threshold, function () use (&$triggerCount, $lock) {
                        $lock->lock();
                        ++$triggerCount;
                        $lock->unlock();
                    });
                }
                $wg->done();
            });
        }
        $wg->wait();

        // 写入数据触发所有订阅者
        $buffer->write(str_repeat('x', 10000));
        Coroutine::sleep(0.1); // 等待所有回调执行

        // 验证所有订阅者都被触发
        $expectedTriggers = $concurrency * $subscribersPerCoroutine;
        $this->assertEquals($expectedTriggers, $triggerCount);
    }

    public function testConcurrentWriteAndSubscribe()
    {
        $buffer = new StreamBuffer(10000);

        $triggerCount = 0;
        $lock = new \Swoole\Lock(SWOOLE_MUTEX);

        $wg = new Coroutine\WaitGroup();

        // 协程 1: 持续写入
        $wg->add();
        go(function () use ($buffer, $wg) {
            for ($i = 0; $i < 100; ++$i) {
                $buffer->write(str_repeat('x', 100));
                Coroutine::sleep(0.001);
            }
            $wg->done();
        });

        // 协程 2: 持续订阅
        $wg->add();
        go(function () use ($buffer, &$triggerCount, $lock, $wg) {
            for ($i = 1; $i <= 50; ++$i) {
                $threshold = $i * 0.02; // 0.02 到 1.0
                $buffer->subscribe($threshold, function () use (&$triggerCount, $lock) {
                    $lock->lock();
                    ++$triggerCount;
                    $lock->unlock();
                });
                Coroutine::sleep(0.002);
            }
            $wg->done();
        });

        $wg->wait();
        Coroutine::sleep(0.1); // 等待所有回调执行

        // 验证至少有一些订阅者被触发
        $this->assertGreaterThan(0, $triggerCount);
    }
    }

    public function testCallbackExecutionOrder()
    {
        $buffer = new StreamBuffer(1000);

        $executionOrder = [];

        // 订阅 50% 阈值
        $buffer->subscribe(0.5, function () use (&$executionOrder) {
            Coroutine::sleep(0.02); // 模拟耗时操作
            $executionOrder[] = 'callback';
        });

        // 写入 600 字节，触发回调
        $buffer->write(str_repeat('a', 600));
        $executionOrder[] = 'after_write';

        // write 不应该阻塞，after_write 应该先于 callback 记录
        $this->assertEquals(['after_write'], $executionOrder);

        // 等待回调执行完成
        Coroutine::sleep(0.03);
        $this->assertEquals(['after_write', 'callback'], $executionOrder);
    }

    public function testRepeatedTriggerDefense()
    {
        $buffer = new StreamBuffer(1000);

        $triggerCount = 0;

        // 订阅 50% 阈值
        $buffer->subscribe(0.5, function () use (&$triggerCount) {
            ++$triggerCount;
        });

        // 写入 600 字节，超过 50%
        $buffer->write(str_repeat('a', 600));
        Coroutine::sleep(0.01);
        $this->assertEquals(1, $triggerCount);

        // 再写入 200 字节，回调不应再触发
        $buffer->write(str_repeat('b', 200));
        Coroutine::sleep(0.01);
        $this->assertEquals(1, $triggerCount);

        // 再写入 200 字节，回调仍不应触发
        $buffer->write(str_repeat('c', 200));
        Coroutine::sleep(0.01);
        $this->assertEquals(1, $triggerCount);
    }

    public function testGetWrittenAndEstimatedTotal()
    {
        $buffer = new StreamBuffer(2000);

        $this->assertEquals(0, $buffer->getWritten());
        $this->assertEquals(2000, $buffer->getEstimatedTotal());

        $buffer->write(str_repeat('a', 500));
        $this->assertEquals(500, $buffer->getWritten());

        $buffer->write(str_repeat('b', 300));
        $this->assertEquals(800, $buffer->getWritten());
    }

    public function testCloseBuffer()
    {
        $buffer = new StreamBuffer(1000);

        $buffer->write('chunk1');
        $buffer->write('chunk2');

        $buffer->close();

        // 关闭后写入不应抛出异常，但数据不会写入
        $buffer->write('chunk3');

        // 读取已有数据
        $this->assertEquals('chunk1', $buffer->read());
        $this->assertEquals('chunk2', $buffer->read());

        // 通道关闭后读取返回 false
        $this->assertFalse($buffer->read());
    }

    public function testImmediateSubscribeAfterThreshold()
    {
        $buffer = new StreamBuffer(1000);

        // 先写入 600 字节，已超过 50%
        $buffer->write(str_repeat('a', 600));

        $triggered = false;

        // 订阅 50% 阈值，应立即触发
        $buffer->subscribe(0.5, function () use (&$triggered) {
            $triggered = true;
        });

        Coroutine::sleep(0.01);
        $this->assertTrue($triggered);
    }

    public function testZeroEstimatedTotal()
    {
        $buffer = new StreamBuffer(0);

        // 预估值为 0 时，构造函数会修正为 1
        $this->assertEquals(1, $buffer->getEstimatedTotal());
        $this->assertEquals(0.0, $buffer->getProgress());

        $buffer->write('test');
        $this->assertEquals(1.0, $buffer->getProgress());
    }

    public function testCallbackException()
    {
        $buffer = new StreamBuffer(1000);

        $triggerCount = 0;

        // 订阅 50% 阈值，回调抛出异常
        $buffer->subscribe(0.5, function () use (&$triggerCount) {
            ++$triggerCount;
            throw new \RuntimeException('callback exception');
        });

        // 写入 600 字节，触发回调
        $buffer->write(str_repeat('a', 600));
        Coroutine::sleep(0.01);

        // 异常不应影响主流程
        $this->assertEquals(1, $triggerCount);
        $this->assertEquals(600, $buffer->getWritten());
    }
}
