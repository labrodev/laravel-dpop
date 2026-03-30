<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class TokenRequestData extends Data
{
    /**
     * @param  array<string,mixed>  $jwk  EC P-256 public key (kty/crv/x/y required; d prohibited)
     * @param  string  $scope  Space-separated or comma-separated scopes
     * @param  array<string,mixed>  $extraClaims  Optional claims merged into the JWT (cannot override standard claims)
     */
    public function __construct(
        public array $jwk,
        public string $scope,
        #[MapInputName('extra_claims')]
        public array $extraClaims = [],
    ) {}

    /**
     * @return array<string,mixed>
     */
    public static function rules(): array
    {
        return [
            'jwk' => ['required', 'array'],
            'jwk.kty' => ['required', 'string'],
            'jwk.crv' => ['required', 'string'],
            'jwk.x' => ['required', 'string'],
            'jwk.y' => ['required', 'string'],
            'jwk.d' => ['prohibited'],
            'jwk.alg' => ['nullable', 'string'],
            'jwk.use' => ['nullable', 'string'],
            'scope' => ['required', 'string'],
            'extra_claims' => ['nullable', 'array'],
            'extra_claims.*' => ['nullable'],
        ];
    }
}
