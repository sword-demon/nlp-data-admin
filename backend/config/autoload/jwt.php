<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'secret' => env('JWT_SECRET', ''),
    'algo' => env('JWT_ALGO', 'HS256'),
    'ttl' => (int) env('JWT_TTL', 86400),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 604800),
    'issuer' => env('APP_NAME', 'nlp-data-admin'),
];
