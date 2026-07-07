<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExternalSymbolValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // None of these tests should ever result in a real external HTTP
        // call - validation/existence guards must short-circuit first.
        Http::preventStrayRequests();
    }

    public function test_invalid_symbol_rejected()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/external/stocks/quote/../../etc');
        $response->assertStatus(422);
    }

    public function test_unknown_symbol_returns_404()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/external/stocks/quote/FOOBAR');
        $response->assertStatus(404);
    }

    public function test_quote_rejects_malformed_symbol()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/external/stocks/quote/'.urlencode('bad!symbol'));
        $response->assertStatus(422);
    }

    public function test_quote_normalizes_case_before_lookup()
    {
        Sanctum::actingAs(User::factory()->create());
        Stock::factory()->create(['symbol' => 'AAPL']);

        Http::fake([
            '*' => Http::response(['Global Quote' => ['01. symbol' => 'AAPL', '05. price' => '100.00']], 200),
        ]);

        $response = $this->getJson('/api/external/stocks/quote/aapl');
        $response->assertStatus(200);
    }

    public function test_history_rejects_malformed_symbol()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/external/stocks/history/'.urlencode('bad!symbol'));
        $response->assertStatus(422);
    }

    public function test_history_rejects_unknown_symbol()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/external/stocks/history/FOOBAR');
        $response->assertStatus(404);
    }

    public function test_history_clamps_out_of_range_days()
    {
        Sanctum::actingAs(User::factory()->create());
        Stock::factory()->create(['symbol' => 'AAPL']);

        Http::fake([
            '*' => Http::response(['Time Series (Daily)' => ['2024-01-01' => []]], 200),
        ]);

        $response = $this->getJson('/api/external/stocks/history/AAPL?days=999999');
        $response->assertStatus(200);
    }

    public function test_profile_rejects_malformed_symbol()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/external/stocks/profile/'.urlencode('bad!symbol'));
        $response->assertStatus(422);
    }

    public function test_profile_rejects_unknown_symbol()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/external/stocks/profile/FOOBAR');
        $response->assertStatus(404);
    }

    public function test_profile_normalizes_case_before_lookup()
    {
        Sanctum::actingAs(User::factory()->create());
        Stock::factory()->create(['symbol' => 'AAPL']);

        Http::fake([
            '*' => Http::response([['symbol' => 'AAPL', 'companyName' => 'Apple Inc.']], 200),
        ]);

        $response = $this->getJson('/api/external/stocks/profile/aapl');
        $response->assertStatus(200);
    }
}
