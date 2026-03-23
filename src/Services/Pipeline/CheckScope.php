<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Services\Pipeline;

use Closure;
use Illuminate\Http\Request;
use Labrodev\Dpop\Exceptions\InvalidDPopProofException;

final class CheckScope
{
    /**
     * @param  string[]  $requiredScopes
     */
    public function __construct(private readonly array $requiredScopes) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (empty($this->requiredScopes)) {
            return $next($request);
        }

        /** @var array<string,mixed> $jwt */
        $jwt = $request->attributes->get('dpop_jwt', []);

        /** @var string[] $tokenScopes */
        $tokenScopes = (array) ($jwt['scp'] ?? []);

        foreach ($this->requiredScopes as $required) {
            if (! in_array(needle: $required, haystack: $tokenScopes, strict: true)) {
                throw new InvalidDPopProofException(errorCode: 'S.1');
            }
        }

        return $next($request);
    }
}
