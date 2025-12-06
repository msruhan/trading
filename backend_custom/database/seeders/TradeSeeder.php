<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Trade;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TradeSeeder extends Seeder
{
    private array $pairs = [
        'EURUSD', 'GBPUSD', 'USDJPY', 'USDCHF', 'AUDUSD',
        'NZDUSD', 'USDCAD', 'EURJPY', 'GBPJPY', 'XAUUSD',
    ];

    private array $pairPrices = [
        'EURUSD' => [1.0700, 1.1100],
        'GBPUSD' => [1.2200, 1.2700],
        'USDJPY' => [145.00, 152.00],
        'USDCHF' => [0.8700, 0.9100],
        'AUDUSD' => [0.6300, 0.6700],
        'NZDUSD' => [0.5800, 0.6200],
        'USDCAD' => [1.3400, 1.3800],
        'EURJPY' => [158.00, 165.00],
        'GBPJPY' => [182.00, 192.00],
        'XAUUSD' => [1900.00, 2100.00],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = Account::all();

        if ($accounts->isEmpty()) {
            $this->command->error('No accounts found. Run AccountSeeder first.');
            return;
        }

        $ticket = 100000000;

        foreach ($accounts as $account) {
            // Generate trades for the last 30 days
            for ($day = 30; $day >= 0; $day--) {
                $date = Carbon::now()->subDays($day);
                
                // Skip weekends (no trading)
                if ($date->isWeekend()) {
                    continue;
                }

                // Random number of trades per day (1-5)
                $tradesPerDay = rand(1, 5);

                for ($i = 0; $i < $tradesPerDay; $i++) {
                    $trade = $this->generateTrade($account, $date, $ticket++);
                    Trade::create($trade);
                }
            }

            // Add some open trades
            for ($i = 0; $i < rand(1, 3); $i++) {
                $trade = $this->generateTrade($account, now(), $ticket++, true);
                Trade::create($trade);
            }
        }

        $this->command->info('Trades seeded successfully!');
    }

    /**
     * Generate a random trade.
     */
    private function generateTrade(Account $account, Carbon $date, int $ticket, bool $isOpen = false): array
    {
        $pair = $this->pairs[array_rand($this->pairs)];
        $type = rand(0, 1) ? 'buy' : 'sell';
        $lots = $this->randomLots();
        
        // Generate prices based on pair
        $priceRange = $this->pairPrices[$pair];
        $openPrice = $this->randomPrice($priceRange[0], $priceRange[1], $pair);
        
        // Generate open time (random hour during trading day)
        $openTime = $date->copy()->setHour(rand(8, 22))->setMinute(rand(0, 59))->setSecond(rand(0, 59));

        if ($isOpen) {
            return [
                'account_id' => $account->id,
                'ticket' => $ticket,
                'pair' => $pair,
                'type' => $type,
                'status' => 'open',
                'open_time' => $openTime,
                'close_time' => null,
                'open_price' => $openPrice,
                'close_price' => null,
                'stop_loss' => $this->calculateSL($openPrice, $type, $pair),
                'take_profit' => $this->calculateTP($openPrice, $type, $pair),
                'lots' => $lots,
                'profit' => null,
                'swap' => 0,
                'commission' => $this->calculateCommission($lots),
                'is_manual' => false,
            ];
        }

        // For closed trades
        $closeTime = $openTime->copy()->addMinutes(rand(5, 480));
        
        // Determine if this is a winning trade (60% win rate)
        $isWinner = rand(1, 100) <= 60;
        
        // Calculate close price based on win/loss
        $closePrice = $this->calculateClosePrice($openPrice, $type, $pair, $isWinner);
        
        // Calculate profit
        $profit = $this->calculateProfit($openPrice, $closePrice, $type, $lots, $pair);
        
        // Calculate pips
        $pips = $this->calculatePips($openPrice, $closePrice, $type, $pair);

        return [
            'account_id' => $account->id,
            'ticket' => $ticket,
            'pair' => $pair,
            'type' => $type,
            'status' => 'closed',
            'open_time' => $openTime,
            'close_time' => $closeTime,
            'open_price' => $openPrice,
            'close_price' => $closePrice,
            'stop_loss' => $this->calculateSL($openPrice, $type, $pair),
            'take_profit' => $this->calculateTP($openPrice, $type, $pair),
            'lots' => $lots,
            'profit' => $profit,
            'pips' => $pips,
            'swap' => rand(-5, 2) / 10,
            'commission' => $this->calculateCommission($lots),
            'duration_minutes' => $openTime->diffInMinutes($closeTime),
            'is_manual' => false,
        ];
    }

    private function randomLots(): float
    {
        $options = [0.01, 0.02, 0.03, 0.05, 0.1, 0.2, 0.5, 1.0];
        return $options[array_rand($options)];
    }

    private function randomPrice(float $min, float $max, string $pair): float
    {
        $decimals = str_contains($pair, 'JPY') || str_contains($pair, 'XAU') ? 2 : 5;
        return round($min + (mt_rand() / mt_getrandmax()) * ($max - $min), $decimals);
    }

    private function calculateSL(float $openPrice, string $type, string $pair): float
    {
        $distance = $this->getPipValue($pair) * rand(20, 50);
        return $type === 'buy' ? $openPrice - $distance : $openPrice + $distance;
    }

    private function calculateTP(float $openPrice, string $type, string $pair): float
    {
        $distance = $this->getPipValue($pair) * rand(30, 100);
        return $type === 'buy' ? $openPrice + $distance : $openPrice - $distance;
    }

    private function calculateClosePrice(float $openPrice, string $type, string $pair, bool $isWinner): float
    {
        $pips = rand(5, 80);
        $pipValue = $this->getPipValue($pair);
        $distance = $pipValue * $pips;

        if ($type === 'buy') {
            return $isWinner ? $openPrice + $distance : $openPrice - $distance;
        } else {
            return $isWinner ? $openPrice - $distance : $openPrice + $distance;
        }
    }

    private function calculateProfit(float $openPrice, float $closePrice, string $type, float $lots, string $pair): float
    {
        $pips = $this->calculatePips($openPrice, $closePrice, $type, $pair);
        $pipValue = $this->getPipValueInUSD($pair);
        return round($pips * $pipValue * $lots * 100000 / 10000, 2);
    }

    private function calculatePips(float $openPrice, float $closePrice, string $type, string $pair): float
    {
        $multiplier = str_contains($pair, 'JPY') ? 100 : 10000;
        if (str_contains($pair, 'XAU')) {
            $multiplier = 10;
        }
        
        $diff = $closePrice - $openPrice;
        if ($type === 'sell') {
            $diff = -$diff;
        }
        
        return round($diff * $multiplier, 1);
    }

    private function getPipValue(string $pair): float
    {
        if (str_contains($pair, 'JPY')) {
            return 0.01;
        }
        if (str_contains($pair, 'XAU')) {
            return 0.1;
        }
        return 0.0001;
    }

    private function getPipValueInUSD(string $pair): float
    {
        // Simplified - in reality this depends on account currency and pair
        return 0.0001;
    }

    private function calculateCommission(float $lots): float
    {
        // $7 per lot round trip
        return round($lots * 7, 2);
    }
}

