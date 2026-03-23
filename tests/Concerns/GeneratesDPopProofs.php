<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Tests\Concerns;

use Firebase\JWT\JWT;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Labrodev\Dpop\Support\Base64Url;
use Labrodev\Dpop\Support\Jwk as JwkSupport;

trait GeneratesDPopProofs
{
    /**
     * @return array{privateKey: string, publicJwk: array<string,string>}
     */
    protected function generateEcKeyPair(): array
    {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        $details = openssl_pkey_get_details($key);
        $ecDetails = $details['ec'];

        $publicJwk = [
            'crv' => 'P-256',
            'kty' => 'EC',
            'x' => Base64Url::encode($ecDetails['x']),
            'y' => Base64Url::encode($ecDetails['y']),
        ];

        openssl_pkey_export($key, $privateKeyPem);

        return [
            'privateKey' => $privateKeyPem,
            'publicJwk' => $publicJwk,
        ];
    }

    /**
     * Build a signed DPoP proof JWS.
     *
     * @param  array<string,string>  $publicJwk
     * @param  array<string,mixed>  $payloadOverrides
     */
    protected function buildDPopProof(
        string $htm,
        string $htu,
        string $privateKeyPem,
        array $publicJwk,
        array $payloadOverrides = [],
    ): string {
        $algorithmManager = new AlgorithmManager([new ES256]);
        $jwsBuilder = new JWSBuilder($algorithmManager);
        $serializer = new CompactSerializer;

        $jwkPrivate = JWKFactory::createFromKey(key: $privateKeyPem);

        $payload = array_merge([
            'htm' => $htm,
            'htu' => $htu,
            'iat' => time(),
            'jti' => bin2hex(random_bytes(16)),
        ], $payloadOverrides);

        $protectedHeader = ['alg' => 'ES256', 'jwk' => $publicJwk, 'typ' => 'dpop+jwt'];

        $jws = $jwsBuilder
            ->create()
            ->withPayload((string) json_encode($payload))
            ->addSignature(protectedHeader: $protectedHeader, signatureKey: $jwkPrivate)
            ->build();

        return $serializer->serialize(jws: $jws, signatureIndex: 0);
    }

    /**
     * Build a valid Bearer JWT bound to the given JWK.
     *
     * @param  array<string,string>  $publicJwk
     * @param  array<string,mixed>  $payloadOverrides
     */
    protected function buildBearerJwt(
        array $publicJwk,
        array $payloadOverrides = [],
    ): string {
        $jkt = JwkSupport::thumbprint(jwk: $publicJwk);

        $payload = array_merge([
            'exp' => time() + 3600,
            'iat' => time(),
            'iss' => 'https://example.com',
            'jkt' => $jkt,
            'scp' => ['read'],
            'sub' => $jkt,
        ], $payloadOverrides);

        return JWT::encode(
            alg: 'HS256',
            key: 'test-secret-key-for-unit-tests-only-64-chars-long-padding-here!',
            payload: $payload,
        );
    }
}
