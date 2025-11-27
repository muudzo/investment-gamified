<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalStockRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_query_param()
    {
        $response = $this->getJson('/api/external/stocks/search');

        $response->assertStatus(422);
    }
}
