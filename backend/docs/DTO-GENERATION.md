# DTO 自动生成系统

本项目使用 OpenAPI Schema + PHP 代码生成器实现 DTO 类的自动生成，确保前端类型定义与后端数据结构的同步。

## 架构概述

```
openapi/schemas/*.yaml  →  bin/generate-dtos.php  →  app/DTO/*.php
                                    ↓
                          templates/dto.php.twig
```

### 文件组织

- `openapi/schemas/` - OpenAPI Schema 定义（按业务模块组织）
- `app/DTO/` - 自动生成的 DTO 类（**禁止手动修改**）
- `templates/dto.php.twig` - PHP DTO 生成模板
- `bin/generate-dtos.php` - 代码生成器脚本

## 快速开始

### 生成所有 DTO

```bash
composer run generate:dto
```

### 添加新 DTO

1. 在 `openapi/schemas/<module>.yaml` 中定义 Schema：

```yaml
components:
  schemas:
    UserProfile:
      description: 用户资料
      type: object
      required:
        - user_id
        - username
      properties:
        user_id:
          type: integer
          description: 用户 ID
          example: 12345
        username:
          type: string
          description: 用户名
          example: "johndoe"
        email:
          type: string
          format: email
          nullable: true
          description: 电子邮箱
          example: "john@example.com"
```

2. 运行生成器：

```bash
composer run generate:dto
```

3. 生成的 DTO 将包含：
   - 类型安全的构造函数
   - `toArray()` 序列化方法
   - `fromArray()` 反序列化方法
   - `validate()` 静态验证方法
   - 完整的 PHPDoc 注释

## Schema 规范

### 支持的类型

| OpenAPI 类型 | PHP 类型 | 示例 |
|--------------|----------|------|
| `string` | `string` | `"hello"` |
| `integer` | `int` | `42` |
| `number` | `float` | `3.14` |
| `boolean` | `bool` | `true` |
| `array` | `array` | `[1, 2, 3]` |
| `object` (additionalProperties) | `array` | `["key" => "value"]` |

### 必需字段 vs 可选字段

```yaml
properties:
  required_field:
    type: string
  optional_field:
    type: string
    nullable: true    # 可为 null
required:
  - required_field    # 构造函数必需参数
```

### 数组和嵌套对象

```yaml
tags:
  type: array
  items:
    type: string
  example: ["技术", "AI"]

metadata:
  type: object
  additionalProperties:
    type: string
  example: {"author": "Alice", "version": "1.0"}
```

### 数值约束

```yaml
age:
  type: integer
  minimum: 0
  maximum: 150
```

## 生成的代码结构

每个 DTO 类包含：

```php
namespace App\DTO;

/**
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 * @see openapi/schemas/<module>.yaml
 */
class UserProfile
{
    public function __construct(
        public int $user_id,
        public string $username,
        public ?string $email = null,
    ) {}

    public function toArray(): array { /* ... */ }
    
    public static function fromArray(array $data): static { /* ... */ }
    
    public static function validate(array $data): void { /* ... */ }
}
```

## CI 集成

GitHub Actions 会在每次 Push/PR 时自动验证：
- OpenAPI schemas 格式正确
- 生成的 DTO 与 schema 同步

## 注意事项

### 1. 生成文件标记

所有生成的 DTO 都包含 `@generated` 注释：

```php
/**
 * @generated 此文件由 bin/generate-dtos.php 自动生成,请勿手动修改
 */
```

**绝对不要手动修改这些文件**，因为下次生成会覆盖你的修改。

### 2. 与运行时 Payload DTO 的区别

- **app/DTO/** - 用于前端类型生成和 API 文档的 Schema DTO
- **app/Service/Agent/Outcome/Payload/** - 运行时业务逻辑使用的 Payload DTO

这两个系统目前是独立的。`app/DTO/` 中的类主要用于：
- OpenAPI 文档生成
- 前端 TypeScript 类型定义生成
- API 契约定义

### 3. 模块化组织

按业务领域将 schemas 拆分到不同文件：

```
openapi/schemas/
├── content.yaml      # 内容相关 DTO
├── images.yaml       # 图片相关 DTO
├── outline.yaml      # 大纲相关 DTO
├── reflection.yaml   # 反思相关 DTO
└── title.yaml        # 标题相关 DTO
```

## 故障排查

### 生成失败：PHP 语法错误

检查 Schema 中的：
- `required` 字段是否存在于 `properties` 中
- `example` 值是否符合类型定义
- 特殊字符是否正确转义

### DTO 类未生成

确保：
1. Schema 文件放在 `openapi/schemas/` 目录
2. 文件扩展名是 `.yaml`
3. YAML 格式正确（使用 yamllint 验证）

### 类型不匹配

如果 PHP 类型与 OpenAPI 类型不符：
1. 检查 `type` 字段拼写
2. 确认 `nullable: true` 是否正确设置
3. 对于复杂类型，检查 `items` 或 `additionalProperties` 定义

## 开发工作流

### 添加新功能的 DTO

1. 在对应的 `openapi/schemas/<module>.yaml` 中定义
2. 运行 `composer run generate:dto`
3. 提交 schema 文件和生成的 DTO
4. CI 自动验证

### 修改现有 DTO

1. 修改 `openapi/schemas/<module>.yaml`
2. 重新运行生成器
3. 检查 `git diff` 确认变更符合预期
4. 提交

## 参考资料

- [OpenAPI 3.0 Specification](https://swagger.io/specification/)
- [JSON Schema Validation](https://json-schema.org/)
- [Twig Template Documentation](https://twig.symfony.com/doc/)
