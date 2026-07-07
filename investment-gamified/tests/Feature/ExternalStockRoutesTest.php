<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExternalStockRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_search_requires_query_param()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/external/stocks/search');

        $response->assertStatus(422);
    }

    public function test_search_rejects_blank_query_param()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/external/stocks/search?q='.urlencode('   '));

        $response->assertStatus(422);
    }

    public function test_search_rejects_overly_long_query_param()
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/external/stocks/search?q='.str_repeat('a', 51));

        $response->assertStatus(422);
    }

    public function test_search_accepts_valid_query_and_returns_provider_results()
    {
        Sanctum::actingAs(User::factory()->create());

        Http::fake([
            '*' => Http::response(['bestMatches' => [['1. symbol' => 'AAPL', '2. name' => 'Apple Inc.']]], 200),
        ]);

        $response = $this->getJson('/api/external/stocks/search?q=apple');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_search_requires_authentication()
    {
        $response = $this->getJson('/api/external/stocks/search?q=apple');

        $response->assertStatus(401);
    }
}
