<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PortfolioAudit;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PortfolioAudit>
 */
class PortfolioAuditFactory extends Factory
{
    protected $model = PortfolioAudit::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 50);
        $price = fake()->randomFloat(4, 5, 500);

        return [
            'user_id' => User::factory(),
            'stock_id' => Stock::factory(),
            'type' => fake()->randomElement(['buy', 'sell']),
            'quantity' => $quantity,
            'price' => $price,
            'total_amount' => $quantity * $price,
            'portfolio_snapshot' => json_encode([
                'quantity_change' => $quantity,
                'average_price_after' => $price,
            ]),
        ];
    }
}
