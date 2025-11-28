<?php
// app/Console/Commands/SeedStocksFromFmp.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Stock;
use App\Services\FinancialModelingPrepService;

class SeedStocksFromFmp extends Command
{
    protected $signature = 'stocks:seed-fmp';
    protected $description = 'Seed stocks database from Financial Modeling Prep API';

    public function handle(FinancialModelingPrepService $fmp)
    {
        $this->info('Starting to seed stocks from FMP...');

        // Kid-friendly popular stocks
        $popularSymbols = [
            'AAPL' => ['category' => 'Tech', 'description' => 'Apple makes iPhones, iPads, and Mac computers'],
            'MSFT' => ['category' => 'Tech', 'description' => 'Microsoft makes Xbox, Windows, and Office'],
            'DIS' => ['category' => 'Entertainment', 'description' => 'Disney owns Mickey Mouse, Marvel, and Star Wars'],
            'GOOGL' => ['category' => 'Tech', 'description' => 'Google helps you search the internet'],
            'AMZN' => ['category' => 'Retail', 'description' => 'Amazon delivers packages to your door'],
            'TSLA' => ['category' => 'Tech', 'description' => 'Tesla makes electric cars and rockets'],
            'NKE' => ['category' => 'Retail', 'description' => 'Nike makes cool sneakers and sportswear'],
            'MCD' => ['category' => 'Food', 'description' => 'McDonald\'s serves burgers and fries'],
            'SBUX' => ['category' => 'Food', 'description' => 'Starbucks makes coffee drinks'],
            'NFLX' => ['category' => 'Entertainment', 'description' => 'Netflix streams movies and shows'],
            'META' => ['category' => 'Tech', 'description' => 'Facebook, Instagram, and WhatsApp'],
            'KO' => ['category' => 'Food', 'description' => 'Coca-Cola makes sodas and drinks'],
        ];

        $successCount = 0;
        $errorCount = 0;

        foreach ($popularSymbols as $symbol => $info) {
            $this->info("Fetching data for {$symbol}...");
            
            try {
                // Get quote data
                $quote = $fmp->getQuote($symbol);
                
                if (!$quote) {
                    $this->error("Failed to fetch quote for {$symbol}");
                    $errorCount++;
                    continue;
                }

                // Get profile data
                $profile = $fmp->getCompanyProfile($symbol);
                
                $stock = Stock::updateOrCreate(
                    ['symbol' => $symbol],
                    [
                        'name' => $quote['name'] ?? ($profile['companyName'] ?? $symbol),
                        'description' => $profile['description'] ?? $info['description'],
                        'kid_friendly_description' => $info['description'],
                        'category' => $info['category'],
                        'current_price' => $quote['price'] ?? 0,
                        'change_percentage' => $quote['change_percent'] ?? 0,
                        'logo_url' => $profile['image'] ?? null,
                        'fun_fact' => $this->generateFunFact($symbol),
                    ]
                );

                $this->info("✓ Successfully imported {$symbol} - {$stock->name}");
                $successCount++;
                
                // Rate limiting - FMP free tier allows 250 requests per day
                sleep(1);
                
            } catch (\Exception $e) {
                $this->error("Error importing {$symbol}: " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("Import completed!");
        $this->info("Successfully imported: {$successCount} stocks");
        if ($errorCount > 0) {
            $this->warn("Failed to import: {$errorCount} stocks");
        }
    }

    private function generateFunFact($symbol): ?string
    {
        $facts = [
            'AAPL' => 'Apple was started in a garage in 1976!',
            'MSFT' => 'Bill Gates started Microsoft when he was only 20 years old!',
            'DIS' => 'Disney World has its own government and fire department!',
            'GOOGL' => 'Google processes over 8.5 billion searches per day!',
            'AMZN' => 'Amazon started as an online bookstore!',
            'TSLA' => 'Tesla cars can drive themselves with Autopilot!',
            'NKE' => 'The Nike swoosh logo was designed for only $35!',
            'MCD' => 'McDonald\'s serves 68 million customers daily!',
            'SBUX' => 'Starbucks serves over 4 million customers every day!',
            'NFLX' => 'Netflix started by mailing DVDs to people\'s homes!',
            'META' => 'Facebook was originally only for college students!',
            'KO' => 'Coca-Cola was invented by a pharmacist in 1886!',
        ];

        return $facts[$symbol] ?? null;
    }
}