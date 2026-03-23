<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Tests\Unit;

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Labrodev\Dpop\Exceptions\InvalidDPopProofException;
use Labrodev\Dpop\Services\DPopVerifier;
use Labrodev\Dpop\Tests\Concerns\GeneratesDPopProofs;
use Labrodev\Dpop\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DPopVerifierTest extends TestCase
{
    use GeneratesDPopProofs;

    private DPopVerifier $verifier;

    /** @var array<string,string> */
    private array $publicJwk;

    private string $privateKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = $this->app->make(DPopVerifier::class);

        ['privateKey' => $this->privateKeyPem, 'publicJwk' => $this->publicJwk] = $this->generateEcKeyPair();
    }

    private function makeValidRequest(string $url = 'https://example.com/resource', string $method = 'GET'): Request
    {
        $bearer = $this->buildBearerJwt(publicJwk: $this->publicJwk);
        $proof = $this->buildDPopProof(
            htm: $method,
            htu: $url,
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $request = Request::create(uri: $url, method: $method);
        $request->headers->set('Authorization', "Bearer {$bearer}");
        $request->headers->set('DPoP', $proof);

        return $request;
    }

    #[Test]
    public function it_passes_all_13_steps_for_valid_request(): void
    {
        $request = $this->makeValidRequest();

        $this->verifier->verify(request: $request);

        $this->assertNotNull($request->attributes->get('dpop_jwt'));
    }

    #[Test]
    public function step1_rejects_missing_authorization_header(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.1');

        $request = Request::create(uri: 'https://example.com/resource', method: 'GET');

        $this->verifier->verify(request: $request);
    }

    #[Test]
    public function step1_rejects_non_bearer_authorization(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.1');

        $request = Request::create(uri: 'https://example.com/resource', method: 'GET');
        $request->headers->set('Authorization', 'Basic abc123');

        $this->verifier->verify(request: $request);
    }

    #[Test]
    public function step2_rejects_invalid_jwt_signature(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.2');

        $request = Request::create(uri: 'https://example.com/resource', method: 'GET');
        $request->headers->set('Authorization', 'Bearer invalid.jwt.token');
        $request->headers->set('DPoP', 'some.proof.here');

        $this->verifier->verify(request: $request);
    }

    #[Test]
    public function step3_rejects_expired_jwt(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.3');

        $expiredJwt = $this->buildBearerJwt(
            payloadOverrides: ['exp' => time() - 10],
            publicJwk: $this->publicJwk,
        );

        $proof = $this->buildDPopProof(
            htm: 'GET',
            htu: 'https://example.com/resource',
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $request = Request::create(uri: 'https://example.com/resource', method: 'GET');
        $request->headers->set('Authorization', "Bearer {$expiredJwt}");
        $request->headers->set('DPoP', $proof);

        $this->verifier->verify(request: $request);
    }

    #[Test]
    public function step4_rejects_missing_jkt_claim(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.4');

        $jwtWithoutJkt = JWT::encode(
            alg: 'HS256',
            key: 'test-secret-key-for-unit-tests-only-64-chars-long-padding-here!',
            payload: ['exp' => time() + 3600, 'iat' => time(), 'sub' => 'test'],
        );

        $proof = $this->buildDPopProof(
            htm: 'GET',
            htu: 'https://example.com/resource',
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $request = Request::create(uri: 'https://example.com/resource', method: 'GET');
        $request->headers->set('Authorization', "Bearer {$jwtWithoutJkt}");
        $request->headers->set('DPoP', $proof);

        $this->verifier->verify(request: $request);
    }

    #[Test]
    public function step5_rejects_missing_dpop_proof_header(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.5');

        $bearer = $this->buildBearerJwt(publicJwk: $this->publicJwk);

        $request = Request::create(uri: 'https://example.com/resource', method: 'GET');
        $request->headers->set('Authorization', "Bearer {$bearer}");

        $this->verifier->verify(request: $request);
    }

    #[Test]
    public function step9_rejects_wrong_htm(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.9');

        $bearer = $this->buildBearerJwt(publicJwk: $this->publicJwk);

        // Proof says POST but request is GET
        $proof = $this->buildDPopProof(
            htm: 'POST',
            htu: 'https://example.com/resource',
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $request = Request::create(uri: 'https://example.com/resource', method: 'GET');
        $request->headers->set('Authorization', "Bearer {$bearer}");
        $request->headers->set('DPoP', $proof);

        $this->verifier->verify(request: $request);
    }

    #[Test]
    public function step10_rejects_wrong_htu(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.10');

        $bearer = $this->buildBearerJwt(publicJwk: $this->publicJwk);

        // Proof says different URL
        $proof = $this->buildDPopProof(
            htm: 'GET',
            htu: 'https://other.example.com/resource',
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $request = Request::create(uri: 'https://example.com/resource', method: 'GET');
        $request->headers->set('Authorization', "Bearer {$bearer}");
        $request->headers->set('DPoP', $proof);

        $this->verifier->verify(request: $request);
    }

    #[Test]
    public function step11_rejects_iat_outside_clock_skew(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.11');

        $bearer = $this->buildBearerJwt(publicJwk: $this->publicJwk);

        $proof = $this->buildDPopProof(
            htm: 'GET',
            htu: 'https://example.com/resource',
            payloadOverrides: ['iat' => time() - 120], // 2 minutes ago, beyond 30s skew
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $request = Request::create(uri: 'https://example.com/resource', method: 'GET');
        $request->headers->set('Authorization', "Bearer {$bearer}");
        $request->headers->set('DPoP', $proof);

        $this->verifier->verify(request: $request);
    }

    #[Test]
    public function step12_rejects_replayed_jti(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.12');

        $bearer = $this->buildBearerJwt(publicJwk: $this->publicJwk);

        $proof = $this->buildDPopProof(
            htm: 'GET',
            htu: 'https://example.com/resource',
            payloadOverrides: ['jti' => 'fixed-jti-for-replay-test'],
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $makeRequest = function () use ($bearer, $proof): Request {
            $r = Request::create(uri: 'https://example.com/resource', method: 'GET');
            $r->headers->set('Authorization', "Bearer {$bearer}");
            $r->headers->set('DPoP', $proof);

            return $r;
        };

        // First request passes
        $this->verifier->verify(request: $makeRequest());

        // Second with same JTI is rejected
        $this->verifier->verify(request: $makeRequest());
    }

    #[Test]
    public function step13_rejects_wrong_jkt(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.13');

        // Bearer bound to a different key's thumbprint
        ['publicJwk' => $otherJwk] = $this->generateEcKeyPair();
        $bearer = $this->buildBearerJwt(publicJwk: $otherJwk);

        // Proof signed with our key pair
        $proof = $this->buildDPopProof(
            htm: 'GET',
            htu: 'https://example.com/resource',
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $request = Request::create(uri: 'https://example.com/resource', method: 'GET');
        $request->headers->set('Authorization', "Bearer {$bearer}");
        $request->headers->set('DPoP', $proof);

        $this->verifier->verify(request: $request);
    }

    #[Test]
    public function step3_rejects_jwt_with_no_exp_claim(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.3');

        $jwtWithoutExp = JWT::encode(
            alg: 'HS256',
            key: 'test-secret-key-for-unit-tests-only-64-chars-long-padding-here!',
            payload: ['iat' => time(), 'jkt' => 'some-jkt', 'sub' => 'some-jkt'],
        );

        $proof = $this->buildDPopProof(
            htm: 'GET',
            htu: 'https://example.com/resource',
            privateKeyPem: $this->privateKeyPem,
            publicJwk: $this->publicJwk,
        );

        $request = Request::create(uri: 'https://example.com/resource', method: 'GET');
        $request->headers->set('Authorization', "Bearer {$jwtWithoutExp}");
        $request->headers->set('DPoP', $proof);

        $this->verifier->verify(request: $request);
    }
}
