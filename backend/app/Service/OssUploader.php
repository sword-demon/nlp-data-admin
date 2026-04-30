<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\Code;
use App\Exception\BusinessException;
use GuzzleHttp\Client;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Guzzle\ClientFactory;
use OSS\Core\OssException;
use OSS\OssClient;
use Throwable;

/**
 * 阿里云 OSS 上传封装。
 *
 * 设计原则：
 * - 配置完整时：下载远端内容 → putObject → 返回 OSS 或 CDN URL
 * - 配置缺失时：直接返回原 URL（降级），仅日志警告，保证端到端流程不中断
 *
 * 依赖：aliyuncs/oss-sdk-php ^2.7
 */
class OssUploader
{
    private bool $enabled;
    private string $accessKeyId;
    private string $accessKeySecret;
    private string $endpoint;
    private string $bucket;
    private string $cdnDomain;
    private string $objectPrefix;

    public function __construct(
        ConfigInterface $config,
        private readonly StdoutLoggerInterface $logger
    ) {
        $c = (array) $config->get('image.oss', []);
        $this->enabled = (bool) ($c['enabled'] ?? false);
        $this->accessKeyId = (string) ($c['access_key_id'] ?? '');
        $this->accessKeySecret = (string) ($c['access_key_secret'] ?? '');
        $this->endpoint = (string) ($c['endpoint'] ?? '');
        $this->bucket = (string) ($c['bucket'] ?? '');
        $this->cdnDomain = rtrim((string) ($c['cdn_domain'] ?? ''), '/');
        $this->objectPrefix = trim((string) ($c['object_prefix'] ?? 'nlp/images'), '/');
    }

    /** 当前 OSS 是否可用。禁用时所有上传操作走降级分支。 */
    public function isEnabled(): bool
    {
        return $this->enabled
            && $this->accessKeyId !== ''
            && $this->accessKeySecret !== ''
            && $this->endpoint !== ''
            && $this->bucket !== '';
    }

    /**
     * 下载远程图片并上传到 OSS。
     *
     * @param string $remoteUrl 远程图片 URL
     * @param string $ext       推荐文件扩展名（不含点）
     * @return string 最终可公开访问的 URL（OSS URL 或原 URL 降级）
     */
    public function uploadFromUrl(string $remoteUrl, string $ext = 'jpg'): string
    {
        if (! $this->isEnabled()) {
            $this->logger->info("[OssUploader] disabled, return original url: {$remoteUrl}");
            return $remoteUrl;
        }

        try {
            $client = $this->makeHttpClient();
            $res = $client->get($remoteUrl, ['timeout' => 20, 'connect_timeout' => 10]);
            $content = (string) $res->getBody();
            $mime = (string) ($res->getHeader('Content-Type')[0] ?? 'image/jpeg');

            if ($content === '') {
                throw new BusinessException(Code::IMAGE_UPLOAD_FAILED, 'empty remote content', 502);
            }

            return $this->uploadFromContent($content, $ext, $mime);
        } catch (BusinessException $e) {
            $this->logger->warning("[OssUploader] upload failed, fallback to origin: {$e->getMessage()}");
            return $remoteUrl;
        } catch (Throwable $e) {
            $this->logger->warning("[OssUploader] unexpected error, fallback to origin: {$e->getMessage()}");
            return $remoteUrl;
        }
    }

    /**
     * 直接上传内容字符串到 OSS。
     *
     * @param string $content   二进制内容
     * @param string $ext       文件扩展名（不含点）
     * @param string $mime      Content-Type
     * @return string OSS 或 CDN URL
     */
    public function uploadFromContent(string $content, string $ext = 'jpg', string $mime = 'image/jpeg'): string
    {
        if (! $this->isEnabled()) {
            throw new BusinessException(
                Code::IMAGE_UPLOAD_FAILED,
                'OSS is not enabled; cannot upload content',
                500
            );
        }

        $objectKey = $this->makeObjectKey($ext);

        try {
            $ossClient = new OssClient(
                $this->accessKeyId,
                $this->accessKeySecret,
                $this->endpoint
            );
            $ossClient->putObject($this->bucket, $objectKey, $content, [
                OssClient::OSS_HEADERS => [
                    'x-oss-object-acl' => 'public-read',
                    'Content-Type' => $mime,
                ],
            ]);
        } catch (OssException $e) {
            throw new BusinessException(Code::IMAGE_UPLOAD_FAILED, 'OSS put failed: ' . $e->getMessage(), 502, $e);
        }

        return $this->buildPublicUrl($objectKey);
    }

    private function makeObjectKey(string $ext): string
    {
        $date = date('Y/m/d');
        $uuid = bin2hex(random_bytes(8));
        $ext = ltrim($ext, '.');
        $ext = $ext !== '' ? $ext : 'jpg';

        return "{$this->objectPrefix}/{$date}/{$uuid}.{$ext}";
    }

    private function buildPublicUrl(string $objectKey): string
    {
        if ($this->cdnDomain !== '') {
            return $this->cdnDomain . '/' . $objectKey;
        }

        // 解析 endpoint（https://oss-cn-hangzhou.aliyuncs.com → oss-cn-hangzhou.aliyuncs.com）
        $parts = parse_url($this->endpoint);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? $this->endpoint;

        return "{$scheme}://{$this->bucket}.{$host}/{$objectKey}";
    }

    private function makeHttpClient(): Client
    {
        if (ApplicationContext::hasContainer()) {
            /** @var ClientFactory $factory */
            $factory = ApplicationContext::getContainer()->get(ClientFactory::class);
            return $factory->create(['http_errors' => true]);
        }
        return new Client(['http_errors' => true]);
    }
}
