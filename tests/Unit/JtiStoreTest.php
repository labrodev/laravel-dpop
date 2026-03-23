<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Labrodev\Dpop\Support\JtiStore;
use Labrodev\Dpop\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class JtiStoreTest extends TestCase
{
    private JtiStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->app->make(JtiStore::class);
    }

    #[Test]
    public function it_returns_true_on_first_store(): void
    {
        $result = $this->store->store(jti: 'unique-jti-1', ttl: 60);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_on_replay(): void
    {
        $this->store->store(jti: 'replay-jti', ttl: 60);

        $result = $this->store->store(jti: 'replay-jti', ttl: 60);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_uses_correct_cache_key_prefix(): void
    {
        $jti = 'check-key-jti';

        $this->store->store(jti: $jti, ttl: 60);

        $this->assertTrue(Cache::has("dpop:jti:{$jti}"));
    }

    #[Test]
    public function it_stores_different_jtis_independently(): void
    {
        $result1 = $this->store->store(jti: 'jti-a', ttl: 60);
        $result2 = $this->store->store(jti: 'jti-b', ttl: 60);

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }
}
