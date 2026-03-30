<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Support;

use Illuminate\Http\Request;
use Throwable;

/**
 * Aligns DPoP proof {@see https://www.rfc-editor.org/rfc/rfc9449.html#name-http-uri} {@code htu}
 * with {@see Request::fullUrl()}.
 *
 * Laravel/Symfony normalize the query string (sorted keys, RFC 3986 encoding). Clients often
 * build {@code htu} with a different parameter order, which would fail a naive string compare
 * ({@code D.E.10}).
 */
final class HtuMatchesRequest
{
    public static function matches(string $htu, Request $request): bool
    {
        if ($htu === $request->fullUrl()) {
            return true;
        }

        try {
            $canonicalFromProof = Request::create($htu, $request->method())->fullUrl();
        } catch (Throwable) {
            return false;
        }

        return $canonicalFromProof === $request->fullUrl();
    }
}
