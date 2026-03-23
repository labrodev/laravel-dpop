<?php

declare(strict_types=1);

namespace Labrodev\Dpop\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Labrodev\Dpop\Tests\Concerns\GeneratesDPopProofs;
use Labrodev\Dpop\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class IdempotencyTest extends TestCase
{
    use GeneratesDPopProofs;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/orders', fn () => response()->json(['order_id' => 42]))
            ->middleware('dpop.idempotency');
    }

    #[Test]
    public function it_passes_first_request_through(): void
    {
        $response = $this->postJson('/orders', ['item' => 'widget'], [
            'Idempotency-Key' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $response->assertOk();
        $response->assertJsonPath('order_id', 42);
    }

    #[Test]
    public function it_returns_cached_response_on_replay(): void
    {
        $key = '550e8400-e29b-41d4-a716-446655440001';
        $body = ['item' => 'widget'];
        $headers = ['Idempotency-Key' => $key];

        $this->postJson('/orders', $body, $headers)->assertOk();

        $response = $this->postJson('/orders', $body, $headers);

        $response->assertOk();
        $response->assertHeader('Idempotency-Replayed', 'true');
    }

    #[Test]
    public function it_returns_409_on_conflict_with_different_body(): void
    {
        $key = '550e8400-e29b-41d4-a716-446655440002';

        $this->postJson('/orders', ['item' => 'widget'], ['Idempotency-Key' => $key])
            ->assertOk();

        $response = $this->postJson('/orders', ['item' => 'different-item'], ['Idempotency-Key' => $key]);

        $response->assertConflict();
        $response->assertJsonPath('error', 'E.I.2');
    }

    #[Test]
    public function it_returns_422_for_missing_idempotency_key(): void
    {
        $response = $this->postJson('/orders', ['item' => 'widget']);

        $response->assertUnprocessable();
        $response->assertJsonPath('error', 'E.I.1');
    }

    #[Test]
    public function it_skips_idempotency_check_for_safe_methods(): void
    {
        Route::get('/orders', fn () => response()->json(['orders' => []]))
            ->middleware('dpop.idempotency');

        $response = $this->getJson('/orders');

        $response->assertOk();
    }
}
