# StreamBuffer - 协程安全流式缓冲区

## 概述

StreamBuffer 是一个基于 Swoole Coroutine Channel 的协程安全流式缓冲区，支持进度追踪和阈值触发器。

主要用于 ContentGenerator 流式输出时，在达到特定进度（如 50%）时触发 ImageAnalyzer 并行分析。

## 核心特性

- ✅ **协程安全**：基于 `Swoole\Coroutine\Channel` 实现
- ✅ **进度追踪**：实时计算写入进度 (0.0-1.0)
- ✅ **阈值触发**：支持多个订阅者，达到阈值时自动触发回调
- ✅ **非阻塞回调**：回调在独立协程中执行，不阻塞主流程
- ✅ **重复触发防御**：每个订阅者只触发一次

## 使用示例

### 基本用法

```php
use App\Service\Agent\StreamBuffer;

// 创建缓冲区，预估总大小 10000 字节
$buffer = new StreamBuffer(10000);

// 订阅 50% 进度阈值
$buffer->subscribe(0.5, function (float $progress) {
    echo "已完成 50%，当前进度: {$progress}\n";
    // 触发 ImageAnalyzer 并行分析
});

// 流式写入数据
$buffer->write('chunk1');
$buffer->write('chunk2');
// ... 当写入达到 5000 字节时，回调自动触发

// 关闭缓冲区
$buffer->close();
```

### 多阶段触发

```php
$buffer = new StreamBuffer(10000);

// 订阅多个阈值
$buffer->subscribe(0.3, function () {
    echo "30% 完成\n";
});

$buffer->subscribe(0.5, function () {
    echo "50% 完成，触发 ImageAnalyzer\n";
    // 启动并行分析
});

$buffer->subscribe(0.8, function () {
    echo "80% 完成\n";
});

// 按序触发：30% → 50% → 80%
```

### ContentGenerator 集成示例

```php
public function generateContentWithParallelAnalysis(): void
{
    $buffer = new StreamBuffer(5000); // 预估 5000 字节

    // 订阅 50% 触发 ImageAnalyzer
    $buffer->subscribe(0.5, function () use ($buffer) {
        // 在独立协程中分析已生成内容
        go(function () use ($buffer) {
            $partialContent = $this->readFromBuffer($buffer);
            $this->imageAnalyzer->execute(['content' => $partialContent]);
        });
    });

    // 流式生成内容
    $generator = $this->contentAgent->executeStream($context);
    foreach ($generator as $chunk) {
        $buffer->write($chunk); // 自动触发进度检查
        $this->sse->broadcastContentChunk($stream, $chunk);
    }

    $buffer->close();
}
```

## API 文档

### `__construct(int $estimatedTotal = 1000, int $bufferSize = 64)`

创建流式缓冲区。

**参数：**
- `$estimatedTotal`: 预估总字节数，用于计算进度（默认 1000）
- `$bufferSize`: Channel 缓冲区大小（默认 64）

### `write(string $chunk): void`

写入数据块。

**参数：**
- `$chunk`: 数据块内容

**行为：**
- 累加已写入字节数
- 推送数据到 Channel
- 检查并触发订阅者

### `subscribe(float $threshold, callable $callback): void`

订阅进度触发器。

**参数：**
- `$threshold`: 进度阈值 (0.0-1.0)
- `$callback`: 回调函数，签名：`function(float $progress): void`

**行为：**
- 订阅者按阈值升序排列
- 达到阈值时在独立协程中触发回调
- 如果订阅时进度已超过阈值，立即触发
- 每个订阅者只触发一次

### `getProgress(): float`

获取当前进度。

**返回：**
- `0.0-1.0` 之间的进度值
- 超过预估值时返回 `1.0`

### `read(): string|false`

读取一个数据块（阻塞）。

**返回：**
- 数据块内容
- 缓冲区关闭时返回 `false`

### `close(): void`

关闭缓冲区。

**行为：**
- 标记为已关闭
- 关闭底层 Channel
- 关闭后 `write()` 不再写入数据

### `getWritten(): int`

获取已写入字节数。

### `getEstimatedTotal(): int`

获取预估总字节数。

## 技术细节

### 协程安全

使用 `Swoole\Coroutine\Channel` 实现协程间通信：

```php
private Channel $channel;

public function __construct(int $estimatedTotal = 1000, int $bufferSize = 64)
{
    $this->channel = new Channel($bufferSize);
}

public function write(string $chunk): void
{
    $this->channel->push($chunk); // 协程安全
}

public function read(): string|false
{
    return $this->channel->pop(); // 阻塞读取
}
```

### 进度计算

```php
public function getProgress(): float
{
    if ($this->estimatedTotal <= 0) {
        return 0.0;
    }
    return min(1.0, (float) $this->written / (float) $this->estimatedTotal);
}
```

- 进度 = 已写入字节数 / 预估总字节数
- 最大值限制为 `1.0`

### 阈值触发机制

```php
private function checkSubscribers(): void
{
    $currentProgress = $this->getProgress();

    foreach ($this->subscribers as &$subscriber) {
        if ($subscriber['triggered']) {
            continue; // 已触发，跳过
        }

        if ($currentProgress >= $subscriber['threshold']) {
            $this->triggerCallback($subscriber['callback']);
            $subscriber['triggered'] = true; // 标记已触发
        }
    }
}
```

**特性：**
- 每次 `write()` 后自动检查
- 订阅者按阈值升序排列
- 已触发的订阅者不再重复触发

### 非阻塞回调

```php
private function triggerCallback(callable $callback): void
{
    go(function () use ($callback) {
        try {
            $callback($this->getProgress());
        } catch (\Throwable $e) {
            // 回调异常不应影响主流程
        }
    });
}
```

**特性：**
- 回调在独立协程中执行
- `write()` 不会被回调阻塞
- 回调异常不会影响主流程

## 性能考虑

### 时间复杂度
- **write()**: O(S)，S 为订阅者数量（通常很小）
- **subscribe()**: O(S log S)，插入排序
- **getProgress()**: O(1)

### 内存占用
- Channel 缓冲区：`bufferSize × 平均 chunk 大小`
- 订阅者列表：O(S)
- 总体内存占用可控

### 并发性能
- 基于 Swoole Channel，天然支持高并发
- 测试：3 协程同时写入 100 次，计数准确

## 测试覆盖

所有测试场景已实现在 `StreamBufferTest.php`：

| 测试场景 | 方法 | 说明 |
|---------|------|------|
| ✅ 基本写入读取 | `testBasicWriteAndRead()` | 3 个 chunk 按序读取 |
| ✅ 进度计算 | `testProgressCalculation()` | 写入 500/1000 = 0.5 |
| ✅ 阈值触发 | `testThresholdTrigger()` | 超过 50% 触发回调 |
| ✅ 多订阅者 | `testMultipleSubscribers()` | 30%/50%/80% 按序触发 |
| ✅ 并发写入安全 | `testConcurrentWriteSafety()` | 3 协程写入 300 次 |
| ✅ 回调执行顺序 | `testCallbackExecutionOrder()` | 回调不阻塞 write |
| ✅ 重复触发防御 | `testRepeatedTriggerDefense()` | 回调仅触发 1 次 |
| ✅ 关闭缓冲区 | `testCloseBuffer()` | 关闭后读取返回 false |
| ✅ 立即订阅 | `testImmediateSubscribeAfterThreshold()` | 订阅时已超阈值 |
| ✅ 零预估值 | `testZeroEstimatedTotal()` | 自动修正为 1 |
| ✅ 回调异常 | `testCallbackException()` | 异常不影响主流程 |

## 与 WorkshopOrchestrator 的集成

### 流式生成 + 并行分析

```php
public function generateContentAndImages(Article $article, EventStream $stream): void
{
    $buffer = new StreamBuffer(5000);

    // 订阅 50% 触发 ImageAnalyzer
    $imageAnalysisStarted = false;
    $buffer->subscribe(0.5, function () use (&$imageAnalysisStarted, $buffer, $article) {
        if ($imageAnalysisStarted) {
            return;
        }
        $imageAnalysisStarted = true;

        // 在独立协程中分析已生成的内容
        go(function () use ($buffer, $article) {
            $this->logger->info("[Workshop#{$article->getKey()}] starting parallel image analysis at 50%");
            // 读取已写入的内容
            $partialContent = ''; // 从 buffer 读取
            // 启动分析
            $this->imageAnalyzer->execute(['content' => $partialContent]);
        });
    });

    // 流式生成内容
    $generator = $this->contentAgent->executeStream($context);
    foreach ($generator as $chunk) {
        $buffer->write($chunk); // 自动触发进度检查
        $this->sse->broadcastContentChunk($stream, $chunk);
    }

    $buffer->close();
}
```

详见 Issue #11 [U10] WorkshopOrchestrator - DAG 改造。

## 常见问题

### Q1: 预估值不准确怎么办？

A: 预估值仅用于计算进度百分比。实际写入可以超过预估值，进度最大为 `1.0`。建议根据历史数据设置合理的预估值。

### Q2: 订阅者可以动态添加吗？

A: 可以。如果订阅时进度已超过阈值，回调会立即触发。

### Q3: 回调中可以执行耗时操作吗？

A: 可以。回调在独立协程中执行，不会阻塞 `write()`。

### Q4: 回调抛出异常会怎样？

A: 异常会被捕获，不会影响主流程。建议在回调中自行处理异常和日志。

### Q5: Channel 缓冲区大小如何选择？

A: 默认 64 足够大多数场景。如果 `write()` 速度远大于 `read()` 速度，可以适当增大。

### Q6: 支持多个读取者吗？

A: 当前设计为单个读取者。多个读取者会竞争 Channel 中的数据。如需多播，可以在 `write()` 时推送到多个 Channel。

## 未来优化

1. **多播支持**：支持多个读取者同时读取
2. **背压控制**：写入速度过快时自动限速
3. **持久化**：支持将流数据持久化到磁盘
4. **统计信息**：记录写入速率、峰值速率等指标
5. **可视化监控**：实时展示流式进度和触发器状态
