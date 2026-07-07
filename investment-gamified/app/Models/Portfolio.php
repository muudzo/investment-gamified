<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Portfolio extends Model
{
    use HasFactory;

    /**
     * Name of the global scope that hides fully-sold (zero-quantity) holdings.
     *
     * IMPORTANT: Any code that must operate on the *physical* row — e.g. re-buying
     * after a full sell, or an atomic quantity mutation — MUST query with
     * ->withoutGlobalScope(self::SCOPE_HAS_QUANTITY). Otherwise a leftover
     * zero-quantity row is invisible and a re-insert violates unique(user_id, stock_id).
     */
    public const SCOPE_HAS_QUANTITY = 'has_quantity';

    protected $fillable = [
        'user_id',
        'stock_id',
        'quantity',
        'average_price',
    ];

    protected $casts = [
        'average_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Exclude zero-quantity entries by default at the model level so
        // API endpoints and queries do not return meaningless holdings.
        static::addGlobalScope(self::SCOPE_HAS_QUANTITY, function (Builder $builder): void {
            $builder->where('quantity', '>', 0);
        });

        // LEDGER AUTHORITY: A holding may be established directly (seed data,
        // administrative import, tests) rather than through the buy flow. When
        // that happens we record an opening-balance entry in the append-only
        // audit ledger so the ledger always reconstructs to the true quantity
        // (sum(buy deltas) - sum(sell deltas) == portfolio.quantity).
        //
        // The trade flow (PortfolioService) always inserts the row at quantity 0
        // first and applies the delta afterwards, so this hook is a no-op there
        // and never double-counts a buy.
        static::created(function (Portfolio $portfolio): void {
            if ((int) $portfolio->quantity > 0) {
                PortfolioAudit::create([
                    'user_id' => $portfolio->user_id,
                    'stock_id' => $portfolio->stock_id,
                    'type' => 'buy',
                    'quantity' => (int) $portfolio->quantity,
                    'price' => (string) $portfolio->average_price,
                    'total_amount' => bcmul((string) $portfolio->average_price, (string) (int) $portfolio->quantity, 2),
                    'portfolio_snapshot' => json_encode([
                        'portfolio' => [
                            'quantity' => (int) $portfolio->quantity,
                            'average_price' => (float) $portfolio->average_price,
                        ],
                        'delta' => [
                            'quantity_change' => (int) $portfolio->quantity,
                            'type' => 'buy',
                        ],
                    ], JSON_THROW_ON_ERROR),
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
