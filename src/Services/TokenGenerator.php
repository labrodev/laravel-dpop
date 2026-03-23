<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Services;

use Firebase\JWT\JWT;
use Labrodev\Dpop\Contracts\DPopTokenGeneratorInterface;
use Labrodev\Dpop\Data\IssuedToken;
use Labrodev\Dpop\Data\TokenRequestData;
use Labrodev\Dpop\Support\Jwk;

final class TokenGenerator implements DPopTokenGeneratorInterface
{
    public function generate(TokenRequestData $tokenRequestData): IssuedToken
    {
        $jkt = Jwk::thumbprint(jwk: $tokenRequestData->jwk);

        /** @var string $secret */
        $secret = config('dpop.jwt.secret');

        /** @var string $algorithm */
        $algorithm = config('dpop.jwt.algorithm', 'HS256');

        /** @var int $lifetime */
        $lifetime = (int) config('dpop.jwt.lifetime', 3600);

        $now = time();

        $delimiter = str_contains($tokenRequestData->scope, ',') ? ',' : ' ';

        $scopes = array_values(array_unique(array_filter(
            array_map('trim', explode($delimiter, $tokenRequestData->scope)),
            static fn (string $s): bool => $s !== '',
        )));

        $payload = [
            'exp' => $now + $lifetime,
            'iat' => $now,
            'iss' => config('app.url'),
            'jkt' => $jkt,
            'scp' => $scopes,
            'sub' => $jkt,
        ];

        $token = JWT::encode(
            alg: $algorithm,
            key: $secret,
            payload: $payload,
        );

        return new IssuedToken(
            expiresIn: $lifetime,
            value: $token,
        );
    }
}
