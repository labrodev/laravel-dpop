<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Support;

use Labrodev\Dpop\Exceptions\InvalidDPopProofException;

final class DPopProofParser
{
    /**
     * Parse a compact JWS DPoP proof into its decoded header and payload.
     *
     * @return array{header: array<string,mixed>, payload: array<string,mixed>}
     *
     * @throws InvalidDPopProofException
     */
    public static function parse(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidDPopProofException(errorCode: 'D.E.5');
        }

        $header = json_decode(associative: true, json: Base64Url::decode($parts[0]));
        $payload = json_decode(associative: true, json: Base64Url::decode($parts[1]));

        if (! is_array($header) || ! is_array($payload)) {
            throw new InvalidDPopProofException(errorCode: 'D.E.5');
        }

        if (($header['typ'] ?? '') !== 'dpop+jwt') {
            throw new InvalidDPopProofException(errorCode: 'D.E.6');
        }

        if (($header['alg'] ?? '') !== 'ES256') {
            throw new InvalidDPopProofException(errorCode: 'D.E.7');
        }

        return [
            'header' => $header,
            'payload' => $payload,
        ];
    }
}
