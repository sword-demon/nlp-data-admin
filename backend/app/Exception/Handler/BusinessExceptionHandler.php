<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Exception\BusinessException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * 业务异常处理器：捕获 BusinessException 并输出统一 JSON 响应。
 * 注册顺序位于 HttpExceptionHandler 之后、AppExceptionHandler 之前。
 */
class BusinessExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        /** @var BusinessException $throwable */
        $this->stopPropagation();

        $body = json_encode([
            'code' => $throwable->getCode(),
            'message' => $throwable->getMessage() ?: 'Business Error',
            'data' => null,
        ], JSON_UNESCAPED_UNICODE);

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($throwable->getHttpStatus())
            ->withBody(new SwooleStream((string) $body));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof BusinessException;
    }
}
