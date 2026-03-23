<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Support;

final class Base64Url
{
    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function decode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - strlen($data) % 4) % 4, '=');

        return base64_decode($padded, strict: true) ?: '';
    }
}
