<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Balance;
use App\Models\Trade;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BalanceSeeder extends Seeder
{
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

        foreach ($accounts as $account) {
            $this->generateBalanceHistory($account);
        }

        $this->command->info('Balances seeded successfully!');
    }

    /**
     * Generate balance history for an account.
     */
    private function generateBalanceHistory(Account $account): void
    {
        $balance = $account->initial_balance;
        
        // Generate balance snapshots for last 30 days
        for ($day = 30; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day);
            
            // Get all closed trades for this day
            $dayTrades = Trade::where('account_id', $account->id)
                ->whereDate('close_time', $date)
                ->where('status', 'closed')
                ->get();

            // Calculate daily profit
            $dailyProfit = $dayTrades->sum('profit') + $dayTrades->sum('swap') - $dayTrades->sum('commission');
            $balance += $dailyProfit;

            // Get open trades count and floating P/L
            $openTrades = Trade::where('account_id', $account->id)
                ->where('status', 'open')
                ->get();
            
            $floatingPL = rand(-200, 300); // Simulated floating P/L
            $equity = $balance + $floatingPL;
            
            // Simulated margin values
            $margin = $openTrades->count() * rand(50, 200);
            $freeMargin = $equity - $margin;
            $marginLevel = $margin > 0 ? ($equity / $margin) * 100 : 0;

            // Create multiple snapshots per day (every 4 hours)
            for ($hour = 0; $hour < 24; $hour += 4) {
                $timestamp = $date->copy()->setHour($hour)->setMinute(0)->setSecond(0);
                
                // Skip if timestamp is in the future
                if ($timestamp->isFuture()) {
                    continue;
                }

                // Add some variation to the balance throughout the day
                $variation = rand(-50, 50);
                
                Balance::create([
                    'account_id' => $account->id,
                    'timestamp' => $timestamp,
                    'balance' => round($balance + ($hour < 20 ? $variation : 0), 2),
                    'equity' => round($equity + $variation, 2),
                    'margin' => round($margin, 2),
                    'free_margin' => round($freeMargin + $variation, 2),
                    'margin_level' => round($marginLevel, 2),
                    'floating_pl' => round($floatingPL + $variation, 2),
                    'open_trades_count' => $openTrades->count(),
                ]);
            }
        }
    }
}

