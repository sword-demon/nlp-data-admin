<?php

declare(strict_types=1);

namespace App\Service\ImageStrategy;

use App\Constants\Code;
use App\Contract\ImageStrategyInterface;
use App\Dto\ImageResult;
use App\Exception\BusinessException;
use App\Service\OssUploader;
use Hyperf\Contract\ConfigInterface;

/**
 * Emoji 策略（基于 Twemoji CDN）。
 *
 * 依据关键词做简单主题映射，返回大尺寸 emoji SVG。
 * 零 API key、零外部依赖、延迟极低 — 作为其他策略失败时的稳定兜底。
 */
class EmojiStrategy extends AbstractImageStrategy implements ImageStrategyInterface
{
    // 关键词 → emoji Unicode code point（Twemoji 要求连字符分隔的十六进制）
    private const KEYWORD_MAP = [
        // 技术类
        'ai' => '1f916',
        '人工智能' => '1f916',
        '机器人' => '1f916',
        '代码' => '1f4bb',
        '编程' => '1f4bb',
        '开发' => '1f4bb',
        '程序员' => '1f468-200d-1f4bb',
        '数据' => '1f4ca',
        '图表' => '1f4ca',
        '统计' => '1f4ca',
        '分析' => '1f50d',
        '趋势' => '1f4c8',
        '增长' => '1f4c8',
        '上升' => '1f4c8',
        '下降' => '1f4c9',
        '衰退' => '1f4c9',
        '流程' => '1f504',
        '循环' => '1f504',
        '迭代' => '1f504',
        '对比' => '2696',
        '平衡' => '2696',
        '想法' => '1f4a1',
        '创意' => '1f4a1',
        '灵感' => '1f4a1',
        '洞察' => '1f4a1',
        '目标' => '1f3af',
        '聚焦' => '1f3af',
        '火箭' => '1f680',
        '启动' => '1f680',
        '加速' => '1f680',
        '警告' => '26a0',
        '风险' => '26a0',
        '注意' => '26a0',
        '成功' => '2705',
        '完成' => '2705',
        '达成' => '2705',
        '失败' => '274c',
        '错误' => '274c',
        '搜索' => '1f50d',
        '查找' => '1f50d',
        '研究' => '1f50d',
        '书' => '1f4d6',
        '学习' => '1f4d6',
        '知识' => '1f4d6',
        '文档' => '1f4dd',
        '工具' => '1f528',
        '设置' => '2699',
        '配置' => '2699',
        '齿轮' => '2699',
        '思考' => '1f9e0',
        '大脑' => '1f9e0',
        '智能' => '1f9e0',
        '用户' => '1f464',
        '人' => '1f464',
        '团队' => '1f465',
        '时间' => '23f0',
        '速度' => '26a1',
        '效率' => '26a1',
        '链接' => '1f517',
        '连接' => '1f517',
        '网络' => '1f310',
        '全球' => '1f310',
        '锁' => '1f512',
        '安全' => '1f512',
        '加密' => '1f512',
        '云' => '2601',
        '云端' => '2601',
        '手机' => '1f4f1',
        '移动' => '1f4f1',
        '设备' => '1f4bb',
    ];

    private string $cdnBase;
    private bool $enabled;

    public function __construct(
        ConfigInterface $config,
        private readonly OssUploader $uploader,
    ) {
        $c = (array) $config->get('image.strategies.emoji', []);
        $this->enabled = (bool) ($c['enabled'] ?? true);
        $this->cdnBase = rtrim((string) ($c['cdn_base'] ?? 'https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/svg'), '/');
    }

    public function getName(): string
    {
        return 'emoji';
    }

    public function getLabel(): string
    {
        return 'Emoji 表情';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function supports(string $type): bool
    {
        return $type === 'emoji';
    }

    public function fetch(string $keyword, array $options = []): ImageResult
    {
        if (! $this->isEnabled()) {
            throw new BusinessException(Code::IMAGE_STRATEGY_DISABLED, 'Emoji disabled', 500);
        }

        $q = $this->normalizeKeyword($keyword);
        $code = $this->matchEmoji($q);
        $originalUrl = "{$this->cdnBase}/{$code}.svg";

        // 上传 OSS（未启用时直接返回原 URL）
        $finalUrl = $this->uploader->uploadFromUrl($originalUrl, 'svg');

        return new ImageResult(
            source: 'emoji',
            url: $finalUrl,
            originalUrl: $originalUrl,
            alt: "Emoji：{$q}",
            attribution: 'Twemoji by Twitter (CC-BY 4.0)',
            mime: 'image/svg+xml',
            raw: $code,
        );
    }

    private function matchEmoji(string $keyword): string
    {
        $lower = mb_strtolower($keyword);
        foreach (self::KEYWORD_MAP as $k => $code) {
            if ($k !== '' && str_contains($lower, mb_strtolower($k))) {
                return $code;
            }
        }
        // 默认：✨ sparkles
        return '2728';
    }
}
