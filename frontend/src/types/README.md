# 前端类型定义

## 📁 文件说明

- **api.ts** - 自动生成的 API 类型定义 (由 `npm run generate:types` 生成,**请勿手动修改**)

## 🔧 使用方法

### 1. 生成类型

```bash
cd frontend
npm run generate:types
```

### 2. 导入类型

```typescript
import type { components } from '@/types/api'

// 使用 Payload 类型
type TitleCandidate = components['schemas']['TitleCandidate']
type TitleCandidates = components['schemas']['TitleCandidates']
type OutlineDraft = components['schemas']['OutlineDraft']
type ReflectionPayload = components['schemas']['ReflectionPayload']

// 示例: 定义函数参数
function processTitles(titles: TitleCandidates): void {
  titles.titles.forEach((candidate: TitleCandidate) => {
    console.log(`${candidate.title} (评分: ${candidate.score})`)
  })
}
```

### 3. 与现有代码集成

```typescript
// 旧代码 (手动定义)
export interface TitleCandidate {
  title: string
  analysis: string
  score: number
}

// 新代码 (从 api.ts 导入)
import type { components } from '@/types/api'

export type TitleCandidate = components['schemas']['TitleCandidate']
```

## 🔄 自动更新机制

当后端 Payload DTO 发生变更时:

1. **开发环境**: 运行 `npm run generate:types` 手动更新
2. **CI 环境**: GitHub Actions 自动检测变更并提交更新

## ⚠️ 注意事项

1. **api.ts 自动生成** - 不要手动修改,否则下次生成时会被覆盖
2. **类型引用路径** - 统一使用 `@/types/api` 导入
3. **类型别名** - 为方便使用,可在其他文件中定义类型别名
4. **依赖后端** - 需要后端先生成 `backend/storage/swagger.json`

## 📖 相关文档

- [U12 实现计划](../../../docs/u12-frontend-type-generation.md)
- [Swagger 注解验证](../../../backend/docs/swagger-validation.md)
- [openapi-typescript 文档](https://github.com/drwpow/openapi-typescript)
