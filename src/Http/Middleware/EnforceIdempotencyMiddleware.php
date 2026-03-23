<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Labrodev\Dpop\Exceptions\IdempotencyConflictException;

final class EnforceIdempotencyMiddleware
{
    private const array UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const int CACHE_TTL = 86400; // 24 hours

    public function __construct(private readonly Repository $cache) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! in_array(needle: strtoupper($request->method()), haystack: self::UNSAFE_METHODS, strict: true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (! $key || ! $this->isValidKey((string) $key)) {
            return response()->json(
                data: ['error' => 'E.I.1'],
                status: 422,
            );
        }

        $cacheKey = 'dpop:idempotency:'.$key;

        /** @var array{body_hash: string, response: array<string,mixed>}|null $cached */
        $cached = $this->cache->get($cacheKey);

        $bodyHash = hash(algo: 'sha256', data: $request->getContent());

        if ($cached !== null) {
            if (! hash_equals($cached['body_hash'], $bodyHash)) {
                throw new IdempotencyConflictException(errorCode: 'E.I.2');
            }

            return response()->json(
                data: $cached['response'],
                status: 200,
            )->header('Idempotency-Replayed', 'true');
        }

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $this->cache->put(
                key: $cacheKey,
                ttl: self::CACHE_TTL,
                value: [
                    'body_hash' => $bodyHash,
                    'response' => $response->getData(assoc: true),
                ],
            );
        }

        return $response;
    }

    private function isValidKey(string $key): bool
    {
        return (bool) preg_match('/^[0-9a-f\-]{8,}$/i', $key);
    }
}
