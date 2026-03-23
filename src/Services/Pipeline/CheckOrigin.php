<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Services\Pipeline;

use Closure;
use Illuminate\Http\Request;
use Labrodev\Dpop\Exceptions\InvalidDPopProofException;

final class CheckOrigin
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var string[] $allowedOrigins */
        $allowedOrigins = array_filter((array) config('dpop.allowed_origins'));

        if (empty($allowedOrigins)) {
            return $next($request);
        }

        $origin = (string) $request->header('Origin', '');

        if (! in_array(needle: $origin, haystack: $allowedOrigins, strict: true)) {
            throw new InvalidDPopProofException(errorCode: 'C.O.1');
        }

        return $next($request);
    }
}
