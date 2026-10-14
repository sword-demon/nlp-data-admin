# AgentDAG - DAG 编排器

## 概述

AgentDAG 是一个有向无环图 (DAG) 编排器，用于管理多个 Agent 之间的依赖关系并支持分层并行执行。

## 核心特性

- ✅ **环检测**：DFS 后向边检测算法，执行前检测环
- ✅ **拓扑排序**：Kahn 算法生成分层执行计划
- ✅ **并行执行**：基于 Hyperf Coroutine，同层节点并行执行
- ✅ **失败处理**：节点失败时停止后续层执行

## 使用示例

### 基本用法

```php
use App\Service\Agent\AgentDAG;
use Hyperf\Di\Annotation\Inject;

class MyService
{
    #[Inject]
    private StdoutLoggerInterface $logger;

    public function orchestrateAgents(): array
    {
        $dag = new AgentDAG($this->logger);

        // 添加节点
        $dag->addNode('research', $topicResearchAgent, ['topic' => '人工智能']);
        $dag->addNode('title', $titleGeneratorAgent, ['style' => '科技']);
        $dag->addNode('outline', $outlineGeneratorAgent);
        $dag->addNode('content', $contentGeneratorAgent);

        // 添加依赖关系
        $dag->addDependency('research', 'title');    // research → title
        $dag->addDependency('title', 'outline');     // title → outline
        $dag->addDependency('outline', 'content');   // outline → content

        // 执行 DAG
        $results = $dag->execute();

        return $results;
    }
}
```

### 菱形依赖示例

```php
// 菱形依赖: A → B, A → C, B → D, C → D
$dag = new AgentDAG($logger);

$dag->addNode('A', $agentA);
$dag->addNode('B', $agentB);
$dag->addNode('C', $agentC);
$dag->addNode('D', $agentD);

$dag->addDependency('A', 'B');
$dag->addDependency('A', 'C');
$dag->addDependency('B', 'D');
$dag->addDependency('C', 'D');

// 执行顺序：
// Layer 0: [A]
// Layer 1: [B, C]  ← 并行执行
// Layer 2: [D]
$results = $dag->execute();
```

## API 文档

### `addNode(string $nodeId, AgentInterface $agent, array $context = []): self`

添加节点到 DAG。

**参数：**
- `$nodeId`: 节点唯一标识符
- `$agent`: 实现 `AgentInterface` 的 Agent 实例
- `$context`: 节点执行时的上下文数据（可选）

**异常：**
- 节点 ID 重复时抛出 `BusinessException`

### `addDependency(string $from, string $to): self`

添加依赖关系：`$from` 必须在 `$to` 之前执行。

**参数：**
- `$from`: 前置节点 ID
- `$to`: 后置节点 ID

**异常：**
- 节点不存在时抛出 `BusinessException`

### `execute(): array<string, AgentOutcome>`

执行 DAG，返回所有节点的执行结果。

**返回：**
- 关联数组：`nodeId => AgentOutcome`

**异常：**
- 检测到环时抛出 `BusinessException`

**执行流程：**
1. DFS 环检测
2. Kahn 拓扑排序生成分层结构
3. 分层并行执行

### `getNodeIds(): array<int, string>`

获取所有节点 ID 列表。

### `getNodeCount(): int`

获取节点数量。

## 算法详解

### 1. DFS 环检测

使用深度优先搜索 (DFS) 检测后向边，时间复杂度 O(V + E)。

**后向边定义：**
- 如果在 DFS 递归栈中访问到已访问的节点，则存在环

**环路径提取：**
- 检测到环时，提取完整的环路径用于错误提示
- 示例：`A → B → C → A`

```php
// 环检测示例
$dag->addNode('A', $agentA);
$dag->addNode('B', $agentB);
$dag->addNode('C', $agentC);

$dag->addDependency('A', 'B');
$dag->addDependency('B', 'C');
$dag->addDependency('C', 'A');  // 形成环

$dag->execute();  // 抛出异常: "cycle detected in DAG: A → B → C → A"
```

### 2. Kahn 拓扑排序

使用 Kahn 算法生成分层结构，时间复杂度 O(V + E)。

**算法步骤：**
1. 初始化入度表：统计每个节点的入度
2. 第一层：所有入度为 0 的节点
3. 迭代：
   - 处理当前层节点
   - 更新邻居入度
   - 入度变为 0 的节点加入下一层
4. 验证：所有节点都被处理

**分层结构示例：**
```
输入: A → B, A → C, B → D, C → D

输出分层:
Layer 0: [A]
Layer 1: [B, C]
Layer 2: [D]
```

### 3. 并行执行

使用 Hyperf Coroutine 实现同层节点并行执行。

**执行策略：**
- 单节点层：串行执行
- 多节点层：并行执行（使用 `Hyperf\Coroutine\Parallel`）

**失败处理：**
- 节点返回 `FAILED` 状态时，停止后续层执行
- 节点抛出异常时，停止后续层执行

```php
// Layer 0: A 失败
// Layer 1: B, C 不会执行
// Layer 2: D 不会执行
```

## 上下文传播

执行节点时，会自动注入 `dag_results` 到上下文：

```php
$dag->addNode('A', $agentA, ['input' => 'data']);
$dag->addNode('B', $agentB);
$dag->addDependency('A', 'B');

// 执行时，B 的 context 包含：
[
    'dag_results' => [
        'A' => AgentOutcome  // A 的执行结果
    ]
]
```

节点可以通过 `$context['dag_results']` 访问依赖节点的结果。

## 性能考虑

### 时间复杂度
- **环检测 (DFS)**：O(V + E)
- **拓扑排序 (Kahn)**：O(V + E)
- **执行**：取决于最长路径和节点执行时间

其中 V 是节点数，E 是边数。

### 空间复杂度
- O(V + E) 用于存储邻接表和入度表

### 并行度
- 最大并行度 = 最宽层的节点数
- 菱形依赖示例中，最大并行度为 2 (Layer 1: B, C)

## 测试覆盖

所有测试场景已实现在 `AgentDAGTest.php`：

| 测试场景 | 方法 | 说明 |
|---------|------|------|
| ✅ 简单 DAG 并行执行 | `testSimpleDAGParallelExecution()` | 3 个无依赖节点并行 |
| ✅ 链式依赖 | `testChainDependency()` | A → B → C 串行 |
| ✅ 菱形依赖 | `testDiamondDependency()` | 正确分层 |
| ✅ 简单环检测 | `testSimpleCycleDetection()` | A → B → C → A |
| ✅ 自环检测 | `testSelfLoopDetection()` | A → A |
| ✅ 多环检测 | `testMultipleCyclesDetection()` | 检测第一个环 |
| ✅ 节点失败停止 | `testNodeExecutionFailureStopsSubsequentLayers()` | 失败停止后续层 |
| ✅ 重复节点异常 | `testDuplicateNodeThrowsException()` | 节点 ID 重复 |
| ✅ 不存在节点异常 | `testDependencyOnNonExistentNodeThrowsException()` | 依赖不存在的节点 |
| ✅ 获取节点 ID | `testGetNodeIds()` | 返回所有节点 ID |
| ✅ 获取节点数量 | `testGetNodeCount()` | 返回节点数量 |
| ✅ 上下文传播 | `testContextPropagation()` | 验证上下文正确传递 |

## 常见问题

### Q1: 如何处理节点执行失败？

A: 节点失败时，AgentDAG 会：
1. 记录失败日志
2. 停止后续层执行
3. 返回已完成节点的结果

### Q2: 如何访问依赖节点的结果？

A: 通过 `$context['dag_results']` 访问：

```php
public function execute(array $context): AgentOutcome
{
    $previousResults = $context['dag_results'] ?? [];
    
    if (isset($previousResults['research'])) {
        $researchData = $previousResults['research']->payload;
        // 使用 research 的结果
    }
    
    // ...
}
```

### Q3: 可以动态添加节点吗？

A: 不可以。必须在调用 `execute()` 之前添加所有节点和依赖关系。

### Q4: 环检测的错误信息是什么？

A: 示例：`cycle detected in DAG: A → B → C → A`

包含完整的环路径，方便定位问题。

### Q5: 并行度可以控制吗？

A: 当前由 DAG 结构决定，同层节点全部并行。如需限制并发数，可在 Hyperf Coroutine 层配置。

## 与 WorkshopOrchestrator 的集成

AgentDAG 可用于替代 WorkshopOrchestrator 中的串行执行：

```php
// 传统串行方式
$research = $this->topicResearch->execute(...);
$title = $this->titleAgent->execute(...);
$outline = $this->outlineAgent->execute(...);

// DAG 并行方式
$dag = new AgentDAG($this->logger);
$dag->addNode('research', $this->topicResearch);
$dag->addNode('title', $this->titleAgent);
$dag->addNode('outline', $this->outlineAgent);
$dag->addDependency('research', 'title');
$results = $dag->execute();
```

详见 Issue #11 [U10] WorkshopOrchestrator - DAG 改造。

## 未来优化

1. **动态 DAG**：支持运行时添加节点
2. **条件执行**：基于前置节点结果决定是否执行
3. **重试机制**：节点失败时自动重试
4. **执行可视化**：生成 DAG 执行的 Mermaid 图
5. **并发控制**：限制最大并发数
