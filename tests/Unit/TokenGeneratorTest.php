<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Tests\Unit;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Labrodev\Dpop\Data\TokenRequestData;
use Labrodev\Dpop\Services\TokenGenerator;
use Labrodev\Dpop\Support\Jwk;
use Labrodev\Dpop\Tests\Concerns\GeneratesDPopProofs;
use Labrodev\Dpop\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TokenGeneratorTest extends TestCase
{
    use GeneratesDPopProofs;

    private TokenGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = $this->app->make(TokenGenerator::class);
    }

    #[Test]
    public function it_issues_a_token_with_correct_claims(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $tokenRequestData = TokenRequestData::from([
            'jwk' => $publicJwk,
            'scope' => 'read write',
        ]);

        $issued = $this->generator->generate(tokenRequestData: $tokenRequestData);

        $decoded = (array) JWT::decode(
            jwt: $issued->value,
            keyOrKeyArray: new Key('test-secret-key-for-unit-tests-only-64-chars-long-padding-here!', 'HS256'),
        );

        $jkt = Jwk::thumbprint(jwk: $publicJwk);

        $this->assertSame($jkt, $decoded['sub']);
        $this->assertSame($jkt, $decoded['jkt']);
        $this->assertContains('read', (array) $decoded['scp']);
        $this->assertContains('write', (array) $decoded['scp']);
        $this->assertCount(2, (array) $decoded['scp']);
        $this->assertArrayHasKey('iss', $decoded);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
    }

    #[Test]
    public function it_returns_correct_expires_in(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $tokenRequestData = TokenRequestData::from([
            'jwk' => $publicJwk,
            'scope' => 'read',
        ]);

        $issued = $this->generator->generate(tokenRequestData: $tokenRequestData);

        $this->assertSame(3600, $issued->expiresIn);
    }

    #[Test]
    public function it_sets_sub_equal_to_jkt(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $tokenRequestData = TokenRequestData::from([
            'jwk' => $publicJwk,
            'scope' => 'read',
        ]);

        $issued = $this->generator->generate(tokenRequestData: $tokenRequestData);

        $decoded = (array) JWT::decode(
            jwt: $issued->value,
            keyOrKeyArray: new Key('test-secret-key-for-unit-tests-only-64-chars-long-padding-here!', 'HS256'),
        );

        $this->assertSame($decoded['sub'], $decoded['jkt']);
    }

    #[Test]
    public function it_parses_comma_separated_scopes_without_duplicates(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $tokenRequestData = TokenRequestData::from([
            'jwk' => $publicJwk,
            'scope' => 'read,write',
        ]);

        $issued = $this->generator->generate(tokenRequestData: $tokenRequestData);

        $decoded = (array) JWT::decode(
            jwt: $issued->value,
            keyOrKeyArray: new Key('test-secret-key-for-unit-tests-only-64-chars-long-padding-here!', 'HS256'),
        );

        $scopes = (array) $decoded['scp'];

        $this->assertContains('read', $scopes);
        $this->assertContains('write', $scopes);
        $this->assertCount(2, $scopes);
        $this->assertNotContains('read,write', $scopes);
    }

    #[Test]
    public function it_parses_space_separated_scopes_without_duplicates(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $tokenRequestData = TokenRequestData::from([
            'jwk' => $publicJwk,
            'scope' => 'read write',
        ]);

        $issued = $this->generator->generate(tokenRequestData: $tokenRequestData);

        $decoded = (array) JWT::decode(
            jwt: $issued->value,
            keyOrKeyArray: new Key('test-secret-key-for-unit-tests-only-64-chars-long-padding-here!', 'HS256'),
        );

        $scopes = (array) $decoded['scp'];

        $this->assertContains('read', $scopes);
        $this->assertContains('write', $scopes);
        $this->assertCount(2, $scopes);
        $this->assertNotContains('read write', $scopes);
    }

    #[Test]
    public function it_merges_extra_claims_into_the_jwt_payload(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $tokenRequestData = TokenRequestData::from([
            'jwk' => $publicJwk,
            'scope' => 'read',
            'extra_claims' => [
                'plugin_uuid' => '550e8400-e29b-41d4-a716-446655440000',
                'origin' => 'https://example.com',
            ],
        ]);

        $issued = $this->generator->generate(tokenRequestData: $tokenRequestData);

        $decoded = (array) JWT::decode(
            jwt: $issued->value,
            keyOrKeyArray: new Key('test-secret-key-for-unit-tests-only-64-chars-long-padding-here!', 'HS256'),
        );

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $decoded['plugin_uuid']);
        $this->assertSame('https://example.com', $decoded['origin']);
    }

    #[Test]
    public function it_does_not_allow_extra_claims_to_override_standard_claims(): void
    {
        ['publicJwk' => $publicJwk] = $this->generateEcKeyPair();

        $jkt = Jwk::thumbprint(jwk: $publicJwk);

        $tokenRequestData = TokenRequestData::from([
            'jwk' => $publicJwk,
            'scope' => 'read',
            'extra_claims' => [
                'exp' => 1,
                'iat' => 1,
                'iss' => 'https://evil.example',
                'jkt' => 'tampered',
                'scp' => ['evil'],
                'sub' => 'tampered',
            ],
        ]);

        $issued = $this->generator->generate(tokenRequestData: $tokenRequestData);

        $decoded = (array) JWT::decode(
            jwt: $issued->value,
            keyOrKeyArray: new Key('test-secret-key-for-unit-tests-only-64-chars-long-padding-here!', 'HS256'),
        );

        $this->assertSame($jkt, $decoded['sub']);
        $this->assertSame($jkt, $decoded['jkt']);
        $this->assertContains('read', (array) $decoded['scp']);
        $this->assertNotSame('tampered', $decoded['sub']);
        $this->assertNotSame('https://evil.example', $decoded['iss']);
        $this->assertGreaterThan(time(), (int) $decoded['exp']);
    }
}
