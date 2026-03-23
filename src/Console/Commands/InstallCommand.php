<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class InstallCommand extends Command
{
    protected $signature = 'dpop:install';

    protected $description = 'Interactive DPoP setup — writes DPOP_* entries to .env and publishes config.';

    public function handle(): int
    {
        $this->info('DPoP Setup');
        $this->line('──────────────────────────────────────────────');

        $secret = $this->askSecret();
        $lifetime = $this->ask(default: '3600', question: 'JWT lifetime in seconds');
        $proofHeader = $this->ask(default: 'DPoP', question: 'DPoP proof header name');
        $allowedOrigins = $this->ask(default: '', question: 'Allowed origins (comma-separated, or * for dev)');
        $tokenRoute = $this->ask(default: 'api/dpop/token', question: 'Token route URI');
        $cacheStore = $this->ask(default: '', question: 'Cache store for JTI / idempotency (leave blank for default)');

        $this->writeEnv(entries: [
            'DPOP_JWT_SECRET' => $secret,
            'DPOP_JWT_LIFETIME' => (string) $lifetime,
            'DPOP_PROOF_HEADER' => (string) $proofHeader,
            'DPOP_ALLOWED_ORIGINS' => (string) $allowedOrigins,
            'DPOP_TOKEN_ROUTE' => (string) $tokenRoute,
            'DPOP_CACHE_STORE' => (string) $cacheStore,
        ]);

        Artisan::call('vendor:publish', [
            '--provider' => 'Labrodev\\Dpop\\DpopServiceProvider',
            '--tag' => 'dpop-config',
            '--force' => true,
        ]);

        $this->line('');
        $this->info('DPoP configuration complete. Summary:');
        $this->line('');
        $this->table(
            headers: ['Key', 'Value'],
            rows: [
                ['DPOP_JWT_SECRET', str_repeat('*', min(8, strlen($secret))).'…'],
                ['DPOP_JWT_LIFETIME', (string) $lifetime],
                ['DPOP_PROOF_HEADER', (string) $proofHeader],
                ['DPOP_ALLOWED_ORIGINS', (string) $allowedOrigins ?: '(none)'],
                ['DPOP_TOKEN_ROUTE', (string) $tokenRoute],
                ['DPOP_CACHE_STORE', (string) $cacheStore ?: '(app default)'],
            ],
        );

        return self::SUCCESS;
    }

    private function askSecret(): string
    {
        $auto = $this->confirm(default: true, question: 'Auto-generate a JWT secret?');

        if ($auto) {
            return bin2hex(random_bytes(32));
        }

        return (string) $this->secret('Enter your JWT secret');
    }

    /**
     * @param  array<string,string>  $entries
     */
    private function writeEnv(array $entries): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->warn('.env file not found — skipping write.');

            return;
        }

        $content = (string) file_get_contents($envPath);

        foreach ($entries as $key => $value) {
            $escapedValue = str_contains($value, ' ') ? "\"{$value}\"" : $value;
            $line = "{$key}={$escapedValue}";

            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", $line, $content) ?? $content;
            } else {
                $content .= PHP_EOL.$line;
            }
        }

        file_put_contents(data: $content, filename: $envPath);
    }
}
