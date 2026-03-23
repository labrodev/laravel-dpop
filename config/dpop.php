<?php

declare(strict_types=1);

return [

    'jwt' => [
        'secret' => env('DPOP_JWT_SECRET'),
        'algorithm' => env('DPOP_JWT_ALGORITHM', 'HS256'),
        'lifetime' => env('DPOP_JWT_LIFETIME', 3600),
    ],

    'clock_skew' => env('DPOP_CLOCK_SKEW', 30),

    'cache_store' => env('DPOP_CACHE_STORE'), // null = default cache

    'jti_ttl' => env('DPOP_JTI_TTL', 600),

    'proof_header' => env('DPOP_PROOF_HEADER', 'DPoP'),

    'allowed_origins' => explode(',', env('DPOP_ALLOWED_ORIGINS', '')),

    'token_route' => env('DPOP_TOKEN_ROUTE', 'api/dpop/token'),

];
