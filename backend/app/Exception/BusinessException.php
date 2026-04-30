<?php

declare(strict_types=1);

namespace App\Exception;

use App\Constants\Code;
use RuntimeException;
use Throwable;

/**
 * 业务异常：承载业务错误码与 HTTP 状态码。
 * 由 BusinessExceptionHandler 统一捕获并序列化为 JSON。
 */
class BusinessException extends RuntimeException
{
    public function __construct(
        int $code = Code::ERROR,
        string $message = '',
        protected int $httpStatus = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
