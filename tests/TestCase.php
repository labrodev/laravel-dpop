<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Tests;

use Labrodev\Dpop\DpopServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            DpopServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('dpop.jwt.secret', 'test-secret-key-for-unit-tests-only-64-chars-long-padding-here!');
        $app['config']->set('dpop.jwt.algorithm', 'HS256');
        $app['config']->set('dpop.jwt.lifetime', 3600);
        $app['config']->set('dpop.clock_skew', 30);
        $app['config']->set('dpop.jti_ttl', 600);
        $app['config']->set('dpop.proof_header', 'DPoP');
        $app['config']->set('dpop.allowed_origins', []);
        $app['config']->set('dpop.token_route', 'api/dpop/token');
        $app['config']->set('cache.default', 'array');
    }
}
