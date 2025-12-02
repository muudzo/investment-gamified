<?php

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GameMarketTick extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:market-tick';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate market movement by updating stock prices every few seconds';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting market simulation... Press Ctrl+C to stop.');

        while (true) {
            $stocks = Stock::all();

            foreach ($stocks as $stock) {
                // Generate a random percentage change between -0.5% and +0.5%
                // mt_rand returns integer, so we divide by 10000 to get decimals
                // Range: -50 to +50 -> -0.005 to +0.005
                $percentChange = mt_rand(-50, 50) / 10000;
                
                // Calculate new price
                $changeAmount = $stock->current_price * $percentChange;
                $newPrice = $stock->current_price + $changeAmount;

                // Ensure price doesn't go below 0.01
                if ($newPrice < 0.01) {
                    $newPrice = 0.01;
                }

                // Update stock
                $stock->current_price = $newPrice;
                // Update change percentage (cumulative for the day/session, or just last tick? 
                // Requirement says "Add small percentage-based changes per tick". 
                // Usually change_percentage is relative to open, but for this simple game, 
                // let's just update it to reflect the latest movement or keep it cumulative.
                // Let's make it reflect the change from the *previous* price for immediate feedback,
                // OR better, keep it as "Day Change" if we had an open price.
                // Since we don't track open price explicitly in the model shown, let's just add the tick change to the current change_percentage.
                $stock->change_percentage += ($percentChange * 100); 
                
                $stock->save();

                $this->output->write(".");
            }

            // Sleep for 3-5 seconds
            $sleepTime = mt_rand(3, 5);
            sleep($sleepTime);
        }
    }
}
