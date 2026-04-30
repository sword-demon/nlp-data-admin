<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Constants\Code;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class ApiResponse
{
    public static function success(
        ResponseInterface $response,
        mixed $data = [],
        string $message = 'success'
    ): PsrResponseInterface {
        return $response->json([
            'code' => Code::SUCCESS,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public static function error(
        ResponseInterface $response,
        int $code = Code::ERROR,
        string $message = 'error',
        int $httpCode = 400
    ): PsrResponseInterface {
        return $response->json([
            'code' => $code,
            'message' => $message,
            'data' => null,
        ])->withStatus($httpCode);
    }

    public static function paginate(
        ResponseInterface $response,
        mixed $data,
        int $total,
        int $page,
        int $limit
    ): PsrResponseInterface {
        return $response->json([
            'code' => Code::SUCCESS,
            'message' => 'success',
            'data' => [
                'list' => $data,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ],
        ]);
    }
}
