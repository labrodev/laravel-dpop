<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Http\Middleware;

use Closure;
use Illuminate\Contracts\Pipeline\Pipeline;
use Illuminate\Http\Request;
use Labrodev\Dpop\Exceptions\DPopException;
use Labrodev\Dpop\Services\Pipeline\CheckOrigin;
use Labrodev\Dpop\Services\Pipeline\CheckScope;
use Labrodev\Dpop\Services\Pipeline\VerifyProof;

final class DPopMiddleware
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(Request $request, Closure $next, string ...$scopes): mixed
    {
        try {
            return $this->pipeline
                ->send($request)
                ->through([
                    CheckOrigin::class,
                    VerifyProof::class,
                    new CheckScope(requiredScopes: array_values($scopes)),
                ])
                ->then(static fn (Request $r): mixed => $next($r));
        } catch (DPopException $e) {
            return $e->render();
        }
    }
}
