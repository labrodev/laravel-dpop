<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Data;

final readonly class IssuedToken
{
    public function __construct(
        public string $value,
        public int $expiresIn,
    ) {}
}
