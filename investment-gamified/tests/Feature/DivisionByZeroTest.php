<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\Stock;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DivisionByZeroTest extends TestCase
{
    public function test_profit_loss_percentage_is_safe_when_average_price_zero()
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['current_price' => 10]);

        Portfolio::create([
            'user_id' => $user->id,
            'stock_id' => $stock->id,
            'quantity' => 1,
            'average_price' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/portfolio');
        $response->assertStatus(200);
        $data = $response->json('data')[0] ?? null;
        $this->assertNotNull($data);
        $this->assertEquals(0, $data['profit_loss_percentage']);
    }
}
