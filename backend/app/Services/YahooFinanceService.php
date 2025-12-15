<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class YahooFinanceService
{
    private const BASE_URL = 'https://query1.finance.yahoo.com/v8/finance/chart';
    private const CACHE_TTL = 60; // Cache for 60 seconds

    /**
     * Fetch latest price for a symbol from Yahoo Finance
     *
     * @param string $symbol
     * @return float|null
     */
    public function fetchPrice(string $symbol): ?float
    {
        $cacheKey = "yahoo_price_{$symbol}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($symbol) {
            try {
                $url = self::BASE_URL . '/' . urlencode($symbol);
                
                $response = Http::timeout(10)
                    ->retry(2, 100)
                    ->get($url);

                if (!$response->successful()) {
                    Log::warning("Yahoo Finance API failed for {$symbol}: HTTP {$response->status()}");
                    return null;
                }

                $data = $response->json();

                if (!isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
                    Log::warning("Yahoo Finance API: Invalid response structure for {$symbol}");
                    return null;
                }

                $price = $data['chart']['result'][0]['meta']['regularMarketPrice'];
                
                Log::info("Yahoo Finance: {$symbol} = {$price}");
                
                return (float) $price;
            } catch (\Exception $e) {
                Log::error("Yahoo Finance API error for {$symbol}: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Fetch multiple symbols at once
     *
     * @param array $symbols
     * @return array
     */
    public function fetchMultiple(array $symbols): array
    {
        $results = [];
        
        foreach ($symbols as $symbol) {
            $results[$symbol] = $this->fetchPrice($symbol);
        }
        
        return $results;
    }

    /**
     * Get DXY (Dollar Index) price
     *
     * @return float|null
     */
    public function getDXY(): ?float
    {
        return $this->fetchPrice('DX-Y.NYB');
    }

    /**
     * Get VIX (Volatility Index) value
     *
     * @return float|null
     */
    public function getVIX(): ?float
    {
        return $this->fetchPrice('^VIX');
    }

    /**
     * Get US 10-Year Treasury Yield
     *
     * @return float|null
     */
    public function getUS10Y(): ?float
    {
        return $this->fetchPrice('^TNX');
    }

    /**
     * Get Gold (XAUUSD) price
     *
     * @return float|null
     */
    public function getGold(): ?float
    {
        return $this->fetchPrice('XAUUSD=X');
    }

    /**
     * Get EURUSD price for correlation calculation
     *
     * @return float|null
     */
    public function getEURUSD(): ?float
    {
        return $this->fetchPrice('EURUSD=X');
    }

    /**
     * Get GBPUSD price for correlation calculation
     *
     * @return float|null
     */
    public function getGBPUSD(): ?float
    {
        return $this->fetchPrice('GBPUSD=X');
    }

    /**
     * Get GVZ (Gold Volatility Index) value
     *
     * @return float|null
     */
    public function getGVZ(): ?float
    {
        return $this->fetchPrice('^GVZ');
    }

    /**
     * Get S&P500 Futures (ES) price
     *
     * @return float|null
     */
    public function getSPX(): ?float
    {
        return $this->fetchPrice('ES=F');
    }

    /**
     * Get S&P500 Futures historical prices for trend analysis
     *
     * @param int $periods Number of periods to fetch (default 20)
     * @return array|null Array of closing prices or null if failed
     */
    public function getSPXHistorical(int $periods = 20): ?array
    {
        return $this->fetchHistoricalPrices('ES=F', $periods, '1h');
    }

    /**
     * Fetch historical price data for correlation calculation
     *
     * @param string $symbol
     * @param int $periods Number of periods to fetch (default 50)
     * @param string $interval Interval (1h, 1d, etc.)
     * @return array|null Array of closing prices or null if failed
     */
    public function fetchHistoricalPrices(string $symbol, int $periods = 50, string $interval = '1h'): ?array
    {
        $cacheKey = "yahoo_historical_{$symbol}_{$periods}_{$interval}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($symbol, $periods, $interval) {
            try {
                $url = self::BASE_URL . '/' . urlencode($symbol);
                
                // Calculate time range
                // For 50 periods of 1h data, we need about 3 days
                $range = '3d'; // Request 3 days of hourly data
                
                $response = Http::timeout(15)
                    ->retry(2, 100)
                    ->get($url, [
                        'interval' => $interval,
                        'range' => $range,
                    ]);

                if (!$response->successful()) {
                    Log::warning("Yahoo Finance historical API failed for {$symbol}: HTTP {$response->status()}");
                    return null;
                }

                $data = $response->json();

                if (!isset($data['chart']['result'][0])) {
                    Log::warning("Yahoo Finance API: No result data for {$symbol}");
                    return null;
                }

                $result = $data['chart']['result'][0];
                
                // Try to get closing prices from indicators
                if (isset($result['indicators']['quote'][0]['close'])) {
                    $closes = $result['indicators']['quote'][0]['close'];
                } elseif (isset($result['indicators']['adjclose'][0]['adjclose'])) {
                    $closes = $result['indicators']['adjclose'][0]['adjclose'];
                } else {
                    Log::warning("Yahoo Finance API: No closing price data for {$symbol}");
                    return null;
                }
                
                // Filter out null values
                $prices = array_filter($closes, function($price) {
                    return $price !== null && $price > 0;
                });
                
                $prices = array_values($prices);
                
                // Get last N periods
                if (count($prices) > $periods) {
                    $prices = array_slice($prices, -$periods);
                }
                
                if (count($prices) < 20) {
                    Log::warning("Yahoo Finance: Insufficient historical data for {$symbol} (got " . count($prices) . " periods, need at least 20)");
                    return null;
                }
                
                return array_map('floatval', $prices);
            } catch (\Exception $e) {
                Log::error("Yahoo Finance historical API error for {$symbol}: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Calculate correlation coefficient between two price series
     *
     * @param array|null $prices1
     * @param array|null $prices2
     * @return float|null Returns correlation coefficient (-1 to 1) or null if insufficient data
     */
    public function calculateCorrelation(?array $prices1, ?array $prices2): ?float
    {
        if ($prices1 === null || $prices2 === null) {
            return null;
        }
        
        // Ensure both arrays have the same length
        $minLength = min(count($prices1), count($prices2));
        if ($minLength < 20) {
            return null; // Need at least 20 data points
        }
        
        $prices1 = array_slice($prices1, -$minLength);
        $prices2 = array_slice($prices2, -$minLength);
        
        // Calculate returns (percentage changes)
        $returns1 = [];
        $returns2 = [];
        
        for ($i = 1; $i < $minLength; $i++) {
            if ($prices1[$i-1] != 0 && $prices2[$i-1] != 0) {
                $returns1[] = ($prices1[$i] - $prices1[$i-1]) / $prices1[$i-1];
                $returns2[] = ($prices2[$i] - $prices2[$i-1]) / $prices2[$i-1];
            }
        }
        
        if (count($returns1) < 20) {
            return null;
        }
        
        // Calculate correlation coefficient
        $mean1 = array_sum($returns1) / count($returns1);
        $mean2 = array_sum($returns2) / count($returns2);
        
        $numerator = 0;
        $denom1 = 0;
        $denom2 = 0;
        
        for ($i = 0; $i < count($returns1); $i++) {
            $diff1 = $returns1[$i] - $mean1;
            $diff2 = $returns2[$i] - $mean2;
            
            $numerator += $diff1 * $diff2;
            $denom1 += $diff1 * $diff1;
            $denom2 += $diff2 * $diff2;
        }
        
        $denominator = sqrt($denom1 * $denom2);
        
        if ($denominator == 0) {
            return null;
        }
        
        $correlation = $numerator / $denominator;
        
        // Clamp to [-1, 1]
        return max(-1, min(1, $correlation));
    }

    /**
     * Calculate cross-pair correlations for MSI
     *
     * @return array
     */
    public function calculateCrossPairCorrelations(): array
    {
        // Fetch historical data for all pairs (last 50 hours)
        $xauusd = $this->fetchHistoricalPrices('XAUUSD=X', 50, '1h');
        $eurusd = $this->fetchHistoricalPrices('EURUSD=X', 50, '1h');
        $gbpusd = $this->fetchHistoricalPrices('GBPUSD=X', 50, '1h');
        $usdjpy = $this->fetchHistoricalPrices('USDJPY=X', 50, '1h');
        
        $correlations = [];
        
        // XAUUSD vs EURUSD
        if ($xauusd !== null && $eurusd !== null) {
            $correlations['xauusd_eurusd'] = $this->calculateCorrelation($xauusd, $eurusd);
        } else {
            $correlations['xauusd_eurusd'] = null;
        }
        
        // XAUUSD vs GBPUSD
        if ($xauusd !== null && $gbpusd !== null) {
            $correlations['xauusd_gbpusd'] = $this->calculateCorrelation($xauusd, $gbpusd);
        } else {
            $correlations['xauusd_gbpusd'] = null;
        }
        
        // XAUUSD vs USDJPY
        if ($xauusd !== null && $usdjpy !== null) {
            $correlations['xauusd_usdjpy'] = $this->calculateCorrelation($xauusd, $usdjpy);
        } else {
            $correlations['xauusd_usdjpy'] = null;
        }
        
        // EURUSD vs GBPUSD
        if ($eurusd !== null && $gbpusd !== null) {
            $correlations['eurusd_gbpusd'] = $this->calculateCorrelation($eurusd, $gbpusd);
        } else {
            $correlations['eurusd_gbpusd'] = null;
        }
        
        // Calculate average absolute correlation
        $validCorrelations = array_filter($correlations, function($corr) {
            return $corr !== null;
        });
        
        if (empty($validCorrelations)) {
            $correlations['average'] = null;
        } else {
            $absCorrelations = array_map('abs', $validCorrelations);
            $correlations['average'] = array_sum($absCorrelations) / count($absCorrelations);
        }
        
        return $correlations;
    }

    /**
     * Get all market data needed for MSI calculation
     *
     * @return array
     */
    public function getMarketData(): array
    {
        $dxy = $this->getDXY();
        $vix = $this->getVIX();
        $gvz = $this->getGVZ();
        $us10y = $this->getUS10Y();
        $gold = $this->getGold();
        $eurusd = $this->getEURUSD();
        $gbpusd = $this->getGBPUSD();
        $spx = $this->getSPX();
        $spxHistorical = $this->getSPXHistorical(20); // Get last 20 hours for trend analysis

        // Calculate cross-pair correlations
        $correlations = $this->calculateCrossPairCorrelations();
        
        // Use average absolute correlation for MSI calculation
        $correlation = $correlations['average'] ?? null;

        return [
            'dxy' => $dxy,
            'vix' => $vix,
            'gvz' => $gvz,
            'us10y' => $us10y,
            'gold' => $gold,
            'eurusd' => $eurusd,
            'gbpusd' => $gbpusd,
            'spx' => $spx,
            'spxHistorical' => $spxHistorical,
            'correlation' => $correlation,
            'correlations' => $correlations, // Include detailed correlations
        ];
    }
}

