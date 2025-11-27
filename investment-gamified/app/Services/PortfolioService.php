<?php

namespace App\Services;

use App\Models\Portfolio;
use App\Models\Stock;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class PortfolioService
{
    /**
     * Handle buying stocks for a user
     * Returns array with keys: success (bool), message (string), data (array)
     */
    public function buyStock($user, string $stockSymbol, int $quantity): array
    {
        $stock = Stock::where('symbol', $stockSymbol)->first();
        if (!$stock) {
            return ['success' => false, 'message' => 'Stock not found'];
        }

        $totalCost = $stock->current_price * $quantity;

        if ($user->balance < $totalCost) {
            return ['success' => false, 'message' => 'Insufficient balance'];
        }

        DB::transaction(function () use ($user, $stock, $quantity, $totalCost) {
            // Deduct balance
            $user->balance -= $totalCost;
            $user->save();

            // Update or create portfolio entry
            $portfolio = Portfolio::firstOrNew([
                'user_id' => $user->id,
                'stock_id' => $stock->id,
            ]);

            $newQuantity = $portfolio->quantity + $quantity;
            $portfolio->average_price = (($portfolio->average_price * $portfolio->quantity) + $totalCost) / $newQuantity;
            $portfolio->quantity = $newQuantity;
            $portfolio->save();

            // Record transaction
            Transaction::create([
                'user_id' => $user->id,
                'stock_id' => $stock->id,
                'type' => 'buy',
                'quantity' => $quantity,
                'price' => $stock->current_price,
                'total_amount' => $totalCost,
            ]);

            // Award XP
            $user->experience_points += 10;
            if ($user->experience_points >= $user->level * 1000) {
                $user->level++;
                $user->experience_points = 0;
            }
            $user->save();
        });

        return [
            'success' => true,
            'message' => 'Stock purchased successfully',
            'data' => [
                'xp_earned' => 10,
            ],
        ];
    }

    /**
     * Handle selling stocks for a user
     */
    public function sellStock($user, string $stockSymbol, int $quantity): array
    {
        $stock = Stock::where('symbol', $stockSymbol)->first();
        if (!$stock) {
            return ['success' => false, 'message' => 'Stock not found'];
        }

        $portfolio = Portfolio::where('user_id', $user->id)
            ->where('stock_id', $stock->id)
            ->first();

        if (!$portfolio || $portfolio->quantity < $quantity) {
            return ['success' => false, 'message' => 'Insufficient stock quantity'];
        }

        $totalRevenue = $stock->current_price * $quantity;

        DB::transaction(function () use ($user, $stock, $portfolio, $quantity, $totalRevenue) {
            // Add to balance
            $user->balance += $totalRevenue;
            $user->save();

            // Update portfolio
            $portfolio->quantity -= $quantity;
            if ($portfolio->quantity == 0) {
                $portfolio->delete();
            } else {
                $portfolio->save();
            }

            // Record transaction
            Transaction::create([
                'user_id' => $user->id,
                'stock_id' => $stock->id,
                'type' => 'sell',
                'quantity' => $quantity,
                'price' => $stock->current_price,
                'total_amount' => $totalRevenue,
            ]);

            // Award XP
            $user->experience_points += 15;
            if ($user->experience_points >= $user->level * 1000) {
                $user->level++;
                $user->experience_points = 0;
            }
            $user->save();
        });

        return [
            'success' => true,
            'message' => 'Stock sold successfully',
            'data' => [
                'xp_earned' => 15,
            ],
        ];
    }
}
