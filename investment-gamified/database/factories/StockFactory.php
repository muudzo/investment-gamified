<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stock>
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function definition(): array
    {
        return [
            'symbol' => strtoupper(fake()->unique()->lexify('????')),
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'kid_friendly_description' => fake()->sentence(),
            'fun_fact' => fake()->sentence(),
            'category' => fake()->randomElement(['Tech', 'Food', 'Entertainment', 'Finance', 'Health']),
            'current_price' => fake()->randomFloat(2, 5, 500),
            'change_percentage' => fake()->randomFloat(2, -5, 5),
            'logo_url' => null,
        ];
    }
}
