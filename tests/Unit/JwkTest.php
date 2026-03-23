<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Tests\Unit;

use Labrodev\Dpop\Support\Jwk;
use Labrodev\Dpop\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class JwkTest extends TestCase
{
    #[Test]
    public function it_computes_rfc7638_thumbprint_deterministically(): void
    {
        $jwk = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => 'f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU',
            'y' => 'x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0',
        ];

        $thumbprint1 = Jwk::thumbprint(jwk: $jwk);
        $thumbprint2 = Jwk::thumbprint(jwk: $jwk);

        $this->assertSame($thumbprint1, $thumbprint2);
    }

    #[Test]
    public function it_produces_different_thumbprints_for_different_keys(): void
    {
        $jwk1 = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => 'f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU',
            'y' => 'x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0',
        ];

        $jwk2 = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'y' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        ];

        $this->assertNotSame(
            Jwk::thumbprint(jwk: $jwk1),
            Jwk::thumbprint(jwk: $jwk2),
        );
    }

    #[Test]
    public function it_ignores_extra_fields_when_computing_thumbprint(): void
    {
        $base = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => 'f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU',
            'y' => 'x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0',
        ];

        $withExtra = array_merge($base, ['alg' => 'ES256', 'use' => 'sig']);

        $this->assertSame(
            Jwk::thumbprint(jwk: $base),
            Jwk::thumbprint(jwk: $withExtra),
        );
    }

    #[Test]
    public function it_returns_base64url_encoded_string(): void
    {
        $jwk = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => 'f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU',
            'y' => 'x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0',
        ];

        $thumbprint = Jwk::thumbprint(jwk: $jwk);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $thumbprint);
        $this->assertStringNotContainsString('=', $thumbprint);
    }
}
