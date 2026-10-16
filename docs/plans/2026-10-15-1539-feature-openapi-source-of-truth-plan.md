---
title: OpenAPI 作为类型定义单一数据源
date: 2026-10-15
type: feature
artifact_contract: ce-unified-plan/v1
artifact_readiness: requirements-only
product_contract_source: ce-brainstorm
execution: code
---

## Goal Capsule

**Objective:** 前后端类型定义从同一 OpenAPI schema 生成,消除运行时对 swagger-php 的依赖并防止类型定义漂移。

**Product authority:** 本计划范围限定在 Agent Outcome Payload DTO 的类型生成流程重构,不涉及其他 API 端点或现有手写 DTO 的迁移。

**Open blockers:** 无

## Product Contract

### Summary

重构类型同步流程,将 OpenAPI schema 作为唯一数据源。PHP Payload DTO 类由 CI 从 OpenAPI schema 自动生成并提交到代码库,前端 TypeScript 类型继续从同一 schema 生成。这消除了运行时 DI 容器扫描 OpenAPI 注解的需求,同时保持前后端类型自动同步。

### Problem Frame

当前 12 个 Payload DTO 类使用 PHP 8 `#[OA\Schema]` 注解,CI 通过 `./vendor/bin/openapi` 扫描这些注解生成 `swagger.json`,前端脚本再将其转换为 TypeScript 类型。问题在于 Hyperf 的 DI 容器在启动时反射扫描所有 PHP 属性,当 `zircote/swagger-php` 包不存在时,加载 `OpenApi\Attributes` 类失败导致应用崩溃。

添加该包到 `composer.json` 可修复运行时,但它是一个仅用于构建的工具,不应成为生产依赖。更深层的问题是注解职责混淆:它们既承担静态分析(类型生成)又被运行时评估(DI 扫描),导致构建时工具泄漏到运行时环境。

### Requirements

**Schema 管理**

- R1. OpenAPI schema 以单个 YAML 文件形式存在于 `backend/openapi/schemas.yaml`,包含所有 Payload DTO 的完整类型定义(属性、类型、必填字段、描述)。
- R2. Schema 文件手工维护,使用标准 OpenAPI 3.0+ 格式,支持 `$ref` 引用以复用通用类型片段。

**PHP DTO 生成**

- R3. PHP DTO 类由 CI 工作流从 `schemas.yaml` 生成,输出到 `backend/app/Service/Agent/Outcome/Payload/Generated/` 目录。
- R4. 生成的 DTO 类包含:公共只读属性、构造函数、`toArray()` 和静态 `fromArray()` 方法、基本类型验证(非空检查)。
- R5. 生成的 PHP 文件提交到代码库(不是临时构建产物),便于代码审查和本地开发无需重新生成。
- R6. CI 验证已提交的生成文件与当前 schema 一致,schema 更改但未重新生成时构建失败。

**TypeScript 类型生成**

- R7. 前端 TypeScript 类型继续通过现有流程生成:`schemas.yaml` → `swagger.json` → `frontend/src/types/api.ts`。
- R8. CI 工作流在生成 PHP 后执行 `./vendor/bin/openapi backend/openapi/schemas.yaml --output storage/swagger.json --format json`,前端脚本读取该文件生成 TypeScript 类型。

**迁移与兼容性**

- R9. 现有 12 个手写 Payload DTO 类的 `#[OA\Schema]` 注解移除,类定义迁移到 `schemas.yaml`,生成的类替代原手写类。
- R10. 生成的类命名空间和类名与原手写类保持一致,确保现有代码引用无需修改。
- R11. 若现有 DTO 包含自定义逻辑(如复杂验证、计算属性),将其提取为独立 Trait 或辅助类,生成的 DTO 保持纯数据结构。

### Key Decisions

**生成的 PHP 文件作为提交的代码库工件而非临时构建产物。** Governs R5, R6。理由:保持本地开发流程简单(无需每次 composer install 重新生成),代码审查可见生成的代码变更,避免生成工具版本差异导致的不确定性。

**CI 验证而非本地 pre-commit 钩子强制重新生成。** Governs R6。理由:避免减慢本地开发速度,开发者可手动运行生成命令,CI 作为最终检查点。

**单一 `schemas.yaml` 而非模块化文件。** Governs R1。理由:当前仅 12 个 DTO,单文件易于导航和全局搜索,可通过 YAML 锚点复用片段,未来扩展时可拆分但现在过早优化。

### Actors

- **开发者** — 修改 schema 定义、运行生成命令、提交代码
- **CI 系统** — 自动生成 PHP DTO 和 swagger.json、验证一致性、运行测试
- **前端构建** — 从 swagger.json 生成 TypeScript 类型

### Key Flows

- F1. 开发者添加新 Payload DTO
  - **Trigger:** 新 Agent outcome 需要新数据结构
  - **Actors:** 开发者, CI
  - **Steps:**
    1. 开发者在 `schemas.yaml` 添加新 schema 定义
    2. 本地运行 `composer generate:dtos` 生成 PHP 类
    3. 提交 schema 和生成的 PHP 文件
    4. CI 验证生成的文件与 schema 一致,生成 swagger.json
    5. 前端 CI 从 swagger.json 生成 TypeScript 类型
  - **Covered by:** R1, R3, R5, R6, R7, R8

- F2. 开发者修改现有 Payload 结构
  - **Trigger:** 业务需求变更需要调整字段
  - **Actors:** 开发者, CI
  - **Steps:**
    1. 开发者更新 `schemas.yaml` 中对应 schema
    2. 本地重新运行 `composer generate:dtos`
    3. 代码审查时可见 schema 变更和生成代码的 diff
    4. CI 验证并更新 swagger.json
    5. 前端类型自动同步
  - **Covered by:** R1, R3, R5, R6, R7, R8

- F3. CI 检测到 schema 与生成文件不一致
  - **Trigger:** PR 修改了 schema 但未重新生成
  - **Actors:** CI
  - **Steps:**
    1. CI 从 schema 重新生成 PHP 文件到临时目录
    2. 对比临时生成文件与已提交文件
    3. 发现差异则构建失败,输出 diff
    4. 开发者本地重新生成并提交
  - **Covered by:** R6

### Acceptance Examples

- AE1. **新增 DTO 端到端验证**
  - **Covers:** R1, R3, R4, R5, R7, R8
  - **Given:** `schemas.yaml` 中新增 `NewPayload` schema,包含 `id: string`、`data: object` 字段
  - **When:** 开发者运行 `composer generate:dtos` 并提交,CI 运行,前端脚本运行
  - **Then:**
    - 生成的 `backend/app/Service/Agent/Outcome/Payload/Generated/NewPayload.php` 包含只读属性 `$id` 和 `$data`
    - 包含 `toArray()` 和 `fromArray()` 方法
    - CI 验证通过
    - `frontend/src/types/api.ts` 包含对应 TypeScript 接口 `NewPayload`

- AE2. **Schema 未重新生成时 CI 失败**
  - **Covers:** R6
  - **Given:** PR 修改了 `schemas.yaml` 中某 schema 的字段名,但未重新生成 PHP 文件
  - **When:** CI 运行验证步骤
  - **Then:**
    - CI 重新生成临时文件并对比
    - 构建失败,输出 "Generated DTOs out of sync with schemas.yaml, run `composer generate:dtos`"
    - 显示 diff

- AE3. **现有 DTO 迁移后功能不变**
  - **Covers:** R9, R10, R11
  - **Given:** 原 `TitleCandidates` 类使用 `#[OA\Schema]` 注解,包含 `toArray()` 方法
  - **When:** 迁移到生成流程:schema 定义在 `schemas.yaml`,生成新类,移除注解
  - **Then:**
    - 生成的类命名空间仍为 `App\Service\Agent\Outcome\Payload\TitleCandidates`
    - 现有代码 `use App\Service\Agent\Outcome\Payload\TitleCandidates;` 无需修改
    - `toArray()` 方法行为与原手写版本一致
    - Hyperf DI 容器启动不再尝试加载 `OpenApi\Attributes`

### Success Criteria

- **技术指标**
  - CI 工作流中 PHP DTO 生成和 TypeScript 类型生成全部自动化,无手工步骤
  - 所有 Payload DTO 相关测试通过,包括序列化/反序列化
  - `composer.json` 不包含 `zircote/swagger-php` 作为运行时依赖(仅 `require-dev`)
  - Hyperf 应用在 PHP 8.1、8.2、8.4 环境下启动成功,DI 容器无注解加载错误

- **开发者体验**
  - Schema 变更后运行单一命令 `composer generate:dtos` 即可完成 PHP 类生成
  - 代码审查中可直接查看生成文件的 diff,无需本地重新生成才能理解变更影响
  - 本地开发无需安装额外工具或运行生成脚本,拉取代码即可运行

### Scope Boundaries

**包含在本计划中:**
- Agent Outcome Payload DTO 的 12 个类迁移到生成流程
- CI 工作流自动化生成和验证
- 前端 TypeScript 类型生成流程调整以使用新 schema 源

**不包含在本计划中:**
- 其他 API 端点的 OpenAPI 文档(如 HTTP Controller 的路由注解)仍使用现有方式
- 现有手写 DTO 类的验证逻辑增强或重构
- OpenAPI schema 的版本管理或变更历史追踪(可作为后续改进)
- 生成工具本身的开发(使用现有成熟工具如 `jane-php/open-api` 或自定义脚本)

### Outstanding Questions

**Resolve Before Planning:**

- OQ1. 使用何种 PHP 代码生成工具?选项:
  - `jane-php/open-api`(成熟但依赖较重)
  - `openapi-generator-cli` PHP 生成器(需 Java 运行时)
  - 自定义 Composer 脚本 + Twig 模板(轻量但需维护模板)
  
- OQ2. 生成的 PHP 类是否需要实现接口或继承基类以保持与现有代码的契约?

- OQ3. 现有 12 个 DTO 中是否存在无法直接从 schema 生成的自定义逻辑(复杂验证、计算属性、类型转换)?需审查每个类确认。
