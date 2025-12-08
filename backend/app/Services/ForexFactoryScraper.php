<?php

namespace App\Services;

use App\Models\NewsItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ForexFactoryScraper
{
    private string $baseUrl = 'https://www.forexfactory.com';
    
    /**
     * Currency to pairs mapping for affected pairs detection
     */
    private array $currencyPairs = [
        'USD' => ['EURUSD', 'GBPUSD', 'USDJPY', 'USDCHF', 'USDCAD', 'AUDUSD', 'NZDUSD', 'XAUUSD'],
        'EUR' => ['EURUSD', 'EURGBP', 'EURJPY', 'EURCHF', 'EURCAD', 'EURAUD', 'EURNZD'],
        'GBP' => ['GBPUSD', 'EURGBP', 'GBPJPY', 'GBPCHF', 'GBPCAD', 'GBPAUD', 'GBPNZD'],
        'JPY' => ['USDJPY', 'EURJPY', 'GBPJPY', 'CHFJPY', 'CADJPY', 'AUDJPY', 'NZDJPY'],
        'CHF' => ['USDCHF', 'EURCHF', 'GBPCHF', 'CHFJPY', 'CADCHF', 'AUDCHF', 'NZDCHF'],
        'CAD' => ['USDCAD', 'EURCAD', 'GBPCAD', 'CADJPY', 'CADCHF', 'AUDCAD', 'NZDCAD'],
        'AUD' => ['AUDUSD', 'EURAUD', 'GBPAUD', 'AUDJPY', 'AUDCHF', 'AUDCAD', 'AUDNZD'],
        'NZD' => ['NZDUSD', 'EURNZD', 'GBPNZD', 'NZDJPY', 'NZDCHF', 'NZDCAD', 'AUDNZD'],
        'CNY' => ['USDCNH'],
    ];

    /**
     * Sync news for a specific date from ForexFactory
     */
    public function syncForDate(Carbon $date, int $userId): array
    {
        $result = [
            'success' => false,
            'synced' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        try {
            $events = $this->fetchCalendarData($date);
            
            foreach ($events as $event) {
                try {
                    $newsItem = $this->upsertNewsItem($event, $date, $userId);
                    if ($newsItem->wasRecentlyCreated) {
                        $result['synced']++;
                    } else {
                        $result['updated']++;
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to process event: " . $e->getMessage();
                    Log::error('ForexFactory event processing failed', [
                        'event' => $event,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $result['success'] = true;
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            Log::error('ForexFactory sync failed', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Sync news for the entire week from ForexFactory
     */
    public function syncWeek(int $userId): array
    {
        $result = [
            'success' => false,
            'synced' => 0,
            'updated' => 0,
            'errors' => [],
            'dates_processed' => [],
        ];

        try {
            $events = $this->fetchWeeklyCalendarData();
            
            foreach ($events as $event) {
                try {
                    if (empty($event['date'])) {
                        continue;
                    }
                    
                    $eventDate = Carbon::parse($event['date']);
                    $newsItem = $this->upsertNewsItem($event, $eventDate, $userId);
                    
                    $dateStr = $eventDate->format('Y-m-d');
                    if (!in_array($dateStr, $result['dates_processed'])) {
                        $result['dates_processed'][] = $dateStr;
                    }
                    
                    if ($newsItem->wasRecentlyCreated) {
                        $result['synced']++;
                    } else {
                        $result['updated']++;
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to process event: " . $e->getMessage();
                    Log::error('ForexFactory event processing failed', [
                        'event' => $event,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $result['success'] = true;
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            Log::error('ForexFactory weekly sync failed', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Fetch weekly calendar data using Puppeteer scraper (from forexfactory.com/calendar)
     */
    private function fetchWeeklyCalendarData(): array
    {
        // Try Puppeteer with week mode first
        $puppeteerData = $this->fetchWithPuppeteer('week');
        if ($puppeteerData !== null && count($puppeteerData) > 0) {
            return $puppeteerData;
        }

        // Fallback to JSON API
        return $this->fetchFromJsonApi();
    }

    /**
     * Fetch today's calendar data using Puppeteer scraper (from forexfactory.com)
     */
    private function fetchTodayCalendarData(): array
    {
        // Try Puppeteer with today mode first
        $puppeteerData = $this->fetchWithPuppeteer('today');
        if ($puppeteerData !== null && count($puppeteerData) > 0) {
            return $puppeteerData;
        }

        // Fallback to weekly data filtered by today's date
        $weeklyData = $this->fetchFromJsonApi();
        $today = today()->format('Y-m-d');
        
        return array_filter($weeklyData, function ($event) use ($today) {
            return isset($event['date']) && $event['date'] === $today;
        });
    }

    /**
     * Fetch calendar data for a specific date
     */
    private function fetchCalendarData(Carbon $date): array
    {
        $targetDate = $date->format('Y-m-d');
        $today = today()->format('Y-m-d');
        
        // If fetching today, use today mode (forexfactory.com)
        if ($targetDate === $today) {
            return $this->fetchTodayCalendarData();
        }
        
        // Otherwise, get weekly data and filter by date
        $weeklyData = $this->fetchWeeklyCalendarData();
        
        return array_values(array_filter($weeklyData, function ($event) use ($targetDate) {
            return isset($event['date']) && $event['date'] === $targetDate;
        }));
    }

    /**
     * Fetch data using Puppeteer Node.js scraper
     * 
     * @param string $mode 'today' for forexfactory.com or 'week' for forexfactory.com/calendar
     */
    private function fetchWithPuppeteer(string $mode = 'week'): ?array
    {
        $scriptPath = base_path('scripts/forexfactory-scraper.js');
        
        if (!file_exists($scriptPath)) {
            Log::warning('Puppeteer scraper script not found', ['path' => $scriptPath]);
            return null;
        }

        // Check if Node.js is available
        $nodeCheck = shell_exec('node --version 2>&1');
        if (empty($nodeCheck) || !str_starts_with(trim($nodeCheck), 'v')) {
            Log::warning('Node.js not available for Puppeteer scraper');
            return null;
        }

        try {
            $command = sprintf('node "%s" --mode=%s 2>&1', $scriptPath, $mode);
            Log::info('Running Puppeteer scraper', ['command' => $command, 'mode' => $mode]);
            
            $output = shell_exec($command);
            
            if (empty($output)) {
                Log::warning('Puppeteer scraper returned empty output');
                return null;
            }

            // Check for error response
            if (str_contains($output, '"error"')) {
                $errorData = json_decode($output, true);
                Log::error('Puppeteer scraper error', ['error' => $errorData['error'] ?? $output]);
                return null;
            }

            $data = json_decode($output, true);
            
            if (!is_array($data)) {
                Log::warning('Puppeteer scraper returned invalid JSON', ['output' => substr($output, 0, 500)]);
                return null;
            }

            Log::info('Puppeteer scraper successful', ['events_count' => count($data), 'mode' => $mode]);
            return $data;
        } catch (\Exception $e) {
            Log::error('Puppeteer scraper failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fallback: Fetch from JSON API
     */
    private function fetchFromJsonApi(): array
    {
        $url = 'https://nfs.faireconomy.media/ff_calendar_thisweek.json';
        $cachePath = storage_path('app/ff_calendar_thisweek.json');

        $items = null;

        // Check cache (< 55 minutes old)
        if (file_exists($cachePath)) {
            $ageSeconds = time() - filemtime($cachePath);
            if ($ageSeconds < 55 * 60) {
                $cached = file_get_contents($cachePath);
                $decoded = json_decode($cached, true);
                if (\is_array($decoded)) {
                    $items = $decoded;
                }
            }
        }

        // Fetch from API if no cache
        if ($items === null) {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json,text/plain,*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->timeout(30)->get($url);

            if ($response->successful()) {
                try {
                    file_put_contents($cachePath, $response->body());
                } catch (\Throwable $e) {
                    Log::warning('Failed to write ForexFactory JSON cache', ['error' => $e->getMessage()]);
                }

                $items = $response->json();
            } elseif ($response->status() === 429 && file_exists($cachePath)) {
                Log::warning('ForexFactory JSON rate limited (429), using cached copy');
                $cached = file_get_contents($cachePath);
                $items = json_decode($cached, true);
            } else {
                throw new \Exception('Failed to fetch ForexFactory JSON: HTTP ' . $response->status());
            }
        }

        if (!\is_array($items)) {
            throw new \Exception('Invalid JSON response from ForexFactory');
        }

        // Convert JSON API format to our format
        $events = [];
        foreach ($items as $item) {
            if (empty($item['date']) || empty($item['country']) || empty($item['title'])) {
                continue;
            }

            try {
                $eventDateTime = Carbon::parse($item['date']);
            } catch (\Exception $e) {
                continue;
            }

            $impactRaw = strtolower($item['impact'] ?? 'Medium');
            $impact = 'medium';
            if ($impactRaw === 'high') {
                $impact = 'high';
            } elseif ($impactRaw === 'low') {
                $impact = 'low';
            }

            $currency = strtoupper($item['country']);

            $events[] = [
                'date' => $eventDateTime->format('Y-m-d'),
                'time' => $eventDateTime->format('H:i:s'),
                'currency' => $currency,
                'impact' => $impact,
                'title' => $item['title'],
                'actual' => $item['actual'] ?? null,
                'forecast' => $item['forecast'] ?? null,
                'previous' => $item['previous'] ?? null,
                'event_id' => $item['id'] ?? Str::slug($currency . '-' . $item['title'] . '-' . $eventDateTime->format('YmdHi')),
            ];
        }

        return $events;
    }

    /**
     * Parse time string to proper 24-hour format
     * Handles: "8:30am", "10:00pm", "12:00am", "12:30pm", "08:30:00"
     */
    private function parseTime(?string $timeStr): ?string
    {
        if (empty($timeStr)) {
            return null;
        }

        $timeStr = strtolower(trim($timeStr));
        
        // Skip non-time values
        if (in_array($timeStr, ['all day', 'tentative', 'day', ''])) {
            return null;
        }

        // Already in 24-hour format with seconds (from Puppeteer)
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $timeStr)) {
            return $timeStr;
        }

        // 24-hour format without seconds
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeStr, $match)) {
            $hours = str_pad($match[1], 2, '0', STR_PAD_LEFT);
            return "{$hours}:{$match[2]}:00";
        }

        // 12-hour format (e.g., "8:30am", "10:00pm")
        if (preg_match('/^(\d{1,2}):(\d{2})\s*(am|pm)$/i', $timeStr, $match)) {
            $hours = (int) $match[1];
            $minutes = $match[2];
            $period = strtolower($match[3]);

            if ($period === 'am') {
                if ($hours === 12) {
                    $hours = 0; // 12:00am = 00:00
                }
            } else {
                // pm
                if ($hours !== 12) {
                    $hours += 12; // 1:00pm = 13:00, but 12:00pm stays 12:00
                }
            }

            return sprintf('%02d:%s:00', $hours, $minutes);
        }

        return null;
    }

    /**
     * Upsert news item to database
     */
    private function upsertNewsItem(array $event, Carbon $date, int $userId): NewsItem
    {
        $currency = strtoupper($event['currency'] ?? '');
        $affectedPairs = $this->currencyPairs[$currency] ?? [];
        
        // Parse time properly
        $time = $this->parseTime($event['time'] ?? null);
        
        // Create unique identifier
        $eventIdBase = $event['event_id'] ?? Str::slug($currency . '-' . ($event['title'] ?? 'unknown'));
        $ffEventId = $eventIdBase . '-' . $date->format('Ymd');
        if ($time) {
            $ffEventId .= '-' . str_replace(':', '', substr($time, 0, 5));
        }

        // Clean actual/forecast/previous values
        $actual = $this->cleanNumericValue($event['actual'] ?? null);
        $forecast = $this->cleanNumericValue($event['forecast'] ?? null);
        $previous = $this->cleanNumericValue($event['previous'] ?? null);

        // Search criteria: date, ff_event_id, and created_by (user_id)
        $searchCriteria = [
            'date' => $date->format('Y-m-d'),
            'ff_event_id' => $ffEventId,
            'created_by' => $userId, // Same user
        ];
        
        $updateData = [
            'time' => $time,
            'title' => $event['title'] ?? 'Unknown Event',
            'currency' => $currency,
            'impact' => $event['impact'] ?? 'medium',
            'actual' => $actual,
            'forecast' => $forecast,
            'previous' => $previous,
            'source' => 'ForexFactory',
            'url' => $this->baseUrl . '/calendar',
            'is_manual' => false,
            'affected_pairs' => $affectedPairs,
            'created_by' => $userId, // Link to user (admin/demo)
        ];
        
        // Don't auto-set EA status - let user mark it manually
        // 'ea_status' => null, // Default to empty
        // 'should_disable_ea' => false, // Default to false
        
        return NewsItem::updateOrCreate($searchCriteria, $updateData);
    }

    /**
     * Clean numeric values (remove HTML entities, trim whitespace)
     */
    private function cleanNumericValue(?string $value): ?string
    {
        if (empty($value) || $value === '&nbsp;' || $value === '-') {
            return null;
        }

        // Remove HTML entities
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim($value);

        if (empty($value) || $value === '-') {
            return null;
        }

        return $value;
    }

    /**
     * Suggest EA status based on impact
     */
    private function suggestEaStatus(string $impact): string
    {
        $impact = strtolower($impact);

        if ($impact === 'high') {
            return 'danger';
        }

        if ($impact === 'medium') {
            return 'caution';
        }

        if ($impact === 'low') {
            return 'safe';
        }

        return 'unknown';
    }

    /**
     * Get affected currency pairs for a currency
     */
    public function getAffectedPairs(string $currency): array
    {
        return $this->currencyPairs[strtoupper($currency)] ?? [];
    }
}
