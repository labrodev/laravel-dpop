<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Support;

use Illuminate\Contracts\Cache\Repository;

final class JtiStore
{
    public function __construct(private readonly Repository $cache) {}

    /**
     * Attempt to store a JTI to detect replays.
     * Returns true if stored successfully (first use), false if already exists (replay).
     */
    public function store(string $jti, int $ttl): bool
    {
        return $this->cache->add(
            key: "dpop:jti:{$jti}",
            ttl: $ttl,
            value: 1,
        );
    }
}
