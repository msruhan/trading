<?php

namespace App\Services;

use App\Models\User;
use App\Models\NewsItem;
use App\Utils\MSICalculator;
use App\Services\GroqService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TodayAnalysisService
{
    protected TechnicalAnalysisService $technicalService;
    protected YahooFinanceService $yahooService;
    protected MSICalculator $msiCalculator;
    protected GroqService $groqService;

    public function __construct(
        TechnicalAnalysisService $technicalService,
        YahooFinanceService $yahooService,
        MSICalculator $msiCalculator,
        GroqService $groqService
    ) {
        $this->technicalService = $technicalService;
        $this->yahooService = $yahooService;
        $this->msiCalculator = $msiCalculator;
        $this->groqService = $groqService;
    }

    /**
     * Get today's comprehensive analysis combining all 4 predictions.
     */
    public function getTodayAnalysis(User $user, string $date = null): array
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $dateStr = $date->format('Y-m-d');

        // Get all 4 predictions
        $prediction1 = $this->getPrediction1ForexFactory($user, $dateStr);
        $prediction2 = $this->getPrediction2Historical($user, $dateStr);
        $prediction3 = $this->getPrediction3Technical($dateStr);
        $prediction4 = $this->getPrediction4MSI();

        // Calculate final EA Safety Score
        $finalScore = $this->calculateFinalScore($prediction1, $prediction2, $prediction3, $prediction4);

        // Determine recommendation
        $recommendation = $this->getRecommendation($finalScore, $prediction1, $prediction2, $prediction3, $prediction4);

        // Get safe hours for today
        $safeHours = $this->getSafeHours($prediction1, $prediction2, $prediction3);

        return [
            'date' => $dateStr,
            'predictions' => [
                'prediction_1' => $prediction1,
                'prediction_2' => $prediction2,
                'prediction_3' => $prediction3,
                'prediction_4' => $prediction4,
            ],
            'final_score' => $finalScore,
            'recommendation' => $recommendation,
            'safe_hours' => $safeHours,
            'summary' => $this->generateSummary($finalScore, $recommendation, $safeHours),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Prediction 1: Forex Factory News Impact
     */
    private function getPrediction1ForexFactory(User $user, string $date): array
    {
        $news = NewsItem::where('created_by', $user->id)
            ->where('date', $date)
            ->get();

        $highImpactCount = $news->where('impact', 'high')->count();
        $mediumImpactCount = $news->where('impact', 'medium')->count();
        $dangerCount = $news->where('ea_status', 'danger')->count();

        // Calculate score
        $score = 100;
        $score -= ($highImpactCount * 20);
        $score -= ($mediumImpactCount * 10);
        $score -= ($dangerCount * 15);
        $score = max(0, min(100, $score));
        $status = $score >= 70 ? 'safe' : ($score >= 40 ? 'caution' : 'danger');
        
        // Get safe windows
        $safeWindows = $this->extractSafeWindows($news);

        // Enhance with Groq AI if available
        if ($this->groqService->isAvailable() && $news->count() > 0) {
            try {
                $newsArray = $news->map(function ($item) {
                    return [
                        'title' => $item->title,
                        'currency' => $item->currency,
                        'impact' => $item->impact,
                        'time' => $item->formatted_time,
                        'ea_status' => $item->ea_status,
                    ];
                })->toArray();

                $groqAnalysis = $this->groqService->analyzeNewsImpact($newsArray, $date);
                
                // Get minimum confidence threshold from config
                $minConfidence = config('services.groq.min_confidence', 60);
                
                // Use Groq analysis if confidence is high enough
                if (($groqAnalysis['confidence'] ?? 0) >= $minConfidence) {
                    return [
                        'name' => 'Forex Factory News Impact (AI Enhanced)',
                        'score' => round($groqAnalysis['score'] ?? $score, 1),
                        'status' => $groqAnalysis['status'] ?? $status,
                        'high_impact_count' => $highImpactCount,
                        'medium_impact_count' => $mediumImpactCount,
                        'danger_count' => $dangerCount,
                        'total_news' => $news->count(),
                        'safe_windows' => $safeWindows,
                        'weight' => 0.30,
                        'ai_enhanced' => true,
                        'ai_confidence' => round($groqAnalysis['confidence'] ?? 0, 1),
                        'ai_reasoning' => $groqAnalysis['reasoning'] ?? null,
                        'ai_recommendation' => $groqAnalysis['recommendation'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                \Log::warning('Groq analysis failed for Prediction 1', ['error' => $e->getMessage()]);
            }
        }

        return [
            'name' => 'Forex Factory News Impact',
            'score' => round($score, 1),
            'status' => $status,
            'high_impact_count' => $highImpactCount,
            'medium_impact_count' => $mediumImpactCount,
            'danger_count' => $dangerCount,
            'total_news' => $news->count(),
            'safe_windows' => $safeWindows,
            'weight' => 0.30,
            'ai_enhanced' => false,
        ];
    }

    /**
     * Prediction 2: Historical Data
     */
    private function getPrediction2Historical(User $user, string $date): array
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeek; // 0 = Sunday, 6 = Saturday
        $dayName = Carbon::parse($date)->format('l');

        // Get historical performance for this day of week (last 90 days)
        $accountIds = $user->accounts()->pluck('id')->toArray();
        
        if (empty($accountIds)) {
            return [
                'name' => 'Historical Data',
                'score' => 50,
                'status' => 'caution',
                'message' => 'No historical data available',
                'weight' => 0.20,
                'method' => 'rule_based',
            ];
        }

        $startDate = Carbon::parse($date)->subDays(90)->startOfDay();
        
        $historicalTrades = \App\Models\Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed')
            ->where('close_time', '>=', $startDate)
            ->whereRaw('DAYOFWEEK(close_time) = ?', [$dayOfWeek + 1]) // MySQL uses 1-7
            ->selectRaw('
                COUNT(*) as total_trades,
                SUM(profit) as total_profit,
                AVG(profit) as avg_profit,
                SUM(CASE WHEN profit > 0 THEN 1 ELSE 0 END) as winning_trades
            ')
            ->first();

        if (!$historicalTrades || $historicalTrades->total_trades == 0) {
            return [
                'name' => 'Historical Data',
                'score' => 50,
                'status' => 'caution',
                'message' => 'Insufficient historical data for this day',
                'weight' => 0.20,
                'method' => 'rule_based',
            ];
        }

        $winrate = ($historicalTrades->winning_trades / $historicalTrades->total_trades) * 100;
        $avgProfit = $historicalTrades->avg_profit;

        // Calculate score based on winrate and average profit
        $score = ($winrate * 0.6) + (min(100, max(0, ($avgProfit + 10) * 5)) * 0.4);
        $score = max(0, min(100, $score));
        $status = $score >= 60 ? 'safe' : ($score >= 40 ? 'caution' : 'danger');

        $historicalData = [
            'total_trades' => $historicalTrades->total_trades,
            'winrate' => $winrate,
            'avg_profit' => $avgProfit,
            'total_profit' => $historicalTrades->total_profit,
            'day_name' => $dayName,
        ];

        // Enhance with Groq AI if available
        if ($this->groqService->isAvailable() && $historicalTrades->total_trades > 0) {
            try {
                $groqAnalysis = $this->groqService->analyzeHistoricalPatterns($historicalData, $date);
                
                // Get minimum confidence threshold from config
                $minConfidence = config('services.groq.min_confidence', 60);
                
                // Use Groq analysis if confidence is high enough
                if (($groqAnalysis['confidence'] ?? 0) >= $minConfidence) {
                    return [
                        'name' => 'Historical Data (AI Enhanced)',
                        'score' => round($groqAnalysis['score'] ?? $score, 1),
                        'status' => $groqAnalysis['status'] ?? $status,
                        'day_name' => $dayName,
                        'total_trades' => $historicalTrades->total_trades,
                        'winrate' => round($winrate, 2),
                        'avg_profit' => round($avgProfit, 2),
                        'total_profit' => round($historicalTrades->total_profit, 2),
                        'weight' => 0.20,
                        'method' => 'rule_based',
                        'ai_enhanced' => true,
                        'ai_confidence' => round($groqAnalysis['confidence'] ?? 0, 1),
                        'ai_reasoning' => $groqAnalysis['reasoning'] ?? null,
                        'ai_recommendation' => $groqAnalysis['recommendation'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                \Log::warning('Groq analysis failed for Prediction 2', ['error' => $e->getMessage()]);
            }
        }

        return [
            'name' => 'Historical Data',
            'score' => round($score, 1),
            'status' => $status,
            'day_name' => $dayName,
            'total_trades' => $historicalTrades->total_trades,
            'winrate' => round($winrate, 2),
            'avg_profit' => round($avgProfit, 2),
            'total_profit' => round($historicalTrades->total_profit, 2),
            'weight' => 0.20,
            'method' => 'rule_based',
            'ai_enhanced' => false,
        ];
    }

    /**
     * Prediction 3: Technical Analysis
     */
    private function getPrediction3Technical(string $date): array
    {
        try {
            // Generate sample OHLC data for technical analysis
            // In production, this should fetch real-time data
            $ohlcData = $this->generateSampleOHLC();
            
            // Analyze using technical service
            $analysis = $this->technicalService->analyze($ohlcData);
            
            // Calculate average score from all timeframes
            $scores = [];
            $statuses = [];
            
            if (isset($analysis['predictions'])) {
                foreach ($analysis['predictions'] as $timeframe => $prediction) {
                    if (isset($prediction['overall_risk_score'])) {
                        $scores[] = 100 - $prediction['overall_risk_score']; // Convert risk to safety score
                        $statuses[] = $prediction['overall_status'] ?? 'caution';
                    }
                }
            }
            
            $avgScore = !empty($scores) ? array_sum($scores) / count($scores) : 50;
            
            // Determine overall status
            $dangerCount = count(array_filter($statuses, fn($s) => $s === 'danger' || $s === 'extreme_shock'));
            $status = $dangerCount > 0 ? 'danger' : ($avgScore >= 60 ? 'safe' : 'caution');

            // Enhance with Groq AI if available
            if ($this->groqService->isAvailable() && !empty($analysis)) {
                try {
                    $groqAnalysis = $this->groqService->analyzeTechnicalIndicators($analysis);
                    
                    // Use Groq analysis if confidence is high enough
                    if (($groqAnalysis['confidence'] ?? 0) >= config('services.groq.min_confidence', 60)) {
                        return [
                            'name' => 'Technical Analysis (AI Enhanced)',
                            'score' => round($groqAnalysis['score'] ?? $avgScore, 1),
                            'status' => $groqAnalysis['status'] ?? $status,
                            'timeframes' => $analysis['predictions'] ?? [],
                            'weight' => 0.30,
                            'ai_enhanced' => true,
                            'ai_confidence' => round($groqAnalysis['confidence'] ?? 0, 1),
                            'ai_reasoning' => $groqAnalysis['reasoning'] ?? null,
                            'ai_recommendation' => $groqAnalysis['recommendation'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Groq analysis failed for Prediction 3', ['error' => $e->getMessage()]);
                }
            }
            
            return [
                'name' => 'Technical Analysis',
                'score' => round($avgScore, 1),
                'status' => $status,
                'timeframes' => $analysis['predictions'] ?? [],
                'weight' => 0.30,
                'ai_enhanced' => false,
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Technical Analysis',
                'score' => 50,
                'status' => 'caution',
                'message' => 'Technical analysis unavailable: ' . $e->getMessage(),
                'weight' => 0.30,
            ];
        }
    }

    /**
     * Generate sample OHLC data for technical analysis.
     */
    private function generateSampleOHLC(): array
    {
        // Generate sample data - in production, fetch from TradingView or broker API
        $ohlc = [];
        $basePrice = 2000; // Example: XAUUSD
        
        // Generate M15 data (96 candles for 24 hours)
        for ($i = 0; $i < 96; $i++) {
            $ohlc['M15'][] = [
                'time' => now()->subMinutes(15 * (96 - $i))->timestamp,
                'open' => $basePrice + rand(-10, 10),
                'high' => $basePrice + rand(5, 15),
                'low' => $basePrice + rand(-15, -5),
                'close' => $basePrice + rand(-10, 10),
                'volume' => rand(100, 1000),
            ];
        }
        
        // Generate H1 data (24 candles)
        for ($i = 0; $i < 24; $i++) {
            $ohlc['H1'][] = [
                'time' => now()->subHours(24 - $i)->timestamp,
                'open' => $basePrice + rand(-20, 20),
                'high' => $basePrice + rand(10, 30),
                'low' => $basePrice + rand(-30, -10),
                'close' => $basePrice + rand(-20, 20),
                'volume' => rand(500, 2000),
            ];
        }
        
        // Generate H4 data (6 candles)
        for ($i = 0; $i < 6; $i++) {
            $ohlc['H4'][] = [
                'time' => now()->subHours(4 * (6 - $i))->timestamp,
                'open' => $basePrice + rand(-30, 30),
                'high' => $basePrice + rand(20, 50),
                'low' => $basePrice + rand(-50, -20),
                'close' => $basePrice + rand(-30, 30),
                'volume' => rand(1000, 5000),
            ];
        }
        
        return $ohlc;
    }

    /**
     * Prediction 4: Macro Stability Index (MSI)
     */
    private function getPrediction4MSI(): array
    {
        try {
            $marketData = $this->yahooService->getMarketData();
            $msiResult = $this->msiCalculator->calculate($marketData);

            $score = $msiResult['msi'];
            $status = strtolower($msiResult['status']);

            // Enhance with Groq AI if available
            if ($this->groqService->isAvailable()) {
                try {
                    $groqAnalysis = $this->groqService->analyzeMarketStability($msiResult);
                    
                    // Use Groq analysis if confidence is high enough
                    if (($groqAnalysis['confidence'] ?? 0) >= config('services.groq.min_confidence', 60)) {
                        return [
                            'name' => 'Market Stability Index (AI Enhanced)',
                            'score' => round($groqAnalysis['score'] ?? $score, 1),
                            'status' => $groqAnalysis['status'] ?? $status,
                            'msi_details' => $msiResult['details'],
                            'weight' => 0.20,
                            'ai_enhanced' => true,
                            'ai_confidence' => round($groqAnalysis['confidence'] ?? 0, 1),
                            'ai_reasoning' => $groqAnalysis['reasoning'] ?? null,
                            'ai_recommendation' => $groqAnalysis['recommendation'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Groq analysis failed for Prediction 4', ['error' => $e->getMessage()]);
                }
            }

            return [
                'name' => 'Market Stability Index',
                'score' => round($score, 1),
                'status' => $status,
                'msi_details' => $msiResult['details'],
                'weight' => 0.20,
                'ai_enhanced' => false,
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Market Stability Index',
                'score' => 50,
                'status' => 'caution',
                'message' => 'Unable to fetch MSI data: ' . $e->getMessage(),
                'weight' => 0.20,
            ];
        }
    }

    /**
     * Calculate final EA Safety Score from all predictions.
     */
    private function calculateFinalScore(array $p1, array $p2, array $p3, array $p4): array
    {
        $weightedScore = 
            ($p1['score'] * $p1['weight']) +
            ($p2['score'] * $p2['weight']) +
            ($p3['score'] * $p3['weight']) +
            ($p4['score'] * $p4['weight']);

        $finalScore = round($weightedScore, 1);

        // Determine overall status
        $status = $finalScore >= 70 ? 'safe' : ($finalScore >= 40 ? 'caution' : 'danger');

        return [
            'score' => $finalScore,
            'status' => $status,
            'breakdown' => [
                'prediction_1' => round($p1['score'] * $p1['weight'], 1),
                'prediction_2' => round($p2['score'] * $p2['weight'], 1),
                'prediction_3' => round($p3['score'] * $p3['weight'], 1),
                'prediction_4' => round($p4['score'] * $p4['weight'], 1),
            ],
        ];
    }

    /**
     * Get EA recommendation based on final score and predictions.
     */
    private function getRecommendation(array $finalScore, array $p1, array $p2, array $p3, array $p4): array
    {
        $score = $finalScore['score'];
        $status = $finalScore['status'];

        // Determine ON/OFF recommendation
        $shouldEnable = $status === 'safe' || ($status === 'caution' && $score >= 50);

        // Generate reasoning
        $reasons = [];
        if ($p1['status'] === 'danger') {
            $reasons[] = 'High impact news events detected';
        }
        if ($p2['status'] === 'danger') {
            $reasons[] = 'Poor historical performance on this day';
        }
        if ($p3['status'] === 'danger') {
            $reasons[] = 'Technical indicators show danger signals';
        }
        if ($p4['status'] === 'danger') {
            $reasons[] = 'Market stability index indicates high volatility';
        }

        if (empty($reasons)) {
            $reasons[] = 'All indicators suggest safe conditions';
        }

        return [
            'action' => $shouldEnable ? 'ON' : 'OFF',
            'confidence' => $this->calculateConfidence($finalScore, $p1, $p2, $p3, $p4),
            'reasons' => $reasons,
            'message' => $shouldEnable 
                ? 'EA Grid dapat diaktifkan dengan aman hari ini'
                : 'Disarankan untuk menonaktifkan EA Grid hari ini',
        ];
    }

    /**
     * Calculate confidence level for recommendation.
     */
    private function calculateConfidence(array $finalScore, array $p1, array $p2, array $p3, array $p4): int
    {
        // Count how many predictions have sufficient data
        $dataQuality = 0;
        if (isset($p1['total_news']) && $p1['total_news'] > 0) $dataQuality++;
        if (isset($p2['total_trades']) && $p2['total_trades'] > 0) $dataQuality++;
        if (isset($p3['timeframes']) && !empty($p3['timeframes'])) $dataQuality++;
        if (isset($p4['msi_details'])) $dataQuality++;

        // Base confidence on data quality and score consistency
        $baseConfidence = ($dataQuality / 4) * 100;
        $scoreConsistency = 100 - abs($finalScore['score'] - 50) * 2; // Higher if score is extreme (very safe or very dangerous)
        
        return round(($baseConfidence * 0.6) + ($scoreConsistency * 0.4));
    }

    /**
     * Extract safe hours from news data.
     */
    private function extractSafeWindows($news): array
    {
        $allHours = range(0, 23);
        $dangerHours = [];

        // Handle both Collection and array
        foreach ($news as $item) {
            // Handle both object and array access
            $eaStatus = is_object($item) ? $item->ea_status : ($item['ea_status'] ?? null);
            $time = is_object($item) ? $item->time : ($item['time'] ?? null);
            
            if ($eaStatus === 'danger' && $time) {
                $hour = (int) Carbon::parse($time)->format('H');
                // Mark 2 hours before and after as dangerous
                for ($i = max(0, $hour - 2); $i <= min(23, $hour + 2); $i++) {
                    $dangerHours[$i] = true;
                }
            }
        }

        $safeHours = array_filter($allHours, function($hour) use ($dangerHours) {
            return !isset($dangerHours[$hour]);
        });

        // Group consecutive hours
        $windows = [];
        $currentWindow = null;

        foreach ($safeHours as $hour) {
            if ($currentWindow === null || $hour !== end($currentWindow) + 1) {
                if ($currentWindow !== null) {
                    $windows[] = [
                        'start' => min($currentWindow),
                        'end' => max($currentWindow),
                    ];
                }
                $currentWindow = [$hour];
            } else {
                $currentWindow[] = $hour;
            }
        }

        if ($currentWindow !== null) {
            $windows[] = [
                'start' => min($currentWindow),
                'end' => max($currentWindow),
            ];
        }

        return $windows;
    }

    /**
     * Get safe hours for today.
     */
    private function getSafeHours(array $p1, array $p2, array $p3): array
    {
        $safeWindows = $p1['safe_windows'] ?? [];

        // Format windows
        $formatted = [];
        foreach ($safeWindows as $window) {
            $formatted[] = [
                'start' => str_pad($window['start'], 2, '0', STR_PAD_LEFT) . ':00',
                'end' => str_pad($window['end'], 2, '0', STR_PAD_LEFT) . ':59',
                'duration' => ($window['end'] - $window['start'] + 1) . ' hours',
            ];
        }

        return $formatted;
    }

    /**
     * Generate human-readable summary.
     */
    private function generateSummary(array $finalScore, array $recommendation, array $safeHours): string
    {
        $score = $finalScore['score'];
        $status = $finalScore['status'];

        $summary = "Berdasarkan analisis 4 prediksi hari ini, skor EA Safety adalah {$score}/100 ({$status}). ";
        $summary .= $recommendation['message'] . " ";

        if (!empty($safeHours)) {
            $summary .= "Jadwal aman untuk EA: " . implode(', ', array_map(function($w) {
                return $w['start'] . ' - ' . $w['end'];
            }, $safeHours)) . ". ";
        }

        $summary .= "Confidence level: {$recommendation['confidence']}%.";

        return $summary;
    }
}
