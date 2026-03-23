<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Contracts;

use Labrodev\Dpop\Data\IssuedToken;
use Labrodev\Dpop\Data\TokenRequestData;

interface DPopTokenGeneratorInterface
{
    public function generate(TokenRequestData $tokenRequestData): IssuedToken;
}
