---
title: OpenAPI DTO Generator - Plan
type: feat
date: 2026-10-15
topic: openapi-dto-generator
artifact_contract: ce-unified-plan/v1
artifact_readiness: implementation-ready
product_contract_source: ce-brainstorm
execution: code
---

# OpenAPI DTO Generator - Plan

## Goal Capsule

**Objective:** Agent Outcome DTO 的类型定义从手动维护的 PHP 类迁移到从 OpenAPI schema 自动生成,消除 schema 与实现不同步的风险。

**Means:** 自定义生成器 (symfony/yaml + Twig) 从模块化 schema 生成代码,CI 严格验证同步 (KTD1, KTD2)。

**Product Authority:** 覆盖现有 12 个手写 DTO (`backend/app/Service/Agent/Outcome/Payload/`) 的自动生成,不涉及其他 DTO 或非 Payload 类型。

**Open Blockers:** 无。

**Stop Conditions:**
- 所有 12 个生成 DTO 功能等价于手写版本 (相同签名、验证逻辑)
- 现有 Agent 测试通过,无回归
- CI 验证流程运行正常
- 手写 DTO 文件已删除

**Tail Ownership:** 实施完成后由 A1 (后端开发者) 负责日常维护 schema 和生成代码。

---

## Product Contract

### Summary

基于 OpenAPI Schema 的 PHP DTO 自动生成系统,覆盖现有 12 个 Agent Outcome Payload DTO。使用自定义生成器 (symfony/yaml + Twig) 生成与手写代码 100% 风格一致的 DTO,schema 按业务领域模块化组织 (title/outline/content/images/reflection),生成代码放置在 `Generated/` 子命名空间,CI 通过 git diff 严格验证同步。一次性迁移所有 DTO (单 PR),保留 OpenAPI 注解以兼容现有前端类型生成流程。

### Problem Frame

当前 Agent Outcome Payload DTO 使用手写 PHP 类加 `OpenApi\Attributes` 注解维护。随着 12 个 DTO 及未来更多 DTO 的增长,手动保持注解与实际代码同步成本高且易错。已有 PR#1 尝试用 `phpdocumentor/reflection-docblock` 自动生成前端类型,但因注解信息不足失败,暴露了当前 schema 单一数据源缺失的问题。

### Key Decisions

- **Schema 文件组织为模块化** (session-settled: user-directed — 选择模块化而非单一文件: 按业务领域分组提供更好的未来扩展性,减少合并冲突)
  - Governs R1, R2
- **生成代码放置在 Generated/ 子命名空间** (session-settled: user-directed — 选择独立子命名空间而非原地生成: 明确区分生成代码与手写代码,防止误修改,简化 CI 验证)
  - Governs R3
- **CI 验证策略采用严格模式** (session-settled: user-directed — 选择 git diff 检查而非自动提交: 可预测,生成代码变化在 PR 中可审查,符合行业惯例)
  - Governs R7

### Requirements

**Schema 组织**

- R1. Schema 文件按业务领域模块化组织在 `backend/openapi/schemas/` 下,主文件 `backend/openapi/openapi.yaml` 通过 `$ref` 引用各模块
- R2. Schema 模块划分为: `title.yaml` (标题相关)、`outline.yaml` (大纲相关)、`content.yaml` (正文相关)、`images.yaml` (图片相关)、`reflection.yaml` (反思相关)

**代码生成**

- R3. 生成的 DTO 放置在 `backend/app/Service/Agent/Outcome/Payload/Generated/` 目录,命名空间为 `App\Service\Agent\Outcome\Payload\Generated`
- R4. 生成的代码风格与现有手写 DTO 100% 一致: `final class`、`readonly`、命名参数、PSR-12 代码风格
- R5. 生成器支持嵌套对象、数组类型、nullable 属性、字符串长度验证
- R6. 生成器依赖仅为 dev 依赖: `symfony/yaml` + `twig/twig`

**开发工作流**

- R7. CI 运行 `composer generate:dtos` 并通过 `git diff --exit-code` 验证生成代码与 schema 同步,不同步则构建失败
- R8. 开发者修改 schema 后需本地运行 `composer generate:dtos` 并提交 schema + 生成代码
- R9. 生成器作为 Composer 脚本提供: `composer generate:dtos`

**迁移策略**

- R10. 现有 12 个手写 DTO 迁移到生成后,引用路径从 `App\Service\Agent\Outcome\Payload\<Name>` 变更为 `App\Service\Agent\Outcome\Payload\Generated\<Name>`
- R11. 现有手写 DTO 文件在生成器验证通过后删除

### Actors

- A1. **后端开发者** — 修改 schema,运行生成器,提交代码
- A2. **CI 系统** — 验证 schema 与生成代码同步
- A3. **Agent 实现代码** — 消费生成的 DTO

### Key Flows

- F1. 添加新 DTO
  - **Trigger:** 需要新增 Agent Outcome Payload DTO
  - **Actors:** A1, A2
  - **Steps:** 
    1. 开发者在相应 `schemas/*.yaml` 文件添加 schema 定义
    2. 本地运行 `composer generate:dtos`
    3. 检查生成的 PHP 类
    4. 提交 schema 文件 + 生成的 PHP 文件
    5. CI 验证生成代码与 schema 同步
  - **Outcome:** 新 DTO 可供 Agent 使用
  - **Covers R1, R3, R7, R8, R9**

- F2. 修改现有 DTO
  - **Trigger:** 需要修改字段或添加验证规则
  - **Actors:** A1, A2, A3
  - **Steps:**
    1. 开发者修改相应 `schemas/*.yaml` 文件
    2. 本地运行 `composer generate:dtos`
    3. Review 生成代码的变化
    4. 更新引用该 DTO 的 Agent 代码 (如签名变化)
    5. 提交 schema + 生成代码 + Agent 代码
    6. CI 验证
  - **Outcome:** DTO 更新,所有引用处已适配
  - **Covers R1, R3, R4, R7, R8**

- F3. 初始迁移 12 个手写 DTO
  - **Trigger:** 生成器实施完成,准备迁移现有 DTO
  - **Actors:** A1, A3
  - **Steps:**
    1. 根据现有 12 个手写 DTO 编写 schema
    2. 生成 DTO 并对比与手写版本的差异
    3. 调整 schema 或模板直到生成代码与手写版本功能等价
    4. 批量更新所有引用处的 import 路径
    5. 运行完整测试套件
    6. 删除手写 DTO 文件
    7. 提交 schema + 生成代码 + 引用更新 + 删除手写文件
  - **Outcome:** 所有 DTO 由 schema 驱动,手写类已移除
  - **Covers R2, R3, R4, R10, R11**

### Acceptance Examples

- AE1. Schema 变更自动反映到 DTO
  - **Covers R4, R7**
  - **Given:** `content.yaml` 中 `ContentDraft` 有字段 `title: string`
  - **When:** 开发者添加 `subtitle?: string` (nullable) 并运行生成器
  - **Then:** 
    - 生成的 `ContentDraft.php` 包含 `public readonly ?string $subtitle`
    - 构造函数签名为 `public function __construct(..., ?string $subtitle = null)`
    - CI 检查通过

- AE2. 忘记运行生成器时 CI 失败
  - **Covers R7, R8**
  - **Given:** 开发者修改了 `title.yaml` 但未运行生成器
  - **When:** 提交只包含 `title.yaml` 变更,不包含生成的 PHP 代码变更
  - **Then:** 
    - CI 运行 `composer generate:dtos`
    - `git diff` 检测到 `Generated/TitleCandidate.php` 有未提交变更
    - 构建失败,提示 "Generated DTOs are out of sync. Run 'composer generate:dtos' locally."

- AE3. 嵌套对象正确生成
  - **Covers R4, R5**
  - **Given:** `outline.yaml` 定义 `OutlineNode` 包含 `children: OutlineNode[]`
  - **When:** 运行生成器
  - **Then:**
    - 生成 `OutlineNode.php` 包含 `/** @var OutlineNode[] */ public readonly array $children`
    - 构造函数使用 `...` 展开符: `OutlineNode ...$children`
    - 代码风格与手写类一致

### Scope Boundaries

**Deferred for later:**

- 生成 API 端点代码 (仅生成 DTO)
- 生成数据库 Migration (DTO 不涉及持久化)
- 支持 OpenAPI 3.1 的新特性 (先基于 3.0 实施)
- 前端 TypeScript 类型自动生成 (优先解决后端 DTO,前端类型生成是后续 PR)

**Outside this product's identity:**

- 使用第三方 OpenAPI 生成器 (如 `jane-php/open-api`, `swagger-api/swagger-codegen`) — 原型已验证自定义方案代码风格更匹配
- 支持非 Payload DTO 的自动生成 — 当前范围明确限制在 `Agent/Outcome/Payload` 下

### Success Criteria

- 生成的 12 个 DTO 与手写版本功能等价 (相同签名、验证逻辑、命名风格)
- 现有所有 Agent 测试通过 (无回归)
- CI 能够检测并阻止 schema 与代码不同步
- 开发者文档清晰说明工作流和注意事项

### Sources / Research

- **原型验证:** `.context/compound-engineering/ce-prototype/2026-10-15-openapi-dto-generator/` — 完整的生成器原型,8/8 测试通过,证明技术方案可行
- **现有 DTO 位置:** `backend/app/Service/Agent/Outcome/Payload/` — 12 个手写类,目标迁移对象
- **现有类型生成尝试:** PR#1 尝试用 `phpdocumentor/reflection-docblock` 失败 — 触发本方案的动机
- **PSR-4 自动加载配置:** `backend/composer.json` — `App\` 映射到 `app/` 目录
- **已有 CI 策略:** U12 的自动提交模式成功运行 (前端类型生成),但本计划采用严格验证模式
- **历史迁移经验:** ADR 0003 记录一次性迁移 6 个 Agent 成功,当前规模 (12 个 DTO + 19 处引用) 可控

---

## Planning Contract

### Key Technical Decisions

- KTD1. **保留 OpenAPI 注解在生成的 DTO 中** — 现有 CI 依赖 PHP DTO 注解生成 swagger.json (通过 `zircote/swagger-php` 扫描 `OpenApi\Attributes`) 再生成前端类型。保留注解避免破坏现有工作流,Twig 模板生成完整的 `#[OA\Schema]` 和 `#[OA\Property]` 注解。Schema YAML 需要包含注解所需的 `description` 和 `example` 字段。替代方案 (重构 CI 直接从 YAML 生成 swagger.json) 改动更大且不必要。Governs R4, R6, U4。

- KTD2. **一次性迁移所有 12 个 DTO (单 PR)** — 历史经验 (ADR 0003) 支持一次性迁移 6 个 Agent,当前规模 (12 个 DTO + 19 处引用路径更新) 可控。避免中间态 (部分 DTO 生成 + 部分手写) 带来的混乱和维护成本。分批迁移会降低单次 PR 风险但引入多个中间状态,增加测试和代码审查复杂度。本地完整验证后再提交 PR。Governs F3, U6-U9。

- KTD3. **生成器错误处理使用标准 PHP 异常** — schema 解析失败时抛出 `\RuntimeException`,错误消息包含文件名和具体错误位置。Composer script 失败会自动中断 CI,无需复杂的错误恢复策略。Governs U3。

- KTD4. **不依赖 `php-cs-fixer` 自动格式化** — Twig 模板直接生成符合 PSR-12 的代码 (原型已验证缩进和换行精确控制)。避免生成器依赖重量级工具。CI 中添加 `php-cs-fixer --dry-run` 验证生成代码风格,但不自动修复。Governs R4, U4。

- KTD5. **`fromArray()` 方法默认生成** — 现有 8/12 DTO 有 `fromArray()` 静态工厂方法 (反序列化需求)。生成器默认为所有 DTO 生成该方法,除非 schema 明确标记 `x-skip-from-array: true`。使用 OpenAPI 扩展字段让 schema 显式控制该行为,统一覆盖避免手动维护。Governs R4, U4。

- KTD6. **CI 验证工作流采用严格模式** — 新增独立 CI 工作流 `.github/workflows/dto-validation.yml` 运行 `composer generate:dtos`,通过 `git diff --exit-code backend/app/Service/Agent/Outcome/Payload/Generated/` 验证无未提交变更。与现有前端类型生成 CI (自动提交模式) 独立,前者是下游产物自动化,后者是源代码严格验证。失败时提示明确错误消息指导开发者修复。Governs R7, U8。

### Technical Approach

生成器核心流程:
1. **Schema 解析** — `bin/generate-dtos.php` 使用 `symfony/yaml` 递归解析 `backend/openapi/openapi.yaml` 和引用的模块 schema,提取 `components.schemas` 下的 DTO 定义
2. **类型映射** — OpenAPI 类型映射到 PHP 类型: `string` → `string`、`integer` → `int`、`boolean` → `bool`、`array` → `array` + PHPDoc、`object` → 嵌套类引用、`nullable` → `?Type`
3. **模板渲染** — Twig 模板 `templates/dto.php.twig` 生成 PHP 类,包含 final class 定义、readonly 属性、命名参数构造函数、OpenAPI 注解、`fromArray()` 方法
4. **文件写入** — 生成的类写入 `backend/app/Service/Agent/Outcome/Payload/Generated/<Name>.php`,覆盖现有文件

现有代码复用:
- 原型 `.context/compound-engineering/ce-prototype/2026-10-15-openapi-dto-generator/` 的生成器逻辑和模板直接迁移到 `backend/bin/` 和 `backend/templates/`
- 现有 Composer scripts 模式 (frontend-types-generation) 作为参考

### Implementation Constraints

- 生成器仅支持 OpenAPI 3.0 schema (不支持 3.1 新特性如 `unevaluatedProperties`)
- 仅生成 `Agent/Outcome/Payload` 命名空间下的 DTO,其他命名空间不纳入自动生成
- 生成的代码不可手动修改,所有变更必须通过 schema 驱动
- 依赖 `zircote/swagger-php` 已存在于项目 (前端类型生成 CI 使用),无需新增

### Sequencing

Phase 1: 基础设施 (U1-U5)
- 先建立 schema 文件结构和生成器工具链
- 验证生成逻辑和 Composer 集成

Phase 2: 生成和验证 (U6)
- 生成所有 DTO 并对比手写版本
- 本地完整测试

Phase 3: 迁移和清理 (U7-U9)
- 批量更新引用路径
- 删除手写文件
- 添加 CI 验证

Phase 4: 文档和收尾 (U10)
- 更新开发者文档

---

## Implementation Units

### U1. 创建模块化 Schema 文件结构

**Goal:** 建立按业务领域组织的 OpenAPI schema 文件结构,定义所有 12 个 DTO。

**Requirements:** R1, R2

**Files:**
- `backend/openapi/openapi.yaml` (新建主文件)
- `backend/openapi/schemas/title.yaml` (新建,3 个 DTO: TitleCandidate/TitlePoll/TitleDecision)
- `backend/openapi/schemas/outline.yaml` (新建,2 个 DTO: OutlineNode/OutlineStructure)
- `backend/openapi/schemas/content.yaml` (新建,2 个 DTO: ContentDraft/ContentRevision)
- `backend/openapi/schemas/images.yaml` (新建,3 个 DTO: ImageryRequirement/ImagePlaceholder/ImageCaption)
- `backend/openapi/schemas/reflection.yaml` (新建,2 个 DTO: ReflectionPrompt/ReflectionResponse)

**Approach:**
- 参考手写 DTO (`backend/app/Service/Agent/Outcome/Payload/*.php`) 提取字段定义、类型、nullable、验证规则
- Schema 包含 OpenAPI 注解所需字段: `description`、`example`、`required`、`minLength`/`maxLength` (KTD1)
- 使用 `$ref` 引用嵌套对象 (如 `OutlineNode` 自引用)
- 标记 4 个 Images 相关 DTO 为 `x-skip-from-array: true` (无 `fromArray()` 方法,KTD5)
- 主文件 `openapi.yaml` 使用 `$ref: './schemas/<module>.yaml#/components/schemas/<Name>'` 引用各模块

**Test Scenarios:**
- Schema 文件通过 YAML 语法验证 (`symfony/yaml` 成功解析)
- 每个模块包含预期数量的 DTO 定义
- `$ref` 引用路径正确 (相对路径可解析)

**Verification:** U3 生成器可成功解析所有 schema 文件。

**Dependencies:** 无

---

### U2. 添加生成器依赖到 Composer

**Goal:** 将生成器所需的库添加为开发依赖。

**Requirements:** R6

**Files:**
- `backend/composer.json`
- `backend/composer.lock`

**Approach:**
- 运行 `composer require --dev symfony/yaml twig/twig`
- 验证版本兼容 (symfony/yaml 与现有 Symfony 组件兼容,Twig 最新稳定版)

**Test Scenarios:**
- `composer install` 成功
- `composer validate` 通过
- 依赖仅出现在 `require-dev`,不在 `require`

**Verification:** `vendor/` 包含 `symfony/yaml` 和 `twig/twig`,执行 `composer list` 无错误。

**Dependencies:** 无

---

### U3. 实现生成器脚本

**Goal:** 创建解析 schema 并生成 DTO 的核心脚本。

**Requirements:** R3, R5

**Files:**
- `backend/bin/generate-dtos.php` (新建)

**Approach:**
- 复用原型逻辑 `.context/compound-engineering/ce-prototype/2026-10-15-openapi-dto-generator/bin/generate.php`
- 核心步骤:
  1. 加载 `backend/openapi/openapi.yaml` (递归解析 `$ref`)
  2. 提取 `components.schemas` 下所有 schema 定义
  3. 遍历每个 schema,构建 Twig 模板数据 (类名、命名空间、属性列表、类型映射、nullable、OpenAPI 注解)
  4. 渲染模板 `templates/dto.php.twig`
  5. 写入 `backend/app/Service/Agent/Outcome/Payload/Generated/<Name>.php`
- 错误处理: schema 解析失败抛出 `\RuntimeException`,消息包含文件名和具体错误 (KTD3)
- 类型映射逻辑:
  - `string` → `string`
  - `integer` → `int`
  - `boolean` → `bool`
  - `array` items 类型 → `array` + PHPDoc `@var Type[]`
  - `$ref` → 解析为类引用
  - `nullable: true` → `?Type`

**Test Scenarios:**
- 解析合法 schema 成功生成 DTO
- 解析格式错误的 schema 抛出明确异常
- 嵌套 `$ref` 正确解析为类引用

**Verification:** 手动运行 `php backend/bin/generate-dtos.php` 生成 Generated 目录下的文件。

**Dependencies:** U1, U2

---

### U4. 创建 Twig 模板

**Goal:** 定义生成 PHP DTO 类的 Twig 模板,生成代码风格与手写版本 100% 一致。

**Requirements:** R4, R5

**Files:**
- `backend/templates/dto.php.twig` (新建)

**Approach:**
- 复用原型模板 `.context/compound-engineering/ce-prototype/2026-10-15-openapi-dto-generator/templates/dto.php.twig`
- 模板生成内容:
  - `<?php declare(strict_types=1);` 头部
  - `namespace App\Service\Agent\Outcome\Payload\Generated;`
  - `use` 语句 (嵌套类、OpenAPI 注解)
  - `#[OA\Schema(...)]` 类级别注解 (description、required、example,KTD1)
  - `final class <Name>`
  - `readonly` 属性声明 + `#[OA\Property(...)]` 注解
  - 命名参数构造函数 (参数名、类型、nullable、默认值)
  - `fromArray()` 静态工厂方法 (除非 `x-skip-from-array: true`,KTD5)
- 精确控制缩进 (4 空格) 和换行,符合 PSR-12 (KTD4)
- PHPDoc 格式: `/** @var Type[] */` 用于数组类型

**Test Scenarios:**
- 生成的代码通过 `php -l` 语法检查
- 生成的代码符合 PSR-12 (4 空格缩进、花括号换行)
- OpenAPI 注解完整 (description、type、example)
- `fromArray()` 方法正确处理嵌套对象和数组

**Verification:** U6 对比生成代码与手写版本,风格和功能一致。

**Dependencies:** 无

---

### U5. 添加 Composer script 命令

**Goal:** 提供便捷的 Composer 命令运行生成器。

**Requirements:** R9

**Files:**
- `backend/composer.json`

**Approach:**
- 在 `scripts` 部分添加:
  ```json
  "generate:dtos": "php bin/generate-dtos.php"
  ```
- 参考现有 script 命名风格 (kebab-case)

**Test Scenarios:**
- `composer generate:dtos` 成功运行生成器
- 命令输出无 Composer 警告或错误

**Verification:** 执行 `composer generate:dtos`,Generated 目录下生成或更新 12 个 PHP 文件。

**Dependencies:** U3, U4

---

### U6. 生成所有 DTO 并本地验证

**Goal:** 运行生成器生成所有 12 个 DTO,对比手写版本验证功能等价。

**Requirements:** R3, R4, R10

**Files:**
- `backend/app/Service/Agent/Outcome/Payload/Generated/` (新建目录)
- 生成 12 个 PHP 文件: `TitleCandidate.php`、`TitlePoll.php`、`TitleDecision.php`、`OutlineNode.php`、`OutlineStructure.php`、`ContentDraft.php`、`ContentRevision.php`、`ImageryRequirement.php`、`ImagePlaceholder.php`、`ImageCaption.php`、`ReflectionPrompt.php`、`ReflectionResponse.php`

**Approach:**
- 执行 `composer generate:dtos`
- 逐一对比生成的 DTO 与手写版本:
  - 属性数量、类型、nullable 一致
  - 构造函数签名一致 (参数顺序、默认值)
  - `fromArray()` 方法逻辑等价 (8/12 DTO 有此方法)
  - OpenAPI 注解完整
- 差异调整: 如生成代码与手写不匹配,调整 schema (U1) 或模板 (U4) 直到一致

**Test Scenarios:**
- 所有 12 个 DTO 生成成功
- 生成的类可被 Composer 自动加载 (`App\Service\Agent\Outcome\Payload\Generated\<Name>`)
- PHPStan 静态分析通过 (无类型错误)

**Verification:** 本地运行现有测试套件 (`composer test`),验证生成的 DTO 不引入回归 (引用路径暂未更新,但类定义应兼容)。

**Dependencies:** U1, U5

---

### U7. 批量更新引用路径

**Goal:** 将 19 处代码引用从手写 DTO 路径变更为生成 DTO 路径。

**Requirements:** R10

**Files:**
- 需要搜索更新: `backend/app/Service/Agent/` 下所有 `use App\Service\Agent\Outcome\Payload\<Name>;` 语句

**Approach:**
- 使用查找替换工具批量更新:
  ```
  查找: use App\Service\Agent\Outcome\Payload\(<Name>);
  替换: use App\Service\Agent\Outcome\Payload\Generated\$1;
  ```
  其中 `<Name>` 为 12 个 DTO 类名之一
- 手动 review 每处变更,确保上下文正确
- 运行 `composer dump-autoload` 刷新自动加载缓存

**Test Scenarios:**
- 所有引用处编译通过 (无 `Class not found` 错误)
- IDE 跳转定义指向 Generated 目录

**Verification:** 运行 `composer test`,所有测试通过 (无未解析类引用)。

**Dependencies:** U6

---

### U8. 添加 CI DTO 同步验证

**Goal:** 创建 CI 工作流,验证生成的 DTO 与 schema 同步。

**Requirements:** R7, R8

**Files:**
- `.github/workflows/dto-validation.yml` (新建)

**Approach:**
- 复用现有 CI 工作流结构 (参考 `.github/workflows/ci.yml`)
- 工作流步骤:
  1. Checkout 代码
  2. 设置 PHP 8.2 环境 (与生产环境对齐,修复 PHP 8.1 版本冲突)
  3. 安装 Composer 依赖 (`composer install`)
  4. 运行 `composer generate:dtos`
  5. 检查 git diff: `git diff --exit-code backend/app/Service/Agent/Outcome/Payload/Generated/ || (echo "Error: Generated DTOs are out of sync. Run 'composer generate:dtos' locally and commit changes." && exit 1)`
- 触发条件: `push` 到主分支和 PR,paths 包含 `backend/openapi/**` 或 `backend/app/Service/Agent/Outcome/Payload/Generated/**`
- 与现有前端类型生成 CI 独立,两者策略不同 (KTD6)

**Test Scenarios:**
- 手动修改 schema,不运行生成器,提交 — CI 应失败
- 修改 schema,本地运行生成器并提交 — CI 应通过
- 修改生成代码 (手动编辑 Generated/ 文件),提交 — CI 应失败

**Verification:** 在 PR 中观察 CI 运行结果,验证失败时错误消息清晰。

**Dependencies:** U7

---

### U9. 删除手写 DTO 文件

**Goal:** 在生成器验证通过后,删除原手写 DTO 文件。

**Requirements:** R11

**Files:**
- 删除: `backend/app/Service/Agent/Outcome/Payload/TitleCandidate.php`、`TitlePoll.php`、`TitleDecision.php`、`OutlineNode.php`、`OutlineStructure.php`、`ContentDraft.php`、`ContentRevision.php`、`ImageryRequirement.php`、`ImagePlaceholder.php`、`ImageCaption.php`、`ReflectionPrompt.php`、`ReflectionResponse.php`

**Approach:**
- 逐一删除 12 个手写文件
- 运行 `composer dump-autoload` 清理自动加载缓存

**Test Scenarios:**
- 删除后 `composer test` 仍通过 (所有引用已指向 Generated/)
- `git status` 显示 12 个文件删除

**Verification:** 运行 CI 工作流 (U8),验证通过。

**Dependencies:** U7, U8

---

### U10. 更新开发者文档

**Goal:** 在 README 添加生成器使用说明和工作流指引。

**Requirements:** R8, R9

**Files:**
- `backend/README.md`

**Approach:**
- 添加 "Agent Outcome DTO 管理" 章节:
  - 说明 DTO 由 OpenAPI schema 自动生成
  - 指引: 修改 schema 后运行 `composer generate:dtos`
  - 警告: 不要手动修改 `Generated/` 目录下的文件
  - CI 验证机制说明
  - schema 文件组织结构 (title/outline/content/images/reflection)
  - `fromArray()` 方法行为和 `x-skip-from-array` 扩展字段
- 参考现有 README 的代码风格和章节组织

**Test Scenarios:**
- 文档清晰说明工作流步骤
- 命令示例可直接复制执行

**Verification:** 请另一位开发者 review 文档,确认易读性和完整性。

**Dependencies:** U9

---

## Verification Contract

### Automated Tests

- **运行现有测试套件:** `composer test` — 验证无回归,所有 Agent 测试通过
- **PHPStan 静态分析:** `composer phpstan` — 验证生成代码无类型错误
- **PHP 语法检查:** `php -l backend/app/Service/Agent/Outcome/Payload/Generated/*.php` — 验证所有生成文件语法正确
- **代码风格检查:** `composer cs-check` 或 `./vendor/bin/php-cs-fixer fix --dry-run --diff backend/app/Service/Agent/Outcome/Payload/Generated/` — 验证生成代码符合 PSR-12

### CI Gates

- **DTO 同步验证 (U8 新增):** `.github/workflows/dto-validation.yml` 必须通过 — 验证 schema 与生成代码同步
- **现有 CI 工作流:** `.github/workflows/ci.yml` 必须通过 — 验证整体项目构建和测试

### Manual Verification

- **生成代码对比:** 人工 review 生成的 12 个 DTO 与手写版本,确认签名和逻辑等价
- **引用路径完整性:** 搜索项目中所有 `use App\Service\Agent\Outcome\Payload\` 语句,确认无遗漏或错误引用
- **开发者工作流测试:** 模拟修改 schema → 运行生成器 → 提交 PR,验证 CI 反馈准确

### Quality Thresholds

- 测试覆盖率保持不变或提升 (无回归)
- 静态分析无新增错误
- 生成代码通过所有代码风格检查

---

## Definition of Done

### Global Criteria

- 所有 12 个 DTO 由 schema 驱动,手写文件已删除
- 现有 Agent 测试套件 100% 通过,无回归
- CI DTO 同步验证工作流运行正常
- 开发者文档更新,工作流清晰说明
- 无未提交的临时文件、调试代码或实验性代码
- PR 已 merge 到主分支

### Per-Unit Completion Criteria

每个 Implementation Unit 在满足以下条件时视为完成:
- 单元目标 (Goal) 达成
- 所有列出的文件已修改或新建
- Test Scenarios 中的场景全部验证通过
- Verification 步骤执行无错误
- 依赖的前置单元已完成

### Cleanup Criterion

- 原型目录 `.context/compound-engineering/ce-prototype/2026-10-15-openapi-dto-generator/` 保留作为参考 (不算遗留代码)
- 无生成器调试输出或临时文件
- 无注释掉的实验性代码
