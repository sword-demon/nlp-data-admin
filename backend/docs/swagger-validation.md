# Swagger 注解验证文档

## 已补充注解的 DTO 清单

### 核心 Payload DTO (7 个)

1. **ResearchBundle** - 选题研究资料包
   - 字段: topic, provider, queried_at, summary, sources[], fallback
   - 嵌套对象: sources 数组包含 {title, url, snippet}

2. **TitleCandidate** - 单个标题候选项
   - 字段: title, analysis, score (1-10)

3. **TitleCandidates** - 标题候选集
   - 字段: titles[] (TitleCandidate 数组)
   - 不变量: 至少 1 个标题

4. **OutlineNode** - 大纲节点
   - 字段: id, text, level, parent_id
   - parent_id 可为 null (顶层节点)

5. **OutlineDraft** - 大纲草稿
   - 字段: markdown, nodes[] (OutlineNode 数组)
   - 不变量: markdown 非空 && nodes 非空

6. **ContentDraft** - 正文草稿
   - 字段: content, placeholders[] (Placeholder 数组), word_count

7. **ReflectionPayload** - 评审结果
   - 字段: scores (object), suggestions[] (string 数组), average_score
   - scores 包含 5 个维度: logic, fact, language, title, structure

### 辅助 DTO (5 个)

8. **Placeholder** - 配图占位符
   - 字段: id, index, keyword

9. **ImageAnalysis** - 单个配图分析
   - 字段: placeholder_id, context, keywords[], suggested_type, reasoning

10. **ImageAnalyses** - 配图分析集
    - 字段: analyses[] (ImageAnalysis 数组)

11. **ImageSlot** - 已生成配图槽位
    - 字段: placeholder_id, type, source, url, original_url, keyword, alt, attribution, mime, raw, is_placeholder

12. **GeneratedImages** - 已生成配图集
    - 字段: images[] (ImageSlot 数组)

## 注解特性

### Schema 级别注解
- 所有 DTO 都添加了 `#[OA\Schema]` 注解
- 包含 schema 名称、描述、类型和必填字段列表

### Property 级别注解
- 所有公共属性都添加了 `#[OA\Property]` 注解
- 包含属性名、描述、类型、示例值
- 枚举字段使用 `enum` 参数限定可选值
- 数值字段使用 `minimum`/`maximum` 限定范围
- 可空字段使用 `nullable: true`

### 嵌套引用
- 数组字段使用 `OA\Items` 定义元素类型
- 引用其他 Schema 使用 `ref: '#/components/schemas/SchemaName'`
- 示例: TitleCandidates.titles 引用 TitleCandidate

## 生成 OpenAPI Spec 的步骤

### 1. 安装依赖
```bash
cd backend
composer require hyperf-plus/swagger --dev
# 或
composer require zircote/swagger-php --dev
```

### 2. 生成 swagger.json
```bash
# 如果使用 hyperf-plus/swagger
php bin/hyperf.php swagger:generate

# 或使用 zircote/swagger-php
./vendor/bin/openapi \
  --output storage/swagger.json \
  --format json \
  app/Service/Agent/Outcome/Payload \
  app/Controller
```

### 3. 验证生成结果
```bash
# 检查文件是否生成
test -f backend/storage/swagger.json && echo "✅ swagger.json generated"

# 验证 schema 数量 (应该 >= 12)
jq '.components.schemas | keys | length' backend/storage/swagger.json

# 查看所有 schema 名称
jq '.components.schemas | keys' backend/storage/swagger.json

# 验证 TitleCandidates 的必填字段
jq '.components.schemas.TitleCandidates.required' backend/storage/swagger.json

# 验证 ReflectionPayload 的 scores 字段定义
jq '.components.schemas.ReflectionPayload.properties.scores' backend/storage/swagger.json
```

## 测试场景

### 场景 1: Schema 完整性
**期望**: swagger.json 包含 12 个 components/schemas
```bash
SCHEMA_COUNT=$(jq '.components.schemas | keys | length' backend/storage/swagger.json)
test $SCHEMA_COUNT -ge 12 && echo "✅ Pass" || echo "❌ Fail: only $SCHEMA_COUNT schemas"
```

### 场景 2: 字段类型正确
**期望**: TitleCandidate.score 类型为 integer
```bash
SCORE_TYPE=$(jq -r '.components.schemas.TitleCandidate.properties.score.type' backend/storage/swagger.json)
test "$SCORE_TYPE" = "integer" && echo "✅ Pass" || echo "❌ Fail: type is $SCORE_TYPE"
```

### 场景 3: 嵌套对象引用
**期望**: TitleCandidates.titles 正确引用 TitleCandidate
```bash
TITLES_REF=$(jq -r '.components.schemas.TitleCandidates.properties.titles.items["$ref"]' backend/storage/swagger.json)
test "$TITLES_REF" = "#/components/schemas/TitleCandidate" && echo "✅ Pass" || echo "❌ Fail: ref is $TITLES_REF"
```

### 场景 4: 必填字段标记
**期望**: OutlineDraft 的 required 包含 markdown 和 nodes
```bash
REQUIRED=$(jq -r '.components.schemas.OutlineDraft.required | @json' backend/storage/swagger.json)
echo "$REQUIRED" | grep -q "markdown" && echo "$REQUIRED" | grep -q "nodes" && echo "✅ Pass" || echo "❌ Fail: $REQUIRED"
```

### 场景 5: Example 示例值
**期望**: 每个 string/number 字段包含 example
```bash
TITLE_EXAMPLE=$(jq -r '.components.schemas.TitleCandidate.properties.title.example' backend/storage/swagger.json)
test -n "$TITLE_EXAMPLE" && echo "✅ Pass: $TITLE_EXAMPLE" || echo "❌ Fail: no example"
```

### 场景 6: Enum 枚举验证
**期望**: ImageAnalysis.suggested_type 包含 enum: ['photo', 'illustration', 'diagram']
```bash
ENUM_VALUES=$(jq -r '.components.schemas.ImageAnalysis.properties.suggested_type.enum | @json' backend/storage/swagger.json)
echo "$ENUM_VALUES" | grep -q "photo" && echo "$ENUM_VALUES" | grep -q "illustration" && echo "✅ Pass" || echo "❌ Fail: $ENUM_VALUES"
```

## 已知限制

1. **ResearchBundle.sources** 是动态数组，内部结构未强类型化 (使用 inline object 定义)
2. **ImageSlot.raw** 是 mixed 类型，无法精确定义 schema
3. **PHP 8.1+ Attributes** 语法要求 - 旧版本需使用注释式注解

## 后续步骤

1. 安装 OpenAPI 生成工具
2. 执行生成命令
3. 运行上述 6 个测试场景
4. 如果测试通过，继续前端类型生成 (U12)
