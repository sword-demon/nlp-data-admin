# 原型决策记录

## 核心决策

### 1. 采用自定义生成器方案

**决定**：使用自定义 PHP 脚本 + Twig 模板生成 DTO，而非 jane-php/openapi-generator 等现成工具。

**理由**：
- 现有工具生成 HTTP 客户端风格代码，不符合项目的简单 DTO 模式
- 项目只需要 DTO（数据传输对象），不需要 HTTP 客户端功能
- 自定义方案无运行时依赖，现成工具会引入大量库
- 完全控制代码风格，与现有代码 100% 一致

**验证结果**：8/8 测试通过，生成代码与手写代码风格一致。

### 2. 代码风格特征

生成的 DTO 必须包含以下特征（已验证）：

- `declare(strict_types=1)`
- `final class` 定义
- `readonly` 属性
- 命名构造器参数
- `toArray(): array` 方法
- `static fromArray(array $data): self` 工厂方法
- 验证逻辑（如 `minItems`）抛出 `InvalidArgumentException`
- 嵌套对象通过递归调用 `fromArray()` 构造
- PHPDoc 类型注释（可选，但推荐）

### 3. 不包含 OpenAPI 注解

**决定**：生成的 DTO 类不包含 `OpenApi\Attributes` 注解。

**理由**：
- OpenAPI 注解是为了从 PHP 代码生成 OpenAPI schema（反向）
- 我们的方向是从 OpenAPI schema 生成 PHP 代码（正向）
- 包含注解会引入 `zircote/swagger-php` 运行时依赖
- OpenAPI schema 已是单一数据源，无需在代码中重复定义

### 4. 技术栈

**依赖**（仅 dev 环境）：
- `symfony/yaml` ^7.0 - 读取 OpenAPI YAML
- `twig/twig` ^3.0 - 模板渲染

**生成器架构**：
```
schemas.yaml → PHP 脚本（symfony/yaml）→ Twig 模板 → 生成的 DTO 类
```

### 5. 验证的 OpenAPI 特性支持

已验证支持：
- [x] 基本类型：string, integer, boolean
- [x] 嵌套对象：`$ref` 引用
- [x] 对象数组：`items: {$ref: '...'}`
- [x] 可空字段：`nullable: true`
- [x] 验证约束：`minItems`, `required`
- [x] 默认值处理：`fromArray()` 中的 `?? []`

未验证（待扩展）：
- [ ] enum 类型
- [ ] oneOf/anyOf/allOf
- [ ] 格式约束：`format: date`, `pattern`, `minLength`
- [ ] 数字约束：`minimum`, `maximum`

**策略**：优先支持 80% 常见场景，复杂情况允许手写覆盖。

## 实现路径

### Phase 1: 核心生成器（已完成原型）

- [x] 读取 OpenAPI schema YAML
- [x] Twig 模板生成基本 DTO
- [x] 支持嵌套对象和数组
- [x] 支持 nullable 字段
- [x] 支持 minItems 验证
- [x] 验证生成代码可运行

### Phase 2: 集成到项目（待实施）

1. **在 backend 目录添加依赖**：
   ```bash
   composer require --dev symfony/yaml:^7.0 twig/twig:^3.0
   ```

2. **创建 OpenAPI schema 文件**：
   - 路径：`backend/openapi/schemas.yaml`
   - 提取现有 DTO 的 schema 定义

3. **移植生成器脚本**：
   - 从原型目录复制 `generate.php` 和 `dto.php.twig`
   - 放置到 `backend/bin/` 和 `backend/templates/`

4. **配置 Composer 脚本**：
   ```json
   {
       "scripts": {
           "generate:dtos": "php bin/generate-dtos.php"
       }
   }
   ```

5. **集成到 CI**：
   ```yaml
   - name: 生成并验证 DTOs
     run: |
       cd backend
       composer generate:dtos
       git diff --exit-code app/Service/Agent/Outcome/Payload/Generated/
   ```

### Phase 3: 迁移现有 DTO（待实施）

1. 为每个现有 DTO 编写 OpenAPI schema
2. 生成新 DTO 到 `Generated/` 命名空间
3. 更新引用，替换手写 DTO
4. 删除手写 DTO 文件
5. 移除 `zircote/swagger-php` 依赖（如果仅用于 DTO）

## 风险与缓解

| 风险 | 缓解措施 | 状态 |
|------|---------|------|
| 模板维护成本 | 保持模板简单，覆盖常见场景即可 | ✅ 已简化 |
| OpenAPI 特性支持不全 | 逐步扩展，复杂情况允许手写覆盖 | ✅ 已规划 |
| 生成代码质量 | CI 中对比测试，验证序列化一致性 | ✅ 已验证 |
| 开发者学习成本 | 提供详细文档和示例 | ⏳ 待完成 |

## 待定问题

### 1. Schema 文件组织

**选项 A**：单一 YAML 文件
```yaml
# backend/openapi/schemas.yaml
components:
  schemas:
    TitleCandidate: { ... }
    TitleCandidates: { ... }
    OutlineNode: { ... }
```

**选项 B**：模块化文件
```
backend/openapi/
  schemas/
    title-candidate.yaml
    title-candidates.yaml
    outline-node.yaml
  openapi.yaml  # 通过 $ref 引用
```

**倾向**：选项 A（简单），随着 schema 增长再考虑拆分。

### 2. 生成目录

**选项 A**：单独的 `Generated/` 命名空间
- 路径：`app/Service/Agent/Outcome/Payload/Generated/`
- 优点：明确标识生成代码，避免意外修改
- 缺点：引用路径更长

**选项 B**：原地生成
- 路径：与手写 DTO 同级
- 优点：引用路径更短
- 缺点：难以区分生成代码和手写代码

**倾向**：选项 A（Generated/ 命名空间）

### 3. CI 验证策略

**选项 A**：严格模式（git diff 检查）
```bash
php bin/generate-dtos.php
git diff --exit-code app/Service/Agent/Outcome/Payload/Generated/
```
- 优点：强制开发者提交生成的代码
- 缺点：每次 schema 变更都需要本地重新生成

**选项 B**：自动提交模式
- CI 自动运行生成器并提交
- 优点：开发者无需手动生成
- 缺点：CI 需要 git 写权限

**倾向**：选项 A（严格模式），保持简单。

## 下一步行动

如果用户选择应用原型决策，推荐：

1. **立即行动**：运行 `/ce-plan` 制定详细实施计划
   - 任务分解：依赖安装、生成器集成、CI 配置、迁移现有 DTO
   - 时间估算：2-3 天（含测试和文档）

2. **可选行动**：先用 `/ce-brainstorm` 讨论待定问题
   - Schema 文件组织
   - 生成目录位置
   - CI 验证策略

---

**原型路径**: `.context/compound-engineering/ce-prototype/2026-10-15-openapi-dto-generator/`  
**验证结果**: 8/8 测试通过 ✅
