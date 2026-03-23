<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Labrodev\Dpop\Data\IssuedToken;

/**
 * @mixin IssuedToken
 */
final class TokenResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'type' => 'token',
                'attributes' => [
                    'expires_in' => $this->expiresIn,
                    'token' => $this->value,
                ],
            ],
        ];
    }
}
