<?php

declare(strict_types=1);

namespace Labrodev\Dpop;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Pipeline\Pipeline as PipelineContract;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Router;
use Labrodev\Dpop\Console\Commands\InstallCommand;
use Labrodev\Dpop\Contracts\DPopTokenGeneratorInterface;
use Labrodev\Dpop\Http\Middleware\DPopMiddleware;
use Labrodev\Dpop\Http\Middleware\EnforceIdempotencyMiddleware;
use Labrodev\Dpop\Services\DPopVerifier;
use Labrodev\Dpop\Services\TokenGenerator;
use Labrodev\Dpop\Support\JtiStore;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class DpopServiceProvider extends PackageServiceProvider
{
    public const string PACKAGE_NAME = 'dpop';

    public function configurePackage(Package $package): void
    {
        $package->name(self::PACKAGE_NAME)
            ->hasConfigFile(self::PACKAGE_NAME)
            ->hasRoutes(['api'])
            ->hasCommand(InstallCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->app->bind(DPopTokenGeneratorInterface::class, TokenGenerator::class);

        $this->app->bind(PipelineContract::class, Pipeline::class);

        $this->app->bind(
            abstract: Repository::class,
            concrete: function (): Repository {
                /** @var CacheManager $manager */
                $manager = $this->app->make(CacheManager::class);

                $store = config('dpop.cache_store');

                return $store
                    ? $manager->store((string) $store)
                    : $manager->store();
            },
        );

        $this->app->bind(JtiStore::class, function (): JtiStore {
            return new JtiStore(cache: $this->app->make(Repository::class));
        });

        $this->app->bind(DPopVerifier::class, function (): DPopVerifier {
            return new DPopVerifier(jtiStore: $this->app->make(JtiStore::class));
        });

        $this->app->bind(EnforceIdempotencyMiddleware::class, function (): EnforceIdempotencyMiddleware {
            return new EnforceIdempotencyMiddleware(cache: $this->app->make(Repository::class));
        });
    }

    public function bootingPackage(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('dpop', DPopMiddleware::class);
        $router->aliasMiddleware('dpop.idempotency', EnforceIdempotencyMiddleware::class);
    }
}
