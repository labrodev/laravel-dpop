<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Support;

final class Jwk
{
    /**
     * Compute RFC 7638 JWK thumbprint for an EC P-256 public key.
     * Only crv/kty/x/y are included, sorted alphabetically, then SHA-256 hashed.
     *
     * @param  array<string,mixed>  $jwk
     */
    public static function thumbprint(array $jwk): string
    {
        $members = [
            'crv' => (string) ($jwk['crv'] ?? ''),
            'kty' => (string) ($jwk['kty'] ?? ''),
            'x' => (string) ($jwk['x'] ?? ''),
            'y' => (string) ($jwk['y'] ?? ''),
        ];

        ksort($members);

        $json = json_encode(flags: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, value: $members);

        return Base64Url::encode(hash(algo: 'sha256', binary: true, data: (string) $json));
    }
}
