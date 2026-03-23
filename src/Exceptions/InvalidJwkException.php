<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Exceptions;

use Illuminate\Http\JsonResponse;

final class InvalidJwkException extends DPopException
{
    public function render(): JsonResponse
    {
        return response()->json(
            data: ['error' => $this->errorCode],
            status: 422,
        );
    }
}
