# Plan: Phase 6 — 可观测性系统

## Summary

通过 Hyperf AOP 注解实现 Agent 执行的自动日志记录：创建 `#[AgentLog]` PHP 8 Attribute 注解，`AgentLogAspect` 切面拦截所有标注方法，自动记录执行耗时、输入输出摘要、成功/失败状态到 `agent_logs` 表。在此基础上构建 Dashboard API 提供执行统计（耗时分布、成功率趋势、Agent 热力图、用户活跃度）。

## User Story

As a 平台管理员/开发者, I want 查看 Agent 执行的详细日志和统计报表, So that 我可以监控平台运行状态、发现性能瓶颈、优化 Agent 策略。

## Problem → Solution

当前 Agent 执行无结构化日志 → 通过 AOP 注解零侵入地记录每次 Agent 调用的耗时/状态/摘要 → Dashboard API 聚合统计，前端可视化展示。

## Metadata

- **Complexity**: Medium
- **Source PRD**: `.claude/PRPs/prds/ai-multi-agent-content-platform.prd.md`
- **PRD Phase**: Phase 6 — 可观测性系统
- **Estimated Files**: 12+

---

## UX Design

### Before
```
┌─────────────────────────────┐
│  Agent 执行无日志            │
│  无法统计执行耗时            │
│  无法查看成功率              │
│  无可观测性数据              │
└─────────────────────────────┘
```

### After
```
┌─────────────────────────────┐
│  Dashboard → 可观测性面板    │
│  ┌───────────────────────┐  │
│  │ 今日 Agent 调用统计    │  │
│  │ 总调用: 1,234          │  │
│  │ 成功率: 96.8%          │  │
│  │ 平均耗时: 8.2s         │  │
│  │ 最慢 Agent: ContentGen │  │
│  └───────────────────────┘  │
│  ┌───────────────────────┐  │
│  │ Agent 耗时趋势图       │  │
│  │ 成功率趋势图           │  │
│  │ 各 Agent 调用占比饼图  │  │
│  └───────────────────────┘  │
│  ┌───────────────────────┐  │
│  │ 最近 Agent 日志列表    │  │
│  │ [Agent] [耗时] [状态]  │  │
│  └───────────────────────┘  │
└─────────────────────────────┘
```

### Interaction Changes

| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Agent 执行 | 无日志 | 自动记录到 agent_logs | AOP 拦截，零侵入 |
| 管理后台 | 无可观测性 | Dashboard 统计图表 | 仅 admin 角色可见 |
| API 调试 | 看日志文件 | 结构化查询 API | 按时间/Agent/状态筛选 |
| 性能监控 | 不存在 | 慢查询告警列表 | duration_ms > 30s 标记 |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Model/AgentLog.php` | 1-43 | 日志模型 + 字段定义 |
| P0 | `backend/vendor/hyperf/db-connection/src/Annotation/Transactional.php` | all | PHP 8 Attribute 范例 |
| P0 | `backend/vendor/hyperf/db-connection/src/Aspect/TransactionAspect.php` | all | AbstractAspect 使用范例 |
| P1 | `backend/vendor/hyperf/di/src/Aop/AbstractAspect.php` | all | Aspect 基类 API |
| P1 | `backend/vendor/hyperf/di/src/Aop/ProceedingJoinPoint.php` | all | JoinPoint API（className/methodName/process） |
| P1 | `backend/config/autoload/aspects.php` | all | Aspect 注册位置 |
| P1 | `backend/config/autoload/annotations.php` | all | 注解扫描路径配置 |
| P2 | `backend/app/Model/User.php` | 1-83 | ROLE_ADMIN 常量 |
| P2 | `backend/app/Controller/AbstractController.php` | all | 控制器基类 |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| Hyperf AOP | `vendor/hyperf/di/src/Aop/AbstractAspect.php` | $annotations 属性匹配注解类，process() 接管方法执行 |
| PHP 8 Attributes | PHP 8.0+ | `#[Attribute]` 声明 + `Reflection::getAttributes()` 获取 |
| Hyperf AnnotationCollector | `vendor/hyperf/di/src/Annotation/AnnotationCollector.php` | `getClassMethodAnnotation()` 读取方法上的注解实例 |

---

## Patterns to Mirror

### ANNOTATION_PATTERN (PHP 8 Attribute)
```php
// SOURCE: backend/vendor/hyperf/db-connection/src/Annotation/Transactional.php
use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_METHOD)]
class Transactional extends AbstractAnnotation
{
    // Constructor params = annotation config
    // Must extend AbstractAnnotation
    // Must use #[Attribute(...)] declaration
}
```

### ASPECT_PATTERN
```php
// SOURCE: backend/vendor/hyperf/db-connection/src/Aspect/TransactionAspect.php
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;

class TransactionAspect extends AbstractAspect
{
    public array $annotations = [Transactional::class];

    public function process(ProceedingJoinPoint $proceedingJoinPoint): mixed
    {
        // Before: $proceedingJoinPoint->className, methodName, getArguments()
        $result = $proceedingJoinPoint->process(); // Execute original method
        // After: $proceedingJoinPoint->result
        return $result;
    }
}
```

### ASPECT_REGISTRATION
```php
// SOURCE: backend/config/autoload/aspects.php
return [
    App\Aspect\AgentLogAspect::class,
];
```

### ANNOTATION_USAGE (on Agent methods)
```php
#[AgentLog(name: 'title_generator')]
public function generate(string $topic, string $style): array
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/app/Annotation/AgentLog.php` | CREATE | `#[AgentLog]` PHP 8 Attribute 注解定义 |
| `backend/app/Aspect/AgentLogAspect.php` | CREATE | AOP 切面 — 拦截并记录日志 |
| `backend/app/Service/ObservabilityService.php` | CREATE | 统计查询服务 |
| `backend/app/Controller/ObservabilityController.php` | CREATE | Dashboard API 端点 |
| `backend/app/Middleware/AdminMiddleware.php` | CREATE | admin 角色检查中间件 |
| `backend/config/autoload/aspects.php` | UPDATE | 注册 AgentLogAspect |
| `backend/config/routes.php` | UPDATE | 添加可观测性 API 路由 |
| `backend/app/Service/Agent/TitleGeneratorAgent.php` | UPDATE | 添加 `#[AgentLog]` 注解 |
| `backend/app/Service/Agent/OutlineGeneratorAgent.php` | UPDATE | 添加 `#[AgentLog]` 注解 |
| `backend/app/Service/Agent/ContentGeneratorAgent.php` | UPDATE | 添加 `#[AgentLog]` 注解 |
| `backend/app/Service/Agent/ImageAnalyzerAgent.php` | UPDATE | 添加 `#[AgentLog]` 注解 |
| `backend/app/Service/Agent/ParallelImageGenerator.php` | UPDATE | 添加 `#[AgentLog]` 注解 |

## NOT Building

- 分布式链路追踪（Jaeger/Zipkin）— 单体应用暂不需要
- 实时告警系统（钉钉/飞书通知）— 后续版本
- 日志 ELK 栈集成 — MySQL 存储足够
- 前端 Dashboard 可视化 — Phase 7 实现
- 自动性能优化建议 — 后续版本

---

## Step-by-Step Tasks

### Task 1: 创建 `#[AgentLog]` 注解
- **ACTION**: 定义 PHP 8 Attribute 注解类
- **IMPLEMENT**:
  ```php
  <?php
  namespace App\Annotation;

  use Attribute;
  use Hyperf\Di\Annotation\AbstractAnnotation;

  #[Attribute(Attribute::TARGET_METHOD)]
  class AgentLog extends AbstractAnnotation
  {
      public function __construct(
          public string $name = '',
          public bool $logInput = true,
          public bool $logOutput = true,
          public int $maxSummaryLength = 500,
      ) {}
  }
  ```
- **MIRROR**: 参考 `vendor/hyperf/db-connection/src/Annotation/Transactional.php`
- **IMPORTS**: `Attribute`, `Hyperf\Di\Annotation\AbstractAnnotation`
- **GOTCHA**: 属性必须为 public（AspectLoader 通过反射读取默认值）
- **VALIDATE**: 语法检查通过

### Task 2: 创建 AgentLogAspect
- **ACTION**: AOP 切面拦截 `#[AgentLog]` 标注方法
- **IMPLEMENT**:
  - 声明 `$annotations = [AgentLogAnnotation::class]`
  - `process()`:
    1. 从 `AnnotationCollector::getClassMethodAnnotation()` 读取注解配置
    2. 记录开始时间 `microtime(true)`
    3. 调用 `$proceedingJoinPoint->process()` 执行原方法
    4. 计算 `durationMs`
    5. 截取 input/output 摘要（限制长度）
    6. `AgentLog::query()->create(...)` 持久化
    7. 异常时记录 status=failed + error_message
  - 从 `Context::get('user_id')` 和 `Context::get('article_id')` 获取上下文
  - 日志写入失败时仅记录到 Logger（不影响业务）
- **MIRROR**: 参考 `vendor/hyperf/db-connection/src/Aspect/TransactionAspect.php`
- **IMPORTS**: `App\Annotation\AgentLog as AgentLogAnnotation`, `App\Model\AgentLog`, `Hyperf\Di\Aop\*`, `Hyperf\Context\Context`, `Psr\Log\LoggerInterface`
- **GOTCHA**:
  - `finally` 块中写日志，确保异常也能记录
  - `getArguments()` 返回有序参数数组，截取前 N 字符作为摘要
  - 流式方法（Generator 返回值）不记录 output（`logOutput: false`）
  - `$proceedingJoinPoint->result` 在 `process()` 返回后才可用
- **VALIDATE**: 标注 `#[AgentLog]` 的方法执行后 agent_logs 表有新记录

### Task 3: 注册 Aspect
- **ACTION**: 在 `config/autoload/aspects.php` 注册
- **IMPLEMENT**:
  ```php
  return [
      App\Aspect\AgentLogAspect::class,
  ];
  ```
- **MIRROR**: Hyperf Aspect 注册标准格式
- **IMPORTS**: 无
- **GOTCHA**: 注解扫描路径已在 `annotations.php` 中配置 `BASE_PATH . '/app'`，无需额外配置
- **VALIDATE**: Aspect 被加载，注解方法被拦截

### Task 4: 为所有 Agent 方法添加 `#[AgentLog]` 注解
- **ACTION**: 在 5 个 Agent 类的核心方法上添加注解
- **IMPLEMENT**:
  - `TitleGeneratorAgent::generate()` → `#[AgentLog(name: 'title_generator')]`
  - `OutlineGeneratorAgent::generate()` → `#[AgentLog(name: 'outline_generator')]`
  - `ContentGeneratorAgent::generate()` → `#[AgentLog(name: 'content_generator')]`
  - `ImageAnalyzerAgent::analyze()` → `#[AgentLog(name: 'image_analyzer')]`
  - `ParallelImageGenerator::generate()` → `#[AgentLog(name: 'parallel_image_generator', logOutput: false)]`
  - 添加 `use App\Annotation\AgentLog;` 导入
- **MIRROR**: 注解放在方法声明正上方
- **IMPORTS**: `App\Annotation\AgentLog`
- **GOTCHA**: `ParallelImageGenerator` 的 `logOutput: false` 因为返回大量图片 URL，摘要无意义
- **VALIDATE**: 每个 Agent 执行后 agent_logs 有对应记录

### Task 5: 创建 AdminMiddleware
- **ACTION**: admin 角色检查中间件（保护 Dashboard API）
- **IMPLEMENT**:
  - 从 JWT 上下文获取用户
  - 检查 `User::role === User::ROLE_ADMIN`
  - 非 admin 返回 403 JSON
- **MIRROR**: 参考 `JwtAuthMiddleware` 的中间件结构
- **IMPORTS**: `App\Model\User`, `Hyperf\Context\Context`
- **GOTCHA**: 需在 JWT 中间件之后执行（确保已认证）
- **VALIDATE**: 非 admin 用户访问 Dashboard API 返回 403

### Task 6: 创建 ObservabilityService
- **ACTION**: 统计查询服务
- **IMPLEMENT**:
  - `getOverview(string $startDate, string $endDate): array` — 总调用/成功率/平均耗时/最慢 Agent
  - `getAgentStats(string $startDate, string $endDate): array` — 各 Agent 的调用次数/成功率/平均耗时
  - `getDailyTrend(string $startDate, string $endDate): array` — 按天的调用次数/成功率趋势
  - `getSlowAgents(int $threshold = 30000, int $limit = 20): array` — 慢执行列表
  - `getRecentLogs(int $limit = 50): array` — 最近日志（支持按 agent_name/status 筛选）
  - `getUserActivity(int $userId, string $startDate, string $endDate): array` — 用户活跃度
- **MIRROR**: 使用 AgentLog Model 查询 + groupBy 聚合
- **IMPORTS**: `App\Model\AgentLog`, `Hyperf\DbConnection\Db`
- **GOTCHA**:
  - 日期范围默认最近 7 天
  - 统计查询可能较慢，考虑 Redis 缓存热点数据
  - 使用 DB::raw() 进行聚合（AVG, COUNT, SUM）
- **VALIDATE**: 调用 `getOverview()` 返回正确的统计数据

### Task 7: 创建 ObservabilityController
- **ACTION**: Dashboard API 端点
- **IMPLEMENT**:
  - `GET /api/admin/observability/overview?start_date=&end_date=` → 概览统计
  - `GET /api/admin/observability/agents?start_date=&end_date=` → Agent 维度统计
  - `GET /api/admin/observability/trend?start_date=&end_date=` → 日趋势
  - `GET /api/admin/observability/slow?threshold=30000&limit=20` → 慢执行
  - `GET /api/admin/observability/logs?agent_name=&status=&limit=50` → 最近日志
  - `GET /api/admin/observability/user/:id?start_date=&end_date=` → 用户活跃度
- **MIRROR**: 扩展 `AbstractController`，使用 `ApiResponse::success`
- **IMPORTS**: `App\Service\ObservabilityService`
- **GOTCHA**: 所有端点需要 admin 中间件保护
- **VALIDATE**: admin 用户调用 API 返回统计数据

### Task 8: 更新路由配置
- **ACTION**: 注册可观测性路由
- **IMPLEMENT**:
  - `Router::addGroup('/api/admin/observability', ...)` — admin 中间件组
  - 包含 overview/agents/trend/slow/logs/user 子路由
- **MIRROR**: 参考 Phase 2 的路由组模式
- **IMPORTS**: `Hyperf\HttpServer\Router\Router`
- **GOTCHA**: admin 路由组需要 JWT + Admin 双重中间件
- **VALIDATE**: `php bin/hyperf.php describe:routes` 显示新路由

### Task 9: 集成验证
- **ACTION**: 验证 AOP 日志记录和统计查询
- **IMPLEMENT**:
  - 启动全栈 → 创建创作会话 → 完成 3 阶段创作
  - 查询 agent_logs: `SELECT agent_name, duration_ms, status FROM agent_logs`
  - 调用 `/api/admin/observability/overview` 验证统计数据
  - 验证慢执行、Agent 统计等 API
- **MIRROR**: Phase 1/2 集成验证模式
- **IMPORTS**: N/A
- **GOTCHA**: 需要 admin 用户才能访问 Dashboard API
- **VALIDATE**: agent_logs 有完整执行记录，统计数据正确

---

## Testing Strategy

### Unit Tests

| Test | Input | Expected Output |
|---|---|---|
| AgentLog 注解 | 反射检查 | Attribute TARGET_METHOD |
| AgentLogAspect::process | 正常方法 | agent_logs 新增 1 条 status=success |
| AgentLogAspect::process | 抛异常方法 | agent_logs 新增 1 条 status=failed |
| ObservabilityService::getOverview | 最近 7 天 | {total, success_rate, avg_duration, slowest_agent} |
| ObservabilityService::getAgentStats | 最近 7 天 | [{name, count, success_rate, avg_duration}] |
| ObservabilityService::getDailyTrend | 最近 7 天 | [{date, count, success_rate, avg_duration}] |
| AdminMiddleware | non-admin user | 403 response |

### Edge Cases Checklist
- [ ] Agent 方法抛异常 → 日志记录 status=failed + error_message
- [ ] 流式方法（Generator） → logOutput=false 不记录输出
- [ ] agent_logs 表写入失败 → 不影响原方法执行
- [ ] 输入参数超大 → 截取到 maxSummaryLength
- [ ] 无日志数据时统计 → 返回空数据而非错误
- [ ] 大量日志时的聚合查询性能 → 添加 created_at 索引

---

## Validation Commands

### Static Analysis
```bash
php -l app/Annotation/AgentLog.php app/Aspect/AgentLogAspect.php \
  app/Service/ObservabilityService.php app/Controller/ObservabilityController.php \
  app/Middleware/AdminMiddleware.php
```
EXPECT: No syntax errors

### Database Validation
```bash
docker exec nlp-mysql mysql -u root -pnlp_root_2024 nlp_content \
  -e "SELECT agent_name, duration_ms, status, created_at FROM agent_logs ORDER BY id DESC LIMIT 10"
```
EXPECT: Agent 执行记录存在

### Observability API Test
```bash
# Create admin user first
TOKEN=$(curl -s -X POST http://localhost:9501/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"xxx"}' | jq -r '.data.token')

# Get overview
curl -H "Authorization: Bearer $TOKEN" \
  "http://localhost:9501/api/admin/observability/overview?start_date=2026-04-01&end_date=2026-04-30"
```
EXPECT: JSON with total, success_rate, avg_duration

### Agent Log Verification
```bash
# After running a creative workflow, check logs
docker exec nlp-mysql mysql -u root -pnlp_root_2024 nlp_content \
  -e "SELECT COUNT(*) as total, status, AVG(duration_ms) as avg_ms FROM agent_logs GROUP BY status"
```
EXPECT: success 和 failed 分组统计

---

## Acceptance Criteria
- [ ] `#[AgentLog]` 注解定义正确
- [ ] AgentLogAspect 自动拦截标注方法
- [ ] 所有 5 个 Agent 添加了注解
- [ ] agent_logs 表记录完整的执行信息
- [ ] 异常也被记录（status=failed）
- [ ] Dashboard API 返回正确统计
- [ ] admin 角色检查中间件工作正常
- [ ] 日志写入失败不影响业务

## Completion Checklist
- [ ] AOP 注解使用 PHP 8 Attribute（非 Doctrine）
- [ ] Aspect 注册到 aspects.php
- [ ] 注解扫描路径包含 app/ 目录
- [ ] 统计查询使用 groupBy 聚合
- [ ] 日志摘要长度有限制
- [ ] Context 传递 user_id 和 article_id
- [ ] 无硬编码值

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| AOP 代理类缓存导致注解不生效 | L | H | 清除 `runtime/container/` 缓存重启 |
| agent_logs 表数据量过大 | M | M | 定期归档 + created_at 索引 |
| 统计查询慢 | M | L | Redis 缓存 + 合理的日期范围限制 |
| Agent 方法签名变化 | L | M | 注解与具体参数无关（基于方法拦截） |

## Notes

- Hyperf 3.x 完全使用 PHP 8 Attributes，不再支持 Doctrine annotations
- `AnnotationCollector` 是注解元数据的中心存储，在启动时由扫描器填充
- `ProceedingJoinPoint::getArguments()` 返回位置参数数组，用于生成输入摘要
- 对于 Generator 返回值（流式方法），`$proceedingJoinPoint->result` 是 Generator 对象而非最终结果，因此 `logOutput: false`
- AdminMiddleware 可复用于未来其他管理后台 API
- agent_logs 表已在 Phase 1 创建（`migrations/2026_04_30_000003_create_agent_logs_table.php`）
