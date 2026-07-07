<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portfolio;
use App\Models\PortfolioAudit;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PortfolioService
{
    private const ERROR_INVALID_QUANTITY = 'Quantity must be greater than zero';

    private const ERROR_STOCK_NOT_FOUND = 'Stock not found';

    private const ERROR_INSUFFICIENT_BALANCE = 'Insufficient balance';

    private const ERROR_INSUFFICIENT_STOCK = 'Insufficient stock quantity';

    private const MAX_RETRIES = 3;

    /** Monetary scale (2 decimal places). All money math uses bcmath at this scale. */
    private const MONEY_SCALE = 2;

    /**
     * ARCHITECTURE: All balance mutations happen atomically in SQL as a single
     * UPDATE guarded by balance_version. Monetary amounts are computed with bcmath
     * (never PHP float) and passed to SQL via bound parameters — never string
     * interpolation — so there is no float-precision drift and no injection surface.
     *
     * CONCURRENCY: Optimistic versioning via balance_version. On a version/quantity
     * collision the transaction throws a RuntimeException and is retried up to
     * MAX_RETRIES times with random backoff. The guarded UPDATEs
     * (WHERE balance_version = cv AND balance >= cost) for the balance and
     * (WHERE quantity >= q) for the holding prevent double-spend and over-sell
     * even without retries.
     *
     * See: PRODUCTION_SCALE_FIXES_GUIDE.md "Explicit Money Representation Contract"
     *      and "Optimistic Locking Justification"
     */
    public function buyStock(User $user, string $stockSymbol, int $quantity): array
    {
        if ($quantity <= 0) {
            return $this->errorResponse(self::ERROR_INVALID_QUANTITY);
        }

        $stock = $this->findStockBySymbol($stockSymbol);
        if ($stock === null) {
            return $this->errorResponse(self::ERROR_STOCK_NOT_FOUND);
        }

        $totalCost = $this->money($stock->current_price, $quantity);
        $xp = (int) Config::get('game.xp.buy_reward', 10);
        $baseXp = (int) Config::get('game.xp.level_up_base', 1000);

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $stock, $quantity, $totalCost, $xp, $baseXp) {
                    $current = DB::table('users')
                        ->where('id', $user->id)
                        ->first(['id', 'balance', 'balance_version']);

                    if (! $current) {
                        return $this->errorResponse('User not found');
                    }

                    if (bccomp((string) $current->balance, $totalCost, self::MONEY_SCALE) < 0) {
                        return $this->errorResponse(self::ERROR_INSUFFICIENT_BALANCE);
                    }

                    $cv = (int) ($current->balance_version ?? 1);
                    $this->debitBalanceOrConflict($user->id, $cv, $totalCost, $xp, $baseXp);

                    $portfolio = $this->upsertPortfolio($user->id, (int) $stock->id, $quantity, $totalCost);

                    Transaction::create([
                        'user_id' => $user->id,
                        'stock_id' => $stock->id,
                        'type' => 'buy',
                        'quantity' => $quantity,
                        'price' => (string) $stock->current_price,
                        'total_amount' => $totalCost,
                    ]);

                    $this->createAuditAndCheckpoint($user, $stock, $portfolio, 'buy', $quantity, (string) $stock->current_price, $totalCost);
                    $this->flushLeaderboardCache();

                    return ['success' => true, 'message' => 'Stock purchased successfully', 'data' => ['xp_earned' => $xp]];
                });
            } catch (\RuntimeException $e) {
                if ($attempt < self::MAX_RETRIES) {
                    usleep(random_int(50, 200) * 1000);

                    continue;
                }

                return $this->logAndFail('buy', $user->id, $stockSymbol, $quantity, $e);
            }
        }

        return $this->errorResponse('Trade failed.');
    }

    public function sellStock(User $user, string $stockSymbol, int $quantity): array
    {
        if ($quantity <= 0) {
            return $this->errorResponse(self::ERROR_INVALID_QUANTITY);
        }

        $stock = $this->findStockBySymbol($stockSymbol);
        if ($stock === null) {
            return $this->errorResponse(self::ERROR_STOCK_NOT_FOUND);
        }

        $xp = (int) Config::get('game.xp.sell_reward', 15);
        $baseXp = (int) Config::get('game.xp.level_up_base', 1000);

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $stock, $quantity, $xp, $baseXp) {
                    // Read the physical row (bypass the zero-quantity global scope) for the
                    // user-facing "you don't own enough" check.
                    $portfolio = Portfolio::withoutGlobalScope(Portfolio::SCOPE_HAS_QUANTITY)
                        ->where('user_id', $user->id)
                        ->where('stock_id', $stock->id)
                        ->first();

                    if ($portfolio === null || (int) $portfolio->quantity < $quantity) {
                        return $this->errorResponse(self::ERROR_INSUFFICIENT_STOCK);
                    }

                    $totalRevenue = $this->money($stock->current_price, $quantity);

                    $current = DB::table('users')
                        ->where('id', $user->id)
                        ->first(['balance_version']);

                    $cv = (int) ($current->balance_version ?? 1);
                    $this->creditBalanceOrConflict($user->id, $cv, $totalRevenue, $xp, $baseXp);

                    // Atomic, guarded holding decrement — mirrors the balance guard.
                    // If a concurrent sell already reduced the quantity below what we
                    // read, this affects 0 rows and we throw to trigger a retry
                    // (which will then re-check and return "insufficient stock").
                    $this->decrementHoldingOrConflict($user->id, (int) $stock->id, $quantity);

                    // Reflect the committed decrement on the in-memory model so the
                    // audit snapshot records the resulting quantity. (Sell never
                    // changes average_price.)
                    $portfolio->quantity = (int) $portfolio->quantity - $quantity;

                    Transaction::create([
                        'user_id' => $user->id,
                        'stock_id' => $stock->id,
                        'type' => 'sell',
                        'quantity' => $quantity,
                        'price' => (string) $stock->current_price,
                        'total_amount' => $totalRevenue,
                    ]);

                    $this->createAuditAndCheckpoint($user, $stock, $portfolio, 'sell', $quantity, (string) $stock->current_price, $totalRevenue);
                    $this->flushLeaderboardCache();

                    return ['success' => true, 'message' => 'Stock sold successfully', 'data' => ['proceeds' => $totalRevenue, 'xp_earned' => $xp]];
                });
            } catch (\RuntimeException $e) {
                if ($attempt < self::MAX_RETRIES) {
                    usleep(random_int(50, 200) * 1000);

                    continue;
                }

                return $this->logAndFail('sell', $user->id, $stockSymbol, $quantity, $e);
            }
        }

        return $this->errorResponse('Trade failed.');
    }

    private function findStockBySymbol(string $symbol): ?Stock
    {
        return Stock::where('symbol', $symbol)->first();
    }

    private function errorResponse(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    private function logAndFail(string $operation, int $userId, string $symbol, int $quantity, \Throwable $e): array
    {
        Log::error("Portfolio {$operation} operation failed after retries", [
            'user_id' => $userId,
            'symbol' => $symbol,
            'quantity' => $quantity,
            'exception' => $e->getMessage(),
        ]);

        return $this->errorResponse('Trade failed due to high contention. Please try again.');
    }

    /**
     * Precise monetary product (price * quantity) at MONEY_SCALE using bcmath.
     * Never uses PHP float arithmetic on monetary values.
     */
    private function money(float|string $price, int $quantity): string
    {
        return bcmul((string) $price, (string) $quantity, self::MONEY_SCALE);
    }

    /**
     * Debit the user balance in a single atomic, version-guarded UPDATE.
     * The monetary amount is bound (?), never interpolated. Throws on a
     * version/balance collision so the caller retries.
     */
    private function debitBalanceOrConflict(int $userId, int $currentVersion, string $amount, int $xp, int $baseXp): void
    {
        [$xpExpr, $levelExpr] = $this->xpSqlExpressions($xp, $baseXp);

        $affected = DB::update(
            'UPDATE users SET '
            .'balance = balance - ?, '
            ."experience_points = {$xpExpr}, "
            ."level = level + ({$levelExpr}), "
            .'balance_version = balance_version + 1 '
            .'WHERE id = ? AND balance_version = ? AND balance >= ?',
            [$amount, $userId, $currentVersion, $amount]
        );

        if ($affected === 0) {
            throw new \RuntimeException('Concurrency conflict updating user balance');
        }
    }

    /**
     * Credit the user balance in a single atomic, version-guarded UPDATE.
     * The monetary amount is bound (?), never interpolated.
     */
    private function creditBalanceOrConflict(int $userId, int $currentVersion, string $amount, int $xp, int $baseXp): void
    {
        [$xpExpr, $levelExpr] = $this->xpSqlExpressions($xp, $baseXp);

        $affected = DB::update(
            'UPDATE users SET '
            .'balance = balance + ?, '
            ."experience_points = {$xpExpr}, "
            ."level = level + ({$levelExpr}), "
            .'balance_version = balance_version + 1 '
            .'WHERE id = ? AND balance_version = ?',
            [$amount, $userId, $currentVersion]
        );

        if ($affected === 0) {
            throw new \RuntimeException('Concurrency conflict updating user balance');
        }
    }

    /**
     * Atomically decrement a holding, guarded by "quantity >= :q" so interleaved
     * sells can never oversell into a negative position. Throws on a 0-row result
     * so the caller retries (and then re-checks / reports insufficient stock).
     */
    private function decrementHoldingOrConflict(int $userId, int $stockId, int $quantity): void
    {
        $affected = DB::update(
            'UPDATE portfolios SET quantity = quantity - ?, updated_at = ? '
            .'WHERE user_id = ? AND stock_id = ? AND quantity >= ?',
            [$quantity, now()->format('Y-m-d H:i:s'), $userId, $stockId, $quantity]
        );

        if ($affected === 0) {
            throw new \RuntimeException('Concurrency conflict decrementing portfolio quantity');
        }
    }

    /**
     * Returns [xpNewExpr, levelIncExpr] as SQL CASE strings.
     * XP rolls over when experience_points + xp crosses level * baseXp.
     * Both inputs are cast to int by the caller, so these fragments contain no
     * external data and carry no injection surface (money is bound separately).
     */
    private function xpSqlExpressions(int $xp, int $baseXp): array
    {
        $xpNew = "CASE WHEN (experience_points + {$xp}) >= (level * {$baseXp}) THEN (experience_points + {$xp}) - (level * {$baseXp}) ELSE experience_points + {$xp} END";
        $levelUp = "CASE WHEN (experience_points + {$xp}) >= (level * {$baseXp}) THEN 1 ELSE 0 END";

        return [$xpNew, $levelUp];
    }

    /**
     * Upsert the buy-side portfolio row and recalculate the weighted average price.
     *
     * REBUY-SAFE: queries with ->withoutGlobalScope(has_quantity) so a leftover
     * zero-quantity row (left behind by a full sell) is found and updated instead
     * of triggering a duplicate INSERT that violates unique(user_id, stock_id).
     *
     * The row is always created at quantity 0 first, then the buy delta is applied.
     * This keeps the opening-balance ledger hook on the model a no-op for the trade
     * flow (see Portfolio::booted) so a buy is never double-counted in the ledger.
     *
     * NOTE: average_price is a display/informational field, so its weighted-average
     * recalculation uses PHP float here — the actual money debit happens precisely
     * in SQL via debitBalanceOrConflict().
     */
    private function upsertPortfolio(int $userId, int $stockId, int $quantity, string $totalCost): Portfolio
    {
        $portfolio = Portfolio::withoutGlobalScope(Portfolio::SCOPE_HAS_QUANTITY)
            ->where('user_id', $userId)
            ->where('stock_id', $stockId)
            ->first();

        if ($portfolio === null) {
            $portfolio = new Portfolio([
                'user_id' => $userId,
                'stock_id' => $stockId,
                'quantity' => 0,
                'average_price' => 0,
            ]);
            $portfolio->save();
        }

        $existingQuantity = (int) $portfolio->quantity;
        $newQuantity = $existingQuantity + $quantity;

        $portfolio->average_price = $newQuantity > 0
            ? (($portfolio->average_price * $existingQuantity) + (float) $totalCost) / $newQuantity
            : 0;
        $portfolio->quantity = $newQuantity;
        $portfolio->save();

        return $portfolio;
    }

    /**
     * Write an immutable audit delta and refresh the portfolio integrity checkpoint.
     *
     * AUDIT SNAPSHOT CONTRACT (JSON stored in portfolio_audit.portfolio_snapshot):
     * {
     *   "portfolio": { "quantity": <int resulting holding>, "average_price": <float> },
     *   "delta":     { "quantity_change": <+int buy | -int sell>, "type": "buy"|"sell" }
     * }
     * The checksum is sha256 of this exact JSON string and is stored on the
     * portfolio row so integrity can be re-verified against the ledger entry.
     */
    private function createAuditAndCheckpoint(
        User $user,
        Stock $stock,
        Portfolio $portfolio,
        string $type,
        int $quantity,
        string $price,
        string $totalAmount,
    ): void {
        $snapshot = json_encode([
            'portfolio' => [
                'quantity' => (int) $portfolio->quantity,
                'average_price' => (float) $portfolio->average_price,
            ],
            'delta' => [
                'quantity_change' => $type === 'buy' ? $quantity : -$quantity,
                'type' => $type,
            ],
        ], JSON_THROW_ON_ERROR);

        $audit = PortfolioAudit::create([
            'user_id' => $user->id,
            'stock_id' => $stock->id,
            'type' => $type,
            'quantity' => $quantity,
            'price' => $price,
            'total_amount' => $totalAmount,
            'portfolio_snapshot' => $snapshot,
        ]);

        $portfolio->ledger_checkpoint_id = $audit->id;
        $portfolio->checksum = hash('sha256', $snapshot);
        $portfolio->save();
    }

    private function flushLeaderboardCache(): void
    {
        try {
            Cache::tags(['leaderboard'])->flush();
        } catch (\Exception) {
            Log::debug('Cache tags not supported for leaderboard flush');
        }
    }
}
