<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Labrodev\Dpop\Contracts\DPopTokenGeneratorInterface;
use Labrodev\Dpop\Data\TokenRequestData;
use Labrodev\Dpop\Http\Resources\TokenResource;

final class TokenController
{
    public function __invoke(DPopTokenGeneratorInterface $generator, TokenRequestData $tokenRequestData): JsonResponse
    {
        $token = $generator->generate(tokenRequestData: $tokenRequestData);

        return TokenResource::make($token)
            ->response()
            ->header('Cache-Control', 'no-store');
    }
}
