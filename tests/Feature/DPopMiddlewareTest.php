<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Labrodev\Dpop\Tests\Concerns\GeneratesDPopProofs;
use Labrodev\Dpop\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DPopMiddlewareTest extends TestCase
{
    use GeneratesDPopProofs;

    /** @var array<string,string> */
    private array $publicJwk;

    private string $privateKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        ['privateKey' => $this->privateKeyPem, 'publicJwk' => $this->publicJwk] = $this->generateEcKeyPair();
    }

    private function setupProtectedRoute(string ...$scopes): void
    {
        $middleware = $scopes
            ? 'dpop:'.implode(',', $scopes)
            : 'dpop';

        Route::get('/protected', fn () => response()->json(['ok' => true]))
            ->middleware($middleware);
    }

    private function makeProtectedRequest(string $url = 'http://localhost/protected'): TestResponse
    {
        $bearer = $this->buildBearerJwt(publicJwk: $this->publicJwk);
        $proof = $this->buildDPopProof(
            htm: 'GET',
            htu: $url,
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        return $this->getJson($url, [
            'Authorization' => "Bearer {$bearer}",
            'DPoP' => $proof,
        ]);
    }

    #[Test]
    public function it_allows_valid_dpop_request_through(): void
    {
        $this->setupProtectedRoute();

        $this->makeProtectedRequest()->assertOk();
    }

    #[Test]
    public function it_returns_401_without_authorization_header(): void
    {
        $this->setupProtectedRoute();

        $response = $this->getJson('/protected');

        $response->assertUnauthorized();
        $response->assertJsonPath('error', 'D.E.1');
    }

    #[Test]
    public function it_returns_401_without_dpop_proof_header(): void
    {
        $this->setupProtectedRoute();

        $bearer = $this->buildBearerJwt(publicJwk: $this->publicJwk);

        $response = $this->getJson('/protected', [
            'Authorization' => "Bearer {$bearer}",
        ]);

        $response->assertUnauthorized();
        $response->assertJsonPath('error', 'D.E.5');
    }

    #[Test]
    public function it_returns_401_for_replayed_jti(): void
    {
        $this->setupProtectedRoute();

        $bearer = $this->buildBearerJwt(publicJwk: $this->publicJwk);
        $proof = $this->buildDPopProof(
            htm: 'GET',
            htu: 'http://localhost/protected',
            payloadOverrides: ['jti' => 'fixed-replay-jti-middleware-test'],
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $headers = [
            'Authorization' => "Bearer {$bearer}",
            'DPoP' => $proof,
        ];

        $this->getJson('/protected', $headers)->assertOk();
        $this->getJson('/protected', $headers)->assertUnauthorized()
            ->assertJsonPath('error', 'D.E.12');
    }

    #[Test]
    public function it_enforces_required_scopes(): void
    {
        $this->setupProtectedRoute('admin');

        $response = $this->makeProtectedRequest();

        // Token has 'read' scope, route requires 'admin'
        $response->assertUnauthorized();
        $response->assertJsonPath('error', 'S.1');
    }

    #[Test]
    public function it_passes_when_scope_matches(): void
    {
        $this->setupProtectedRoute('read');

        $this->makeProtectedRequest()->assertOk();
    }

    #[Test]
    public function it_returns_401_for_wrong_htu(): void
    {
        $this->setupProtectedRoute();

        $bearer = $this->buildBearerJwt(publicJwk: $this->publicJwk);
        $proof = $this->buildDPopProof(
            htm: 'GET',
            htu: 'https://evil.example.com/protected',
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $response = $this->getJson('/protected', [
            'Authorization' => "Bearer {$bearer}",
            'DPoP' => $proof,
        ]);

        $response->assertUnauthorized();
        $response->assertJsonPath('error', 'D.E.10');
    }

    #[Test]
    public function it_rejects_mismatched_key_thumbprint(): void
    {
        $this->setupProtectedRoute();

        ['publicJwk' => $otherJwk] = $this->generateEcKeyPair();
        $bearer = $this->buildBearerJwt(publicJwk: $otherJwk);
        $proof = $this->buildDPopProof(
            htm: 'GET',
            htu: 'http://localhost/protected',
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $response = $this->getJson('/protected', [
            'Authorization' => "Bearer {$bearer}",
            'DPoP' => $proof,
        ]);

        $response->assertUnauthorized();
        $response->assertJsonPath('error', 'D.E.13');
    }
}
