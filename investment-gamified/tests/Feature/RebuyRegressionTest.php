<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\Stock;
use App\Models\User;
use App\Services\PortfolioService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression: re-buying a stock after selling the entire position must succeed.
 *
 * The `has_quantity` global scope hides the zero-quantity row left behind by a
 * full sell. Before the fix, the buy-side lookup could not see that row, tried
 * to INSERT a duplicate, and hit unique(user_id, stock_id) -> uncaught 500.
 * The fix queries the unscoped row and updates it in place.
 */
class RebuyRegressionTest extends TestCase
{
    protected PortfolioService $portfolioService;

    protected User $user;

    protected Stock $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->portfolioService = app(PortfolioService::class);

        $this->user = User::factory()->create([
            'balance' => 10000.00,
            'level' => 1,
            'experience_points' => 0,
        ]);

        $this->stock = Stock::create([
            'symbol' => 'RBUY',
            'name' => 'Rebuy Test',
            'description' => 'Stock for rebuy regression tests',
            'current_price' => 100.00,
            'change_percentage' => 0.00,
        ]);
    }

    public function test_rebuy_after_full_sell_succeeds_without_duplicate_row(): void
    {
        // Buy 5.
        $buy = $this->portfolioService->buyStock($this->user, 'RBUY', 5);
        $this->assertTrue($buy['success'], 'Initial buy should succeed');

        // Sell all 5 -> leaves a hidden zero-quantity row.
        $sell = $this->portfolioService->sellStock($this->user, 'RBUY', 5);
        $this->assertTrue($sell['success'], 'Full sell should succeed');

        // The physical row still exists with quantity 0 (hidden by the global scope).
        $hiddenRows = DB::table('portfolios')
            ->where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->count();
        $this->assertEquals(1, $hiddenRows, 'A single zero-quantity row should remain');

        // Re-buy 3: must reuse the existing row, not 500 on a unique violation.
        $rebuy = $this->portfolioService->buyStock($this->user, 'RBUY', 3);
        $this->assertTrue($rebuy['success'], 'Re-buy after full sell must succeed (no unique-constraint 500)');

        // Exactly one physical row for this user/stock.
        $totalRows = DB::table('portfolios')
            ->where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->count();
        $this->assertEquals(1, $totalRows, 'Re-buy must not create a duplicate portfolio row');

        // Visible holding reflects the re-buy: quantity 3 at average price 100.
        $portfolio = Portfolio::where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->first();

        $this->assertNotNull($portfolio, 'Re-bought holding should be visible again');
        $this->assertEquals(3, $portfolio->quantity);
        $this->assertEquals(100.00, (float) $portfolio->average_price);
    }
}
