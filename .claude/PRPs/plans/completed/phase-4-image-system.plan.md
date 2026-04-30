# Plan: Phase 4 — 配图系统

## Summary

实现 6 种配图方式的策略模式系统：Pexels 图库搜索、Mermaid 流程图生成、Iconify 图标检索、表情包搜索（Bing）、SVG 概念示意图、Nano Banana AI 生图。每种策略独立封装，通过 ParallelImageGenerator 协程并行调度，获取到的图片自动上传至阿里云 OSS/腾讯云 COS，替换 Phase 3 的占位符 mock。

## User Story

As a 内容创作者, I want 文章中自动配上合适的插图（照片/图表/图标/表情包/AI 生图）, So that 我无需手动找图，就能得到图文并茂的完整文章。

## Problem → Solution

Phase 3 仅生成配图占位符 `![配图:关键词](placeholder://image/N)` → 6 种配图策略通过策略模式各司其职，ParallelImageGenerator 并行调度获取真实图片 URL，上传 OSS/COS 后替换占位符。

## Metadata

- **Complexity**: Large
- **Source PRD**: `.claude/PRPs/prds/ai-multi-agent-content-platform.prd.md`
- **PRD Phase**: Phase 4 — 配图系统
- **Estimated Files**: 20+

---

## UX Design

N/A — 配图是后台自动过程，用户在前端 Phase 3 的进度面板中看到 "正在获取配图 (3/5)..." 即可。

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Contract/ModelProviderInterface.php` | 1-20 (plan) | 策略接口参考范式 |
| P0 | `backend/app/Service/Agent/ImageAnalyzerAgent.php` | 1-80 (plan) | 输入来源：分析后的配图需求 |
| P0 | `backend/app/Service/Agent/ParallelImageGenerator.php` | 1-50 (plan) | 待替换的 mock 实现 |
| P1 | `backend/app/Model/Article.php` | 1-50 | images JSON 字段更新 |
| P1 | `backend/app/Helpers/ApiResponse.php` | 1-55 | 统一响应格式 |
| P2 | `backend/.env` | 1-18 | 环境变量配置位置 |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| Pexels API | `https://www.pexels.com/api/` | `GET /v1/search?query=&orientation=&locale=zh-CN`，免费，需 API Key |
| Mermaid PHP | `JBZoo/Mermaid-PHP` | 链式构建流程图/ER/时序/类图，`renderHtml()` 输出 |
| Mermaid Ink | `https://mermaid.ink/` | `GET /img/{encoded}` 直接渲染为 PNG，适合 CDN 引用 |
| Iconify | `https://api.iconify.design/{prefix}/{icon}.svg` | 200k+ 图标，直接返回 SVG |
| 阿里云 OSS | `aliyuncs/oss-sdk-php` V2 | `PutObjectRequest` + `Uploader::uploadFile` |
| DashScope 万相 | `https://dashscope.aliyuncs.com/api/v1/services/aigc/multimodal-generation/generation` | wan2.6-image 模型，文生图 |

---

## Patterns to Mirror

Same as Phase 2/3 established patterns, plus:

### STRATEGY_PATTERN (new)
```php
// Each image source implements this interface
interface ImageStrategyInterface
{
    public function getName(): string;           // 'pexels', 'mermaid', etc.
    public function getLabel(): string;          // 'Pexels图库', '流程图', etc.
    public function supports(string $type): bool; // type from ImageAnalyzerAgent
    public function fetch(string $keyword, array $options = []): ImageResult;
}
```

### IMAGE_RESULT_DTO (new)
```php
readonly class ImageResult
{
    public function __construct(
        public string $source,      // pexels/mermaid/iconify/emoji/svg/nanobanana
        public string $url,         // final OSS/CDN URL
        public string $originalUrl, // original source URL (for attribution)
        public string $alt,         // alt text
        public ?string $attribution, // source attribution if required
    ) {}
}
```

### OSS_UPLOAD_PATTERN (new)
```php
class OssUploader
{
    public function uploadFromUrl(string $url, string $objectKey): string;
    public function uploadFromContent(string $content, string $objectKey, string $mimeType): string;
}
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/app/Contract/ImageStrategyInterface.php` | CREATE | 配图策略统一接口 |
| `backend/app/Dto/ImageResult.php` | CREATE | 配图结果 DTO |
| `backend/app/Service/ImageStrategy/PexelsStrategy.php` | CREATE | Pexels 图库搜索实现 |
| `backend/app/Service/ImageStrategy/MermaidStrategy.php` | CREATE | Mermaid 流程图生成实现 |
| `backend/app/Service/ImageStrategy/IconifyStrategy.php` | CREATE | Iconify 图标检索实现 |
| `backend/app/Service/ImageStrategy/EmojiStrategy.php` | CREATE | 表情包搜索实现 |
| `backend/app/Service/ImageStrategy/SvgConceptStrategy.php` | CREATE | SVG 概念示意图生成 |
| `backend/app/Service/ImageStrategy/NanoBananaStrategy.php` | CREATE | Nano Banana AI 生图实现 |
| `backend/app/Service/ImageStrategyFactory.php` | CREATE | 策略工厂 — 根据 type 返回策略实例 |
| `backend/app/Service/OssUploader.php` | CREATE | OSS/COS 上传封装 |
| `backend/app/Service/Agent/ParallelImageGenerator.php` | UPDATE | 替换 mock 为真实策略调度 |
| `backend/app/Service/WorkshopOrchestrator.php` | UPDATE | 接入真实配图流程 |
| `backend/config/autoload/image.php` | CREATE | 配图策略配置 |
| `backend/composer.json` | UPDATE | 添加 OSS SDK + Pexels 等依赖 |
| `backend/.env` | UPDATE | PEXELS/OSS/ICONIFY 等 Key |
| `backend/.env.example` | UPDATE | 同步模板 |
| `frontend/src/components/workshop/ImageProgress.vue` | CREATE | 配图进度展示 |
| `frontend/src/pages/Workshop.vue` | UPDATE | 集成配图进度 |

## NOT Building

- 图片本地缓存服务 — 直接用 OSS/CDN
- 图片编辑/裁剪功能 — OSS 图片处理按需配置
- 用户自定义上传配图 — 后续版本
- 图片质量审核/NSFW 检测 — MVP 依赖 API 自带审核
- 腾讯云 COS 实现 — 仅 OSS 实现，COS 接口保留扩展点

---

## Step-by-Step Tasks

### Task 1: 安装依赖并创建配置
- **ACTION**: 安装 OSS SDK + Pexels PHP 库，创建 `config/autoload/image.php`
- **IMPLEMENT**:
  - `composer require aliyuncs/oss-sdk-php:^2.7 devscast/pexels-php:^1.2`
  - `config/autoload/image.php`: 6 种策略的启用/禁用 + API Key 配置
  - 更新 `.env` 和 `.env.example`
- **MIRROR**: 参考 `config/autoload/model.php` 的 provider 配置结构
- **IMPORTS**: `aliyuncs/oss-sdk-php`, `devscast/pexels-php`
- **GOTCHA**: OSS SDK V2 需 V4 签名 (`OssClient::OSS_SIGNATURE_VERSION_V4`)，region 必填
- **VALIDATE**: `composer show aliyuncs/oss-sdk-php devscast/pexels-php` 显示版本

### Task 2: 创建 ImageStrategyInterface 和 ImageResult DTO
- **ACTION**: 定义策略契约和结果对象
- **IMPLEMENT**:
  - `ImageStrategyInterface`: `getName()`, `getLabel()`, `supports(string $type)`, `fetch(string $keyword, array $options)`
  - `ImageResult`: readonly DTO (source, url, originalUrl, alt, attribution)
- **MIRROR**: `App\Contract\ModelProviderInterface` 的接口定义风格；immutable DTO 模式
- **IMPORTS**: 无外部依赖
- **GOTCHA**: `supports()` 接收 `ImageAnalyzerAgent` 输出的 `suggested_type`，返回 bool
- **VALIDATE**: 语法检查通过

### Task 3: 创建 PexelsStrategy
- **ACTION**: Pexels 图库搜索策略
- **IMPLEMENT**:
  - 使用 `Devscast\Pexels\Client` 搜索照片
  - `fetch()`: 调用 `searchPhotos($keyword)`，取前 3 张
  - 选择最相关的一张返回 `ImageResult`，附带 photographer 归属
  - 支持 `orientation` 和 `locale=zh-CN` 选项
- **MIRROR**: 构造函数 DI 注入配置（参考各 Agent 类）
- **IMPORTS**: `Devscast\Pexels\Client`, `App\Contract\ImageStrategyInterface`
- **GOTCHA**:
  - Pexels API 限制：每月 200 次请求（免费），单次最多 80 条
  - 必须注明 photographer 归属（Pexels Guidelines）
  - 返回的 `src.large` 或 `src.original` 为原始 URL
- **VALIDATE**: `fetch('AI technology', [])` 返回带归属的真实图片 URL

### Task 4: 创建 MermaidStrategy
- **ACTION**: Mermaid 流程图生成策略
- **IMPLEMENT**:
  - 使用 `JBZoo\Mermaid-PHP` 构建流程图
  - `fetch()`: 根据 keyword 生成流程图（如 "AI 工作流程" → flowchart）
  - 通过 mermaid.ink API 转换为 PNG: `https://mermaid.ink/img/{base64Encode(mermaidCode)}`
  - 或直接输出 mermaid code block 嵌入 Markdown
- **MIRROR**: 与其他 Strategy 相同的 DI 模式
- **IMPORTS**: `JBZoo\Mermaid\*` 或直接 HTTP 调用 mermaid.ink
- **GOTCHA**:
  - Mermaid.ink 免费服务，但有并发限制
  - 更稳定方案：直接嵌入 ` ```mermaid ` markdown 代码块，前端用 mermaid.js 渲染
  - Phase 4 优先使用前端渲染方案（零服务器成本）
- **VALIDATE**: 生成 mermaid 代码块，前端渲染为流程图

### Task 5: 创建 IconifyStrategy
- **ACTION**: Iconify 图标检索策略
- **IMPLEMENT**:
  - 直接调用 Iconify API: `https://api.iconify.design/{prefix}/{icon}.svg`
  - 先搜索: `https://api.iconify.design/search?query={keyword}&limit=5`
  - 下载最匹配的 SVG，上传 OSS 或直接返回 Iconify CDN URL
- **MIRROR**: HTTP 客户端使用 Guzzle（`hyperf/guzzle` 已安装）
- **IMPORTS**: `GuzzleHttp\Client`
- **GOTCHA**:
  - Iconify 图标为 SVG 格式，适合文章中的小图标/示意图
  - 可直接使用 Iconify CDN URL 无需上传（`https://api.iconify.design/{prefix}/{icon}.svg`）
  - 搜索 API 无认证，有速率限制
- **VALIDATE**: 搜索 "AI" 图标返回 SVG URL

### Task 6: 创建 EmojiStrategy
- **ACTION**: 表情包搜索策略
- **IMPLEMENT**:
  - 使用免费 emoji API 或直接 Unicode emoji 映射
  - `fetch()`: 根据关键词匹配相关 emoji，返回大尺寸 emoji PNG
  - 备选方案: 使用 `https://emojihub.yurace.pro/api/all` 或类似免费 API
- **MIRROR**: Guzzle HTTP 调用模式
- **IMPORTS**: `GuzzleHttp\Client`
- **GOTCHA**:
  - 表情包版权问题：使用开源 emoji 集（如 Twemoji/Noto Emoji）
  - 实际可用 `https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/svg/{code}.svg`
- **VALIDATE**: 按关键词返回 emoji SVG URL

### Task 7: 创建 SvgConceptStrategy
- **ACTION**: SVG 概念示意图生成策略
- **IMPLEMENT**:
  - 调用 DashScope 模型生成 SVG 代码
  - Prompt: "根据以下概念生成一个简洁的 SVG 示意图: {keyword}。只输出 SVG 代码。"
  - 解析响应中的 SVG，上传 OSS 或直接嵌入
- **MIRROR**: 使用 `ModelProviderService` 调用 DashScope（与 Agent 相同模式）
- **IMPORTS**: `App\Service\ModelProviderService`
- **GOTCHA**:
  - SVG 生成质量不稳定，需设置合理的 prompt 约束
  - SVG 可能含 JavaScript，上传前需清洗（strip scripts）
  - Fallback: 使用简单模板 SVG（圆圈/箭头/文字）
- **VALIDATE**: 输入 "机器学习流程"，返回有效 SVG

### Task 8: 创建 NanoBananaStrategy
- **ACTION**: Nano Banana AI 生图策略（DashScope 万相）
- **IMPLEMENT**:
  - 调用 DashScope 万相 API: `wan2.6-image` 模型
  - `fetch()`: POST 到 `/services/aigc/multimodal-generation/generation`，文本生图
  - 解析响应中的图片 URL，下载后上传 OSS
- **MIRROR**: 参考 `DashScopeProvider` 的 API 调用模式
- **IMPORTS**: `GuzzleHttp\Client`, `App\Service\ModelProviderService`
- **GOTCHA**:
  - 万相 API 可能返回异步 task_id，需轮询结果
  - 图片生成耗时 5-30 秒，需 SSE 推送进度
  - 生成成本较高，仅 VIP 用户可用（Phase 5 权限控制）
- **VALIDATE**: 调用 API 生成图片，获取 URL

### Task 9: 创建 ImageStrategyFactory
- **ACTION**: 策略工厂 — 根据 ImageAnalyzerAgent 的 `suggested_type` 分发到对应策略
- **IMPLEMENT**:
  - `driver(string $type): ImageStrategyInterface` — 返回对应策略实例
  - `getAvailableTypes(): array` — 获取已启用的策略类型列表
  - 根据 `config/autoload/image.php` 中的启用/禁用状态过滤
- **MIRROR**: 参考 `ModelProviderService` 的工厂模式
- **IMPORTS**: `App\Contract\ImageStrategyInterface`, `Hyperf\Contract\ConfigInterface`
- **GOTCHA**: 
  - 每种策略需通过 DI 容器获取（构造函数注入依赖）
  - 禁用策略不注入，工厂抛出明确异常
- **VALIDATE**: `$factory->driver('pexels')` 返回 PexelsStrategy 实例

### Task 10: 创建 OssUploader
- **ACTION**: 阿里云 OSS 上传封装
- **IMPLEMENT**:
  - `uploadFromUrl(string $url, string $objectKey): string` — 下载远程图片后上传 OSS
  - `uploadFromContent(string $content, string $objectKey, string $mimeType): string` — 直接上传内容
  - 自动生成 CDN URL（如果配置了 CDN 域名）
  - Object key 格式: `images/{date}/{uuid}.{ext}`
  - 内部处理重试和超时
- **MIRROR**: 构造函数 DI + 配置读取模式
- **IMPORTS**: `Oss\OssClient`, `Oss\Models\PutObjectRequest`
- **GOTCHA**:
  - V4 签名需 region + signatureVersion
  - 上传后设置 ACL 为公共读
  - 大文件用 `Uploader::uploadFile` 分片上传
  - 错误时返回原始 URL（降级）
- **VALIDATE**: 上传一张测试图片，返回 OSS URL 可访问

### Task 11: 更新 ParallelImageGenerator（替换 mock）
- **ACTION**: 将 Phase 3 的占位 mock 替换为真实策略调度
- **IMPLEMENT**:
  - 接收 `ImageAnalyzerAgent` 的分析结果
  - 对每个占位符：`factory->driver(analysis.suggested_type)->fetch(keyword)`
  - 协程并行获取所有图片
  - 每获取一张上传 OSS，立即 SSE 推送进度 (`image_ready` 事件)
  - 最终返回所有图片 URL 的映射
- **MIRROR**: 保留原 `AgentInterface` 实现结构
- **IMPORTS**: `App\Service\ImageStrategyFactory`, `App\Service\OssUploader`, `Hyperf\Coroutine\Parallel`
- **GOTCHA**:
  - 使用 `Hyperf\Coroutine\Parallel` 或 `Hyperf\Coroutine\WaitGroup` 并行调度
  - 单个策略失败不影响其他策略（独立降级）
  - 部分图片获取失败时使用默认占位图
  - SSE 每完成一个推送一次进度
- **VALIDATE**: 输入配图分析结果，返回真实图片 URL 列表

### Task 12: 更新 WorkshopOrchestrator 接入真实配图
- **ACTION**: 将配图流程从 mock 切换为真实实现
- **IMPLEMENT**:
  - `IMAGE_GENERATING` 状态时使用更新后的 `ParallelImageGenerator`
  - 替换正文中的 `placeholder://image/N` 为真实 OSS URL
  - 持久化完整的 images JSON 到 article 表
- **MIRROR**: 保留原有状态机流转逻辑，仅替换 Agent 实现
- **IMPORTS**: 更新后的 `ParallelImageGenerator`
- **GOTCHA**: 确保 images JSON 结构与前端预期一致: `[{placeholder_id, url, alt, source, attribution}]`
- **VALIDATE**: 完整创作流程后 article.images 含真实 URL

### Task 13: 创建前端配图进度组件
- **ACTION**: 在创作工坊阶段 3 展示配图获取进度
- **IMPLEMENT**:
  - `ImageProgress.vue`: 显示每张配图的状态（搜索中/上传中/完成/失败）
  - SSE 事件 `image_ready` 更新对应图片状态
  - 图片预览小缩略图
- **MIRROR**: Ant Design Progress/Tag/Image 组件；Vue 响应式更新
- **IMPORTS**: `ant-design-vue`
- **GOTCHA**: 配图完成后需触发 Markdown 正文重新渲染，替换占位符
- **VALIDATE**: 创作过程中看到配图逐个完成

### Task 14: 集成验证
- **ACTION**: Docker 环境端到端测试完整配图流程
- **IMPLEMENT**:
  - 启动全栈 → 创建创作会话 → 完成标题和大纲 → 观察配图过程
  - 验证: Pexels 搜索成功、Mermaid 代码块生成、Iconify SVG 获取
  - 验证: OSS 上传后 URL 可公开访问
  - 验证: 最终文章含真实配图 URL（非占位符）
- **MIRROR**: Phase 1/2/3 集成验证模式
- **IMPORTS**: N/A
- **GOTCHA**: 需提前配置 PEXELS_API_KEY + OSS 凭证
- **VALIDATE**: 文章预览中显示真实配图

---

## Testing Strategy

### Unit Tests

| Test | Input | Expected Output |
|---|---|---|
| PexelsStrategy::supports | 'pexels' | true |
| PexelsStrategy::supports | 'mermaid' | false |
| MermaidStrategy::fetch | keyword='用户登录流程' | mermaid code block string |
| IconifyStrategy::fetch | keyword='AI' | SVG URL |
| ImageStrategyFactory::driver | 'pexels' | PexelsStrategy instance |
| ImageStrategyFactory::driver | 'unknown' | throws BusinessException |
| OssUploader::uploadFromUrl | valid image URL | OSS URL accessible |
| ParallelImageGenerator | 3 placeholder analyses | 3 image results |

### Edge Cases Checklist
- [ ] Pexels 搜索无结果 → 返回通用科技配图
- [ ] OSS 上传失败 → 降级使用原始 URL
- [ ] Mermaid 语法错误 → 返回文本流程图
- [ ] Iconify 搜索无结果 → 返回通用图标
- [ ] 单个策略超时 → 其他策略继续，超时的用占位图
- [ ] 图片下载失败（404） → 跳过该图或使用备选
- [ ] NanoBanana 异步模式返回 task_id → 轮询至超时降级

---

## Validation Commands

### Static Analysis
```bash
php -l app/Contract/ImageStrategyInterface.php app/Dto/ImageResult.php \
  app/Service/ImageStrategy/*.php app/Service/ImageStrategyFactory.php \
  app/Service/OssUploader.php
```
EXPECT: No syntax errors

### Pexels API Test
```bash
php -r "
require 'vendor/autoload.php';
\$c = new Devscast\Pexels\Client(token: getenv('PEXELS_API_KEY'));
\$r = \$c->searchPhotos('AI technology', new Devscast\Pexels\SearchParameters(perPage: 1));
echo \$r->photos[0]->src->medium;
"
```
EXPECT: Output a valid image URL

### OSS Upload Test
```bash
# Upload a test image
php bin/hyperf.php tinker
> $uploader = make(App\Service\OssUploader::class);
> echo $uploader->uploadFromUrl('https://images.pexels.com/...', 'test/test.jpg');
EXPECT: OSS URL accessible via curl

### Database Validation
```bash
docker exec nlp-mysql mysql -u root -pnlp_root_2024 nlp_content \
  -e "SELECT id,JSON_LENGTH(images) FROM articles WHERE images IS NOT NULL LIMIT 1"
```
EXPECT: images count > 0

---

## Acceptance Criteria
- [ ] 6 种配图策略全部实现并通过工厂调度
- [ ] Pexels 可搜索并返回真实图片 URL
- [ ] Mermaid 可生成流程图代码块
- [ ] Iconify 可检索图标 SVG
- [ ] OSS 上传成功并返回可访问 URL
- [ ] ParallelImageGenerator 协程并行执行多策略
- [ ] 配图进度通过 SSE 实时推送
- [ ] 正文中配图占位符被替换为真实 URL
- [ ] 单策略失败不影响整体流程

## Completion Checklist
- [ ] 策略模式接口统一，新增配图方式零侵入
- [ ] OSS 上传封装支持 URL 和内容两种模式
- [ ] 并行调度使用 Hyperf 协程（非多进程）
- [ ] 配图失败有降级方案（占位图）
- [ ] 图片归属信息按 API 要求注明
- [ ] 环境变量统一管理，无硬编码 Key

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Pexels API 免费额度不足 | M | M | 缓存常用配图 + 多来源降级 |
| OSS SDK V2 签名兼容性 | L | M | 严格按文档使用 V4 签名 |
| Nano Banana 生成速度慢 | H | L | 设置超时 30s，超时降级占位图 |
| Mermaid.ink 稳定性 | M | L | 前端 Mermaid.js 渲染备选 |
| 图片版权问题 | L | H | Pexels 注明归属；表情包使用 Twemoji 开源集 |

## Notes

- Mermaid 流程图最佳实现是返回代码块，前端 mermaid.js 渲染（浏览器端零成本、无损缩放）
- Pexels 归属信息必须保留（`attribution` 字段），存储在 `article.images` JSON 中
- OSS 上传策略：图片先上传 OSS 再替换 Markdown 占位符，确保文章持久性不依赖外部 URL
- Iconify SVG 可直接用 CDN URL 无需上传（但建议也上传 OSS 保证可用性）
- 配图权限控制（高级配图仅 VIP）在 Phase 5 实现，Phase 4 所有策略默认可用
- 前端 `ImageProgress.vue` 组件需与 Phase 3 的 `ContentPreview.vue` 配合，配图完成后触发 Markdown 重渲染
