<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Exceptions;

use Illuminate\Http\JsonResponse;

final class InvalidDPopProofException extends DPopException
{
    public function render(): JsonResponse
    {
        return response()->json(
            data: ['error' => $this->errorCode],
            status: 401,
        );
    }
}
