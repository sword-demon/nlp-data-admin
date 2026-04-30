<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\Code;
use App\Exception\BusinessException;
use App\Helpers\ApiResponse;
use App\Service\ModelProviderService;
use Hyperf\Context\ResponseContext;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Engine\Http\EventStream;
use Hyperf\HttpMessage\Server\Response as HyperfServerResponse;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * AI 聊天控制器。
 *
 * - POST /api/ai/chat         非流式补全（返回标准 JSON）
 * - POST /api/ai/chat/stream  SSE 流式，直接写入 Swoole 底层 Response
 * - GET  /api/ai/providers    当前可用 Provider 列表（api_key 非空者）
 *
 * SSE 设计说明：不走 Hyperf 响应管道，直接取 Context 中的 Swoole\Http\Response
 * 手动 header/write/end。控制器返回空响应，避免 Hyperf 再次写入 body 引发冲突。
 */
class AiChatController extends AbstractController
{
    #[Inject]
    protected ModelProviderService $providerService;

    /** GET /api/ai/providers */
    public function providers(): ResponseInterface
    {
        $provider = $this->providerService->driver();

        return ApiResponse::success($this->response, [
            'default' => $provider->getName(),
            'available' => $this->providerService->getAvailableProviders(),
            'models' => $provider->getModels(),
        ]);
    }

    /** POST /api/ai/chat */
    public function chat(): ResponseInterface
    {
        [$prompt, $messages, $options] = $this->parseInput();

        $text = $this->providerService->driver($options['provider'] ?? null)
            ->chat($prompt, $messages, $options);

        return ApiResponse::success($this->response, ['text' => $text]);
    }

    /** POST /api/ai/chat/stream — SSE */
    public function chatStream(): ResponseInterface
    {
        [$prompt, $messages, $options] = $this->parseInput();

        // 取 Hyperf 的 PSR-7 Response，它持有底层 Writable（Swoole/Swow 通用）
        $psr7 = ResponseContext::get();
        $connection = $psr7 instanceof HyperfServerResponse ? $psr7->getConnection() : null;
        if (! $connection) {
            throw new BusinessException(Code::AI_STREAM_ERROR, 'SSE not supported in current context', 500);
        }

        $stream = new EventStream($connection);

        try {
            $generator = $this->providerService->driver($options['provider'] ?? null)
                ->chatStream($prompt, $messages, $options);

            foreach ($generator as $chunk) {
                $line = 'data: ' . json_encode(['text' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";
                $stream->write($line);
            }

            $stream->write("data: [DONE]\n\n");
        } catch (Throwable $e) {
            $stream->write('data: ' . json_encode([
                'error' => true,
                'code' => $e instanceof BusinessException ? $e->getCode() : Code::AI_STREAM_ERROR,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE) . "\n\n");
        }

        $stream->end();

        // 连接已由 EventStream::end() 关闭；返回空响应避免 Hyperf 再次写入 body
        return $this->response->raw('');
    }

    /**
     * 解析统一输入格式：{ prompt, messages?, model?, provider?, temperature?, top_p?, max_tokens? }.
     *
     * @return array{0: string, 1: array<int, array{role: string, content: string}>, 2: array<string, mixed>}
     */
    private function parseInput(): array
    {
        $prompt = (string) $this->request->input('prompt', '');
        $messages = $this->request->input('messages', []);
        if (! is_array($messages)) {
            $messages = [];
        }

        $options = array_filter([
            'model' => $this->request->input('model'),
            'provider' => $this->request->input('provider'),
            'temperature' => $this->request->input('temperature'),
            'top_p' => $this->request->input('top_p'),
            'max_tokens' => $this->request->input('max_tokens'),
        ], static fn($v) => $v !== null && $v !== '');

        if ($prompt === '' && empty($messages)) {
            throw new BusinessException(Code::VALIDATION_ERROR, 'prompt or messages required', 422);
        }

        // 规范化 messages：保留 role/content 字段
        $normalized = [];
        foreach ($messages as $m) {
            if (! is_array($m)) {
                continue;
            }
            $role = (string) ($m['role'] ?? '');
            $content = (string) ($m['content'] ?? '');
            if ($role === '' || $content === '') {
                continue;
            }
            $normalized[] = ['role' => $role, 'content' => $content];
        }

        return [$prompt, $normalized, $options];
    }
}
