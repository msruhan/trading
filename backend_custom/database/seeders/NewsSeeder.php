<?php

namespace Database\Seeders;

use App\Models\NewsItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class NewsSeeder extends Seeder
{
    private array $newsEvents = [
        ['title' => 'Non-Farm Payrolls', 'currency' => 'USD', 'impact' => 'high'],
        ['title' => 'FOMC Statement', 'currency' => 'USD', 'impact' => 'high'],
        ['title' => 'Fed Interest Rate Decision', 'currency' => 'USD', 'impact' => 'high'],
        ['title' => 'CPI m/m', 'currency' => 'USD', 'impact' => 'high'],
        ['title' => 'Core CPI m/m', 'currency' => 'USD', 'impact' => 'high'],
        ['title' => 'GDP q/q', 'currency' => 'USD', 'impact' => 'high'],
        ['title' => 'Unemployment Rate', 'currency' => 'USD', 'impact' => 'medium'],
        ['title' => 'Retail Sales m/m', 'currency' => 'USD', 'impact' => 'medium'],
        ['title' => 'ISM Manufacturing PMI', 'currency' => 'USD', 'impact' => 'medium'],
        ['title' => 'Consumer Confidence', 'currency' => 'USD', 'impact' => 'medium'],
        ['title' => 'ECB Interest Rate Decision', 'currency' => 'EUR', 'impact' => 'high'],
        ['title' => 'German CPI m/m', 'currency' => 'EUR', 'impact' => 'medium'],
        ['title' => 'German Manufacturing PMI', 'currency' => 'EUR', 'impact' => 'medium'],
        ['title' => 'BOE Interest Rate Decision', 'currency' => 'GBP', 'impact' => 'high'],
        ['title' => 'UK CPI y/y', 'currency' => 'GBP', 'impact' => 'high'],
        ['title' => 'UK Unemployment Rate', 'currency' => 'GBP', 'impact' => 'medium'],
        ['title' => 'BOJ Policy Rate', 'currency' => 'JPY', 'impact' => 'high'],
        ['title' => 'Japan CPI y/y', 'currency' => 'JPY', 'impact' => 'medium'],
        ['title' => 'RBA Interest Rate Decision', 'currency' => 'AUD', 'impact' => 'high'],
        ['title' => 'Australian Employment Change', 'currency' => 'AUD', 'impact' => 'medium'],
        ['title' => 'Crude Oil Inventories', 'currency' => 'USD', 'impact' => 'medium'],
        ['title' => 'Initial Jobless Claims', 'currency' => 'USD', 'impact' => 'low'],
        ['title' => 'Building Permits', 'currency' => 'USD', 'impact' => 'low'],
        ['title' => 'Existing Home Sales', 'currency' => 'USD', 'impact' => 'low'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Generate news for the past 7 days and upcoming 7 days
        for ($day = -7; $day <= 7; $day++) {
            $date = Carbon::now()->addDays($day);
            
            // Skip weekends
            if ($date->isWeekend()) {
                continue;
            }

            // Add 2-5 random news events per day
            $eventsCount = rand(2, 5);
            $selectedEvents = array_rand($this->newsEvents, $eventsCount);
            
            if (!is_array($selectedEvents)) {
                $selectedEvents = [$selectedEvents];
            }

            foreach ($selectedEvents as $eventIndex) {
                $event = $this->newsEvents[$eventIndex];
                
                NewsItem::create([
                    'date' => $date->format('Y-m-d'),
                    'time' => sprintf('%02d:%02d:00', rand(8, 20), rand(0, 1) * 30),
                    'title' => $event['title'],
                    'summary' => $this->generateSummary($event['title']),
                    'source' => $this->randomSource(),
                    'impact' => $event['impact'],
                    'currency' => $event['currency'],
                    'actual' => $day < 0 ? $this->generateValue() : null,
                    'forecast' => $this->generateValue(),
                    'previous' => $this->generateValue(),
                    'is_manual' => false,
                ]);
            }
        }

        // Add some manual news entries
        NewsItem::create([
            'date' => Carbon::today()->format('Y-m-d'),
            'title' => 'Market Analysis: EUR/USD Technical Outlook',
            'summary' => 'EUR/USD is testing key resistance at 1.0950. Watch for breakout or rejection.',
            'impact' => 'medium',
            'currency' => 'EUR',
            'is_manual' => true,
            'created_by' => 1,
        ]);

        $this->command->info('News items seeded successfully!');
    }

    private function generateSummary(string $title): string
    {
        $summaries = [
            "Upcoming release of {$title}. Markets expected to react significantly.",
            "{$title} data will be released. Traders should monitor closely.",
            "Key economic indicator: {$title}. May impact currency pairs.",
            "Watch for {$title} release. Potential volatility expected.",
        ];
        
        return $summaries[array_rand($summaries)];
    }

    private function randomSource(): string
    {
        $sources = [
            'ForexFactory',
            'Investing.com',
            'DailyFX',
            'FXStreet',
            'Bloomberg',
            'Reuters',
        ];
        
        return $sources[array_rand($sources)];
    }

    private function generateValue(): string
    {
        $formats = [
            fn() => sprintf('%.1f%%', (rand(-20, 50) / 10)),
            fn() => sprintf('%.2fK', rand(100, 500)),
            fn() => sprintf('%.1f', rand(40, 60)),
            fn() => sprintf('%d', rand(200, 400)),
        ];
        
        return $formats[array_rand($formats)]();
    }
}

