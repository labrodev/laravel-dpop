<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

abstract class DPopException extends RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct(message: $errorCode);
    }

    abstract public function render(): JsonResponse;
}
