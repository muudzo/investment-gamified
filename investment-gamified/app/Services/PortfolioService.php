<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portfolio;
use App\Models\PortfolioAudit;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PortfolioService
{
    private const ERROR_INVALID_QUANTITY = 'Quantity must be greater than zero';
    private const ERROR_STOCK_NOT_FOUND = 'Stock not found';
    private const ERROR_INSUFFICIENT_BALANCE = 'Insufficient balance';
    private const ERROR_INSUFFICIENT_STOCK = 'Insufficient stock quantity';
    private const MAX_RETRIES = 3;

    /**
     * ARCHITECTURE: All balance mutations happen atomically in SQL via DB::raw().
     * PHP must never do float arithmetic on monetary values.
     *
     * CONCURRENCY: Optimistic versioning via balance_version.
     * On collision the transaction is retried up to MAX_RETRIES times with random
     * backoff. The DB constraint (balance >= 0, WHERE balance_version = cv)
     * prevents double-spend even without retries.
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

        $totalCost = $stock->current_price * $quantity;
        $xp     = (int) Config::get('game.xp.buy_reward', 10);
        $baseXp = (int) Config::get('game.xp.level_up_base', 1000);

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $result = DB::transaction(function () use ($user, $stock, $quantity, $totalCost, $xp, $baseXp) {
                    $current = DB::table('users')
                        ->where('id', $user->id)
                        ->first(['id', 'balance', 'experience_points', 'level', 'balance_version']);

                    if (!$current) {
                        return $this->errorResponse('User not found');
                    }

                    if ($current->balance < $totalCost) {
                        return $this->errorResponse(self::ERROR_INSUFFICIENT_BALANCE);
                    }

                    $cv = (int) ($current->balance_version ?? 1);
                    [$xpExpr, $levelExpr] = $this->xpSqlExpressions($xp, $baseXp);

                    $updated = DB::table('users')
                        ->where('id', $user->id)
                        ->where('balance_version', $cv)
                        ->where('balance', '>=', $totalCost)
                        ->update([
                            'balance'           => DB::raw("balance - {$totalCost}"),
                            'experience_points' => DB::raw($xpExpr),
                            'level'             => DB::raw("level + ({$levelExpr})"),
                            'balance_version'   => DB::raw('balance_version + 1'),
                        ]);

                    if ($updated === 0) {
                        throw new \RuntimeException('Concurrency conflict updating user balance');
                    }

                    $portfolio = $this->upsertPortfolio($user->id, $stock->id, $quantity, (float) $totalCost);

                    Transaction::create([
                        'user_id'      => $user->id,
                        'stock_id'     => $stock->id,
                        'type'         => 'buy',
                        'quantity'     => $quantity,
                        'price'        => $stock->current_price,
                        'total_amount' => $totalCost,
                    ]);

                    $this->createAuditAndCheckpoint($user, $stock, $portfolio, 'buy', $quantity, (float) $stock->current_price, (float) $totalCost);
                    $this->flushLeaderboardCache();

                    return ['success' => true, 'message' => 'Stock purchased successfully', 'data' => ['xp_earned' => $xp]];
                });

                return $result;
            } catch (\RuntimeException $e) {
                if ($attempt < self::MAX_RETRIES) {
                    usleep(random_int(50, 200) * 1000);
                    continue;
                }

                Log::error('Portfolio buy operation failed after retries', [
                    'user_id'   => $user->id,
                    'symbol'    => $stockSymbol,
                    'quantity'  => $quantity,
                    'exception' => $e->getMessage(),
                ]);

                return $this->errorResponse('Trade failed due to high contention. Please try again.');
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

        $xp     = (int) Config::get('game.xp.sell_reward', 15);
        $baseXp = (int) Config::get('game.xp.level_up_base', 1000);

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $result = DB::transaction(function () use ($user, $stock, $quantity, $xp, $baseXp) {
                    $portfolio = Portfolio::where('user_id', $user->id)
                        ->where('stock_id', $stock->id)
                        ->first();

                    if (!$portfolio || $portfolio->quantity < $quantity) {
                        return $this->errorResponse(self::ERROR_INSUFFICIENT_STOCK);
                    }

                    $totalRevenue = $stock->current_price * $quantity;

                    $current = DB::table('users')
                        ->where('id', $user->id)
                        ->first(['balance_version', 'experience_points', 'level']);

                    $cv = (int) ($current->balance_version ?? 1);
                    [$xpExpr, $levelExpr] = $this->xpSqlExpressions($xp, $baseXp);

                    $updated = DB::table('users')
                        ->where('id', $user->id)
                        ->where('balance_version', $cv)
                        ->update([
                            'balance'           => DB::raw("balance + {$totalRevenue}"),
                            'experience_points' => DB::raw($xpExpr),
                            'level'             => DB::raw("level + ({$levelExpr})"),
                            'balance_version'   => DB::raw('balance_version + 1'),
                        ]);

                    if ($updated === 0) {
                        throw new \RuntimeException('Concurrency conflict updating user balance');
                    }

                    $portfolio->quantity -= $quantity;
                    $portfolio->save();

                    Transaction::create([
                        'user_id'      => $user->id,
                        'stock_id'     => $stock->id,
                        'type'         => 'sell',
                        'quantity'     => $quantity,
                        'price'        => $stock->current_price,
                        'total_amount' => $totalRevenue,
                    ]);

                    $this->createAuditAndCheckpoint($user, $stock, $portfolio, 'sell', $quantity, (float) $stock->current_price, (float) $totalRevenue);
                    $this->flushLeaderboardCache();

                    return ['success' => true, 'message' => 'Stock sold successfully', 'data' => ['proceeds' => $totalRevenue, 'xp_earned' => $xp]];
                });

                return $result;
            } catch (\RuntimeException $e) {
                if ($attempt < self::MAX_RETRIES) {
                    usleep(random_int(50, 200) * 1000);
                    continue;
                }

                Log::error('Portfolio sell operation failed after retries', [
                    'user_id'   => $user->id,
                    'symbol'    => $stockSymbol,
                    'quantity'  => $quantity,
                    'exception' => $e->getMessage(),
                ]);

                return $this->errorResponse('Trade failed due to high contention. Please try again.');
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

    /**
     * Returns [xpNewExpr, levelIncExpr] as SQL CASE strings.
     * XP rolls over when experience_points + xp crosses level * baseXp.
     */
    private function xpSqlExpressions(int $xp, int $baseXp): array
    {
        $xpNew   = "CASE WHEN (experience_points + {$xp}) >= (level * {$baseXp}) THEN (experience_points + {$xp}) - (level * {$baseXp}) ELSE experience_points + {$xp} END";
        $levelUp = "CASE WHEN (experience_points + {$xp}) >= (level * {$baseXp}) THEN 1 ELSE 0 END";

        return [$xpNew, $levelUp];
    }

    /**
     * Upsert the portfolio row and recalculate the weighted average price in PHP.
     * NOTE: average_price uses PHP float here because it is a display/informational
     * field, not a money mutation — the actual balance debit happens in SQL above.
     */
    private function upsertPortfolio(int $userId, int $stockId, int $quantity, float $totalCost): Portfolio
    {
        $portfolio = Portfolio::where('user_id', $userId)->where('stock_id', $stockId)->first();

        if (!$portfolio) {
            $portfolio = new Portfolio([
                'user_id'       => $userId,
                'stock_id'      => $stockId,
                'quantity'      => 0,
                'average_price' => 0,
            ]);
        }

        $newQuantity = $portfolio->quantity + $quantity;
        $portfolio->average_price = $newQuantity > 0
            ? (($portfolio->average_price * $portfolio->quantity) + $totalCost) / $newQuantity
            : 0;
        $portfolio->quantity = $newQuantity;
        $portfolio->save();

        return $portfolio;
    }

    /**
     * Write an immutable audit delta and update the portfolio integrity checkpoint.
     */
    private function createAuditAndCheckpoint(
        User $user,
        Stock $stock,
        Portfolio $portfolio,
        string $type,
        int $quantity,
        float $price,
        float $totalAmount,
    ): void {
        $snapshot = json_encode([
            'quantity_change'     => $type === 'buy' ? $quantity : -$quantity,
            'average_price_after' => $portfolio->average_price,
        ]);

        $audit = PortfolioAudit::create([
            'user_id'            => $user->id,
            'stock_id'           => $stock->id,
            'type'               => $type,
            'quantity'           => $quantity,
            'price'              => $price,
            'total_amount'       => $totalAmount,
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
