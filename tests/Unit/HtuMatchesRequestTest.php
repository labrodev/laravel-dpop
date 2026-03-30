<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Tests\Unit;

use Illuminate\Http\Request;
use Labrodev\Dpop\Support\HtuMatchesRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HtuMatchesRequestTest extends TestCase
{
    #[Test]
    public function it_matches_when_query_string_order_differs_but_canonical_urls_are_equal(): void
    {
        $request = Request::create('https://example.test/api/schedule?b=2&a=1', 'GET');
        $htu = 'https://example.test/api/schedule?a=1&b=2';

        $this->assertTrue(HtuMatchesRequest::matches($htu, $request));
    }

    #[Test]
    public function it_rejects_different_paths(): void
    {
        $request = Request::create('https://example.test/api/schedule?a=1', 'GET');
        $htu = 'https://example.test/api/other?a=1';

        $this->assertFalse(HtuMatchesRequest::matches($htu, $request));
    }
}
