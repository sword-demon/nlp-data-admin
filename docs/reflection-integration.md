# ReflectionAgent 集成文档

## 概述

在 `WorkshopOrchestrator` 中集成了 `ReflectionAgent`，用于在内容生成后进行质量评审，确保生成的内容符合质量标准。

## 工作流程

```
ContentGenerator 生成正文
        ↓
ReflectionAgent 评审（5 维度评分）
        ↓
    评分 >= 7？
    ├─ 是 → 继续配图
    └─ 否 → 触发重写
            ↓
        ContentGenerator 重新生成
            ↓
        ReflectionAgent 再次评审（不再重写）
            ↓
        继续配图
```

## SSE 事件

### 新增事件

| 事件名称 | 数据结构 | 说明 |
|---------|---------|------|
| `reflection_started` | `{}` | 评审开始 |
| `reflection_completed` | `{scores, suggestions, average_score, passed}` | 评审完成 |
| `reflection_error` | `{error}` | 评审异常（跳过评审继续配图） |
| `reflection_rewrite_triggered` | `{}` | 触发重写 |
| `reflection_rewrite_failed` | `{error}` | 重写失败（继续配图） |

### 评审完成事件数据结构

```json
{
  "scores": {
    "logic": 8,
    "fact": 8,
    "language": 8,
    "title": 8,
    "structure": 8
  },
  "suggestions": ["建议1", "建议2"],
  "average_score": 8.0,
  "passed": true
}
```

## 核心逻辑

### 评审标准

- 5 个维度：逻辑 (logic)、事实 (fact)、语言 (language)、标题 (title)、结构 (structure)
- 每个维度评分 1-10 分
- **通过标准：所有维度 >= 7 分**

### 重写策略

1. **首次评审不通过**：触发重写，最多 1 次
2. **重写后再次评审**：无论是否通过，都不再重写
3. **评审异常**：跳过评审，直接继续配图流程

### 异常处理

```php
try {
    $reflectionOutcome = $this->reflectionAgent->execute([...]);
    // 处理评审结果
} catch (Throwable $e) {
    $this->logger->warning("reflection failed, skipping: {$e->getMessage()}");
    $this->sse->broadcast($stream, 'reflection_error', ['error' => $e->getMessage()]);
    // 返回 true 表示跳过评审，继续流程
    return true;
}
```

## 测试覆盖

### 测试场景

1. ✅ **评分通过，不重写** - `testReflectionPassedNoRewrite()`
   - 所有维度 >= 7 分
   - 直接进入配图阶段

2. ✅ **评分不通过，触发重写** - `testReflectionFailedTriggerRewrite()`
   - 首次评审有维度 < 7 分
   - 触发重写
   - 重写后通过

3. ✅ **重写后仍不通过** - `testReflectionFailedAfterRewriteNoFurtherRewrite()`
   - 首次评审不通过
   - 触发重写
   - 重写后仍不通过
   - 不再重写，直接继续配图

4. ✅ **评审异常** - `testReflectionExceptionSkipAndContinue()`
   - ReflectionAgent 抛出异常
   - 跳过评审
   - 继续配图流程

## 代码位置

- **核心逻辑**：`backend/app/Service/WorkshopOrchestrator.php`
  - `performReflection()` 方法：执行评审
  - `generateContentAndImages()` 方法：集成评审流程

- **测试文件**：`backend/test/Unit/Service/WorkshopOrchestratorTest.php`

- **相关类**：
  - `App\Service\Agent\ReflectionAgent` - 评审 Agent
  - `App\Service\Agent\Outcome\Payload\ReflectionPayload` - 评审结果 DTO

## 性能考虑

1. **评审耗时**：ReflectionAgent 调用 LLM，耗时约 1-3 秒
2. **重写耗时**：ContentGenerator 重写，流式输出约 10-30 秒
3. **最坏情况**：首次生成 + 评审 + 重写 + 再次评审，总耗时约 40-60 秒

## 监控指标

建议监控以下指标：

- `reflection_pass_rate`：评审通过率
- `reflection_rewrite_rate`：重写触发率
- `reflection_error_rate`：评审异常率
- `reflection_avg_score`：平均评分
- `reflection_duration_p95`：评审耗时 P95

## 后续优化

1. **并行评审**：在生成过程中提前开始评审（需要流式评审）
2. **增量评审**：只评审修改的部分
3. **自适应阈值**：根据历史数据动态调整通过阈值
4. **多轮重写**：支持配置最大重写次数
