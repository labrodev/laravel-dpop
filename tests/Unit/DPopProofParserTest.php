<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Tests\Unit;

use Labrodev\Dpop\Exceptions\InvalidDPopProofException;
use Labrodev\Dpop\Support\Base64Url;
use Labrodev\Dpop\Support\DPopProofParser;
use Labrodev\Dpop\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DPopProofParserTest extends TestCase
{
    #[Test]
    public function it_rejects_non_three_part_token(): void
    {
        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.5');

        DPopProofParser::parse(token: 'only.two');
    }

    #[Test]
    public function it_rejects_wrong_typ(): void
    {
        $header = Base64Url::encode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = Base64Url::encode((string) json_encode(['iat' => time()]));

        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.6');

        DPopProofParser::parse(token: "{$header}.{$payload}.fakesig");
    }

    #[Test]
    public function it_rejects_wrong_alg(): void
    {
        $header = Base64Url::encode((string) json_encode(['typ' => 'dpop+jwt', 'alg' => 'RS256']));
        $payload = Base64Url::encode((string) json_encode(['iat' => time()]));

        $this->expectException(InvalidDPopProofException::class);
        $this->expectExceptionMessage('D.E.7');

        DPopProofParser::parse(token: "{$header}.{$payload}.fakesig");
    }

    #[Test]
    public function it_parses_valid_header_and_payload(): void
    {
        $header = Base64Url::encode((string) json_encode(['typ' => 'dpop+jwt', 'alg' => 'ES256', 'jwk' => []]));
        $payload = Base64Url::encode((string) json_encode(['htm' => 'POST', 'htu' => 'https://example.com/token', 'iat' => time(), 'jti' => 'abc123']));

        $result = DPopProofParser::parse(token: "{$header}.{$payload}.fakesig");

        $this->assertSame('dpop+jwt', $result['header']['typ']);
        $this->assertSame('ES256', $result['header']['alg']);
        $this->assertSame('POST', $result['payload']['htm']);
    }
}
