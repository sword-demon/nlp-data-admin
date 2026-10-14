# U12 前端类型生成 - 实现文档

**创建日期**: 2026-10-14  
**状态**: ✅ 实现完成  
**关联 Issue**: #13

---

## 📋 实现概述

实现从后端 OpenAPI spec 自动生成前端 TypeScript 类型定义,并集成到 CI 流程,实现前后端类型零漂移。

## 🎯 目标

- ✅ 本地生成类型脚本
- ✅ npm scripts 集成
- ✅ GitHub Actions CI 自动提交
- ✅ TypeScript 编译验证
- ✅ 类型完整性检查

## 📁 新增文件

### 1. 类型生成脚本

**文件**: `frontend/scripts/generate-types.sh`

**功能**:
- 检查 `backend/storage/swagger.json` 是否存在
- 使用 `openapi-typescript` 生成类型定义
- 验证关键类型是否完整
- 输出生成统计信息

**使用**:
```bash
cd frontend
npm run generate:types
```

**输出**: `frontend/src/types/api.ts`

### 2. CI 工作流

**文件**: `.github/workflows/ci.yml`

**触发条件**:
- Payload DTO 文件变更 (`backend/app/Service/Agent/Outcome/Payload/**`)
- Swagger 配置变更 (`backend/config/autoload/swagger.php`)
- 手动触发 (`workflow_dispatch`)

**工作流步骤**:
1. 检出代码
2. 设置 PHP 环境 (8.1)
3. 安装 PHP 依赖
4. 生成 `swagger.json`
5. 验证 schema 数量 (>= 7)
6. 设置 Node.js 环境 (20)
7. 安装前端依赖
8. 生成 TypeScript 类型
9. 运行 TypeScript 编译验证
10. 检查文件变更
11. 自动提交并推送 (如果有变更)

### 3. 类型使用文档

**文件**: `frontend/src/types/README.md`

**内容**:
- 文件说明
- 使用方法示例
- 自动更新机制
- 注意事项

## 📦 依赖更新

### frontend/package.json

**新增脚本**:
```json
"scripts": {
  "generate:types": "bash scripts/generate-types.sh"
}
```

**新增依赖**:
```json
"devDependencies": {
  "openapi-typescript": "^7.4.3"
}
```

## ✅ 测试场景

### 场景 1: 本地生成类型

**步骤**:
```bash
cd frontend
npm run generate:types
```

**预期结果**:
- ✅ 生成 `frontend/src/types/api.ts`
- ✅ 包含 7+ 个 Payload 类型
- ✅ 无错误输出

**实际结果**: ⚠️ 需要后端先生成 `swagger.json`

### 场景 2: 类型导入

**测试代码**:
```typescript
import type { components } from '@/types/api'

type TitleCandidate = components['schemas']['TitleCandidate']

const candidate: TitleCandidate = {
  title: 'AI 技术',
  analysis: '分析内容',
  score: 8
}
```

**预期结果**:
- ✅ IDE 自动补全
- ✅ 类型检查通过

### 场景 3: TypeScript 编译

**步骤**:
```bash
cd frontend
npm run build
```

**预期结果**:
- ✅ 编译成功
- ✅ 0 个类型错误

### 场景 4: 类型完整性

**验证脚本** (在 `generate-types.sh` 中):
```bash
REQUIRED_TYPES=(
  "TitleCandidate"
  "TitleCandidates"
  "OutlineNode"
  "OutlineDraft"
  "ContentDraft"
  "ReflectionPayload"
  "ResearchBundle"
)
```

**预期结果**:
- ✅ 所有 7 个核心类型都存在

### 场景 5: 嵌套类型展开

**验证**:
```typescript
type TitleCandidates = components['schemas']['TitleCandidates']

// titles 应该是 TitleCandidate[] 类型
const titles: TitleCandidates['titles'] = [
  { title: 'a', analysis: 'b', score: 8 }
]
```

**预期结果**:
- ✅ 类型正确引用
- ✅ 数组元素类型为 `TitleCandidate`

### 场景 6: CI 自动提交

**触发方式**:
1. 修改任意 Payload DTO 文件
2. 推送到 GitHub
3. 等待 CI 执行

**预期结果**:
- ✅ CI 自动生成 `api.ts`
- ✅ 自动提交并推送
- ✅ commit message: `chore(types): 自动生成前端类型定义`

## 🔧 技术细节

### openapi-typescript

**版本**: 7.4.3

**生成命令**:
```bash
npx openapi-typescript backend/storage/swagger.json -o frontend/src/types/api.ts
```

**生成格式**:
```typescript
export interface components {
  schemas: {
    TitleCandidate: {
      title: string
      analysis: string
      score: number
    }
    TitleCandidates: {
      titles: components['schemas']['TitleCandidate'][]
    }
    // ...
  }
}
```

### GitHub Actions 权限

使用内置 `GITHUB_TOKEN`,具有以下权限:
- `contents: write` - 写入文件
- `pull-requests: write` - 创建 PR (可选)

**配置** (在 `.github/workflows/ci.yml`):
```yaml
permissions:
  contents: write
```

## 📊 验收标准对照

根据 Issue #13:

- [x] **类型生成脚本实现** - `frontend/scripts/generate-types.sh`
- [x] **CI 集成自动提交** - `.github/workflows/ci.yml`
- [ ] **前端 TypeScript 编译 0 错误** - 需要运行时验证
- [ ] **6 个测试场景通过** - 场景 1-5 代码层面完成,场景 6 需 CI 环境

## 🚧 限制与后续

### 当前限制

1. **无法生成 swagger.json** - 需要 PHP 环境
2. **无法执行 CI** - 需要推送到 GitHub
3. **无法验证类型导入** - 需要 Node.js 环境

### 手动验证步骤

当具备运行环境时:

```bash
# 1. 生成 swagger.json
cd backend
./vendor/bin/openapi --output storage/swagger.json --format json app/Service/Agent/Outcome/Payload

# 2. 生成前端类型
cd ../frontend
npm install
npm run generate:types

# 3. 验证 TypeScript 编译
npm run build

# 4. 检查生成的类型
cat src/types/api.ts | grep -E "TitleCandidate|OutlineDraft|ReflectionPayload"
```

### CI 首次运行建议

```bash
# 1. 推送代码触发 CI
git add .
git commit -m "feat(u12): 实现前端类型自动生成"
git push

# 2. 观察 CI 日志
# 访问 https://github.com/sword-demon/nlp-data-admin/actions

# 3. 验证自动提交
git pull
cat frontend/src/types/api.ts
```

## 🎉 完成标志

- [x] 脚本文件创建
- [x] CI 配置创建
- [x] package.json 更新
- [x] 文档完整
- [ ] 运行时验证 (需要环境)
- [ ] CI 首次成功运行 (需要 GitHub)

## 📖 参考资料

- [openapi-typescript GitHub](https://github.com/drwpow/openapi-typescript)
- [GitHub Actions - setup-php](https://github.com/shivammathur/setup-php)
- [OpenAPI 3.0 Specification](https://swagger.io/specification/)
- [TypeScript Handbook - Type Manipulation](https://www.typescriptlang.org/docs/handbook/2/types-from-types.html)

## 🔗 相关文件

- 脚本: [frontend/scripts/generate-types.sh](../frontend/scripts/generate-types.sh)
- CI: [.github/workflows/ci.yml](../.github/workflows/ci.yml)
- 文档: [frontend/src/types/README.md](../frontend/src/types/README.md)
- U11 文档: [swagger-validation.md](../backend/docs/swagger-validation.md)
