<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Services;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key as HmacKey;
use Illuminate\Http\Request;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK as JoseJwk;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Labrodev\Dpop\Exceptions\InvalidDPopProofException;
use Labrodev\Dpop\Support\DPopProofParser;
use Labrodev\Dpop\Support\HtuMatchesRequest;
use Labrodev\Dpop\Support\JtiStore;
use Labrodev\Dpop\Support\Jwk;
use Throwable;

final class DPopVerifier
{
    private readonly JWSVerifier $jwsVerifier;

    private readonly CompactSerializer $serializer;

    public function __construct(private readonly JtiStore $jtiStore)
    {
        $this->jwsVerifier = new JWSVerifier(new AlgorithmManager([new ES256]));
        $this->serializer = new CompactSerializer;
    }

    /**
     * Perform all 13 RFC 9449 DPoP verification steps.
     * Stores the decoded JWT payload into $request->attributes as 'dpop_jwt'.
     *
     * @throws InvalidDPopProofException
     */
    public function verify(Request $request): void
    {
        // Step 1 — Authorization: Bearer present
        $auth = $request->header('Authorization');

        if (! $auth || ! str_starts_with((string) $auth, 'Bearer ')) {
            throw new InvalidDPopProofException(errorCode: 'D.E.1');
        }

        /** @var string $secret */
        $secret = config('dpop.jwt.secret');

        /** @var string $algorithm */
        $algorithm = config('dpop.jwt.algorithm', 'HS256');

        // Step 2 — JWT signature valid
        // Step 3 — JWT not expired (ExpiredException maps to D.E.3, all other failures to D.E.2)
        try {
            $decoded = (array) JWT::decode(
                jwt: substr((string) $auth, 7),
                keyOrKeyArray: new HmacKey(algorithm: $algorithm, keyMaterial: $secret),
            );
        } catch (ExpiredException) {
            throw new InvalidDPopProofException(errorCode: 'D.E.3');
        } catch (Throwable) {
            throw new InvalidDPopProofException(errorCode: 'D.E.2');
        }

        // Step 3 continued — explicit exp guard (firebase/php-jwt skips check when exp is absent)
        if (empty($decoded['exp']) || ((int) $decoded['exp']) < time()) {
            throw new InvalidDPopProofException(errorCode: 'D.E.3');
        }

        // Step 4 — jkt claim present
        if (empty($decoded['jkt']) || ! is_string($decoded['jkt'])) {
            throw new InvalidDPopProofException(errorCode: 'D.E.4');
        }

        /** @var string $proofHeader */
        $proofHeader = config('dpop.proof_header', 'DPoP');

        // Step 5 — DPoP proof header present
        $proofToken = $request->header($proofHeader);

        if (! $proofToken) {
            throw new InvalidDPopProofException(errorCode: 'D.E.5');
        }

        // Steps 5-7 — parse compact JWS, validate typ + alg via DPopProofParser
        ['header' => $header, 'payload' => $proof] = DPopProofParser::parse(token: (string) $proofToken);

        // Step 7 continued — EC P-256 key present in header
        /** @var array<string,mixed>|null $embeddedJwk */
        $embeddedJwk = $header['jwk'] ?? null;

        if (
            ! is_array($embeddedJwk)
            || ($embeddedJwk['kty'] ?? '') !== 'EC'
            || ($embeddedJwk['crv'] ?? '') !== 'P-256'
        ) {
            throw new InvalidDPopProofException(errorCode: 'D.E.7');
        }

        // Step 8 — JWS cryptographic signature valid
        try {
            $jws = $this->serializer->unserialize((string) $proofToken);
            $publicKey = new JoseJwk($embeddedJwk);

            if (! $this->jwsVerifier->verifyWithKey(jws: $jws, jwk: $publicKey, signature: 0)) {
                throw new InvalidDPopProofException(errorCode: 'D.E.8');
            }
        } catch (InvalidDPopProofException $e) {
            throw $e;
        } catch (Throwable) {
            throw new InvalidDPopProofException(errorCode: 'D.E.8');
        }

        // Step 9 — htm matches request method
        $htm = strtoupper((string) ($proof['htm'] ?? ''));

        if ($htm !== strtoupper($request->method())) {
            throw new InvalidDPopProofException(errorCode: 'D.E.9');
        }

        // Step 10 — htu matches fullUrl (NEVER url()); allow canonical equivalence (query order)
        $htu = (string) ($proof['htu'] ?? '');

        if (! HtuMatchesRequest::matches($htu, $request)) {
            throw new InvalidDPopProofException(errorCode: 'D.E.10');
        }

        // Step 11 — iat within clock skew
        $iat = (int) ($proof['iat'] ?? 0);
        $now = time();

        /** @var int $clockSkew */
        $clockSkew = (int) config('dpop.clock_skew', 30);

        if ($iat < $now - $clockSkew || $iat > $now + $clockSkew) {
            throw new InvalidDPopProofException(errorCode: 'D.E.11');
        }

        // Step 12 — jti not replayed
        $jti = (string) ($proof['jti'] ?? '');

        if ($jti === '') {
            throw new InvalidDPopProofException(errorCode: 'D.E.12');
        }

        /** @var int $jtiTtl */
        $jtiTtl = (int) config('dpop.jti_ttl', 600);

        if (! $this->jtiStore->store(jti: $jti, ttl: $jtiTtl)) {
            throw new InvalidDPopProofException(errorCode: 'D.E.12');
        }

        // Step 13 — JWK thumbprint equals jkt
        $calculatedJkt = Jwk::thumbprint(jwk: $embeddedJwk);

        if (! hash_equals(known_string: $decoded['jkt'], user_string: $calculatedJkt)) {
            throw new InvalidDPopProofException(errorCode: 'D.E.13');
        }

        $request->attributes->set('dpop_jwt', $decoded);
    }
}
