<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Services\Pipeline;

use Closure;
use Illuminate\Http\Request;
use Labrodev\Dpop\Services\DPopVerifier;

final class VerifyProof
{
    public function __construct(private readonly DPopVerifier $verifier) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $this->verifier->verify(request: $request);

        return $next($request);
    }
}
