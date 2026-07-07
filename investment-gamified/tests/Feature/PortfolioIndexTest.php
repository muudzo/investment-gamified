<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\Stock;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortfolioIndexTest extends TestCase
{
    public function test_portfolio_index_paginates_and_computes_sql()
    {
        $user = User::factory()->create();

        // 60 DISTINCT stocks, one portfolio row each — respects unique(user_id, stock_id).
        $stocks = Stock::factory()->count(60)->create(['current_price' => 10]);

        foreach ($stocks as $stock) {
            Portfolio::create([
                'user_id' => $user->id,
                'stock_id' => $stock->id,
                'quantity' => 1,
                'average_price' => 5,
            ]);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/portfolio?page=1&per_page=50')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 60)
            ->assertJsonStructure(['success', 'data', 'meta']);
    }
}
