<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Tests\Feature;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Labrodev\Dpop\Tests\Concerns\GeneratesDPopProofs;
use Labrodev\Dpop\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TokenEndpointTest extends TestCase
{
    use GeneratesDPopProofs;

    #[Test]
    public function it_issues_a_token_for_valid_request(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $response = $this->postJson('/api/dpop/token', [
            'jwk' => $publicJwk,
            'scope' => 'read',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'type',
                'attributes' => ['expires_in', 'token'],
            ],
        ]);
        $response->assertJsonPath('data.type', 'token');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control') ?? '');
    }

    #[Test]
    public function it_returns_expires_in_from_config(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $response = $this->postJson('/api/dpop/token', [
            'jwk' => $publicJwk,
            'scope' => 'read',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.attributes.expires_in', 3600);
    }

    #[Test]
    public function it_rejects_request_with_private_key_d(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $jwkWithPrivate = array_merge($publicJwk, ['d' => 'private-key-value']);

        $response = $this->postJson('/api/dpop/token', [
            'jwk' => $jwkWithPrivate,
            'scope' => 'read',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_rejects_request_missing_jwk(): void
    {
        $response = $this->postJson('/api/dpop/token', [
            'scope' => 'read',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_rejects_request_missing_scope(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $response = $this->postJson('/api/dpop/token', [
            'jwk' => $publicJwk,
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_rejects_jwk_missing_required_fields(): void
    {
        $response = $this->postJson('/api/dpop/token', [
            'jwk' => ['kty' => 'EC'], // missing crv, x, y
            'scope' => 'read',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_includes_extra_claims_in_the_issued_token(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $response = $this->postJson('/api/dpop/token', [
            'jwk' => $publicJwk,
            'scope' => 'read',
            'extra_claims' => [
                'plugin_uuid' => '550e8400-e29b-41d4-a716-446655440000',
                'origin' => 'https://widget.example',
            ],
        ]);

        $response->assertOk();

        $token = $response->json('data.attributes.token');
        $this->assertIsString($token);

        $decoded = (array) JWT::decode(
            jwt: $token,
            keyOrKeyArray: new Key('test-secret-key-for-unit-tests-only-64-chars-long-padding-here!', 'HS256'),
        );

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $decoded['plugin_uuid']);
        $this->assertSame('https://widget.example', $decoded['origin']);
    }
}
