<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GroqService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.groq.com/openai/v1';
    protected string $model;
    protected bool $enabled;
    protected int $timeout;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key', '');
        $this->enabled = config('services.groq.enabled', false);
        $this->model = config('services.groq.model', 'llama-3.3-70b-versatile');
        $this->timeout = config('services.groq.timeout', 30);
        $this->cacheTtl = config('services.groq.cache_ttl', 3600); // 1 hour
    }

    /**
     * Check if Groq service is available
     */
    public function isAvailable(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    /**
     * Analyze news impact for Prediction 1
     */
    public function analyzeNewsImpact(array $newsItems, string $date): array
    {
        if (!$this->isAvailable()) {
            return $this->getDefaultAnalysis('news');
        }

        $cacheKey = "groq:news:{$date}:" . md5(json_encode($newsItems));
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($newsItems, $date) {
            $prompt = $this->buildNewsAnalysisPrompt($newsItems, $date);
            return $this->callGroq($prompt, 'news_impact');
        });
    }

    /**
     * Analyze historical patterns for Prediction 2
     */
    public function analyzeHistoricalPatterns(array $historicalData, string $date): array
    {
        if (!$this->isAvailable()) {
            return $this->getDefaultAnalysis('historical');
        }

        $cacheKey = "groq:historical:{$date}:" . md5(json_encode($historicalData));
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($historicalData, $date) {
            $prompt = $this->buildHistoricalAnalysisPrompt($historicalData, $date);
            return $this->callGroq($prompt, 'historical_patterns');
        });
    }

    /**
     * Analyze technical indicators for Prediction 3
     */
    public function analyzeTechnicalIndicators(array $technicalData): array
    {
        if (!$this->isAvailable()) {
            return $this->getDefaultAnalysis('technical');
        }

        $cacheKey = "groq:technical:" . md5(json_encode($technicalData));
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($technicalData) {
            $prompt = $this->buildTechnicalAnalysisPrompt($technicalData);
            return $this->callGroq($prompt, 'technical_analysis');
        });
    }

    /**
     * Analyze market stability for Prediction 4
     */
    public function analyzeMarketStability(array $msiData): array
    {
        if (!$this->isAvailable()) {
            return $this->getDefaultAnalysis('msi');
        }

        $cacheKey = "groq:msi:" . md5(json_encode($msiData));
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($msiData) {
            $prompt = $this->buildMSIAnalysisPrompt($msiData);
            return $this->callGroq($prompt, 'market_stability');
        });
    }

    /**
     * Call Groq API
     */
    protected function callGroq(string $prompt, string $context): array
    {
        try {
            Log::info('Calling Groq API', [
                'context' => $context,
                'model' => $this->model,
                'has_api_key' => !empty($this->apiKey)
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an expert trading analyst specializing in EA Grid trading systems. Provide accurate, data-driven analysis with clear recommendations.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.3, // Lower temperature for more consistent analysis
                    'max_tokens' => 1000,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Groq API response received', [
                    'context' => $context,
                    'successful' => true
                ]);

                return $this->parseGroqResponse($data, $context);
            } else {
                Log::warning('Groq API error', [
                    'context' => $context,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                // Handle rate limit or quota errors
                if ($response->status() === 429) {
                    Log::warning('Groq API rate limit exceeded', ['context' => $context]);
                }

                return $this->getDefaultAnalysis($context);
            }
        } catch (\Exception $e) {
            Log::error('Groq API exception', [
                'context' => $context,
                'error' => $e->getMessage()
            ]);

            return $this->getDefaultAnalysis($context);
        }
    }

    /**
     * Parse Groq API response
     */
    protected function parseGroqResponse(array $data, string $context): array
    {
        try {
            $content = $data['choices'][0]['message']['content'] ?? '';
            
            // Try to extract JSON from response
            $jsonMatch = [];
            if (preg_match('/\{[^}]+\}/s', $content, $jsonMatch)) {
                $parsed = json_decode($jsonMatch[0], true);
                if ($parsed) {
                    return [
                        'status' => $this->extractStatus($parsed),
                        'score' => $this->extractScore($parsed),
                        'confidence' => $this->extractConfidence($parsed),
                        'recommendation' => $parsed['recommendation'] ?? $parsed['message'] ?? null,
                        'reasoning' => $parsed['reasoning'] ?? $parsed['explanation'] ?? $content,
                        'method' => 'groq'
                    ];
                }
            }

            // Fallback: parse from text
            return [
                'status' => $this->extractStatusFromText($content),
                'score' => $this->extractScoreFromText($content),
                'confidence' => $this->extractConfidenceFromText($content),
                'recommendation' => $this->extractRecommendationFromText($content),
                'reasoning' => $content,
                'method' => 'groq'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to parse Groq response', [
                'context' => $context,
                'error' => $e->getMessage()
            ]);

            return $this->getDefaultAnalysis($context);
        }
    }

    /**
     * Build prompt for news impact analysis
     */
    protected function buildNewsAnalysisPrompt(array $newsItems, string $date): string
    {
        $newsSummary = [];
        foreach ($newsItems as $news) {
            $newsSummary[] = sprintf(
                "- %s (%s) at %s - Impact: %s - EA Status: %s",
                $news['title'] ?? 'N/A',
                $news['currency'] ?? 'N/A',
                $news['time'] ?? 'N/A',
                $news['impact'] ?? 'N/A',
                $news['ea_status'] ?? 'not set'
            );
        }

        return sprintf(
            "Analyze Forex Factory news impact for EA Grid trading on %s.\n\n" .
            "News Events:\n%s\n\n" .
            "Provide analysis in JSON format:\n" .
            '{"status": "safe|caution|danger", "score": 0-100, "confidence": 0-100, "recommendation": "...", "reasoning": "..."}' . "\n\n" .
            "Consider:\n" .
            "1. High-impact news events that could cause volatility spikes\n" .
            "2. Currency pairs affected\n" .
            "3. Time windows when EA should be disabled\n" .
            "4. Overall risk level for EA Grid trading\n\n" .
            "Score: 100 = very safe, 0 = very dangerous\n" .
            "Status: SAFE (score >= 70), CAUTION (40-69), DANGER (< 40)",
            $date,
            implode("\n", $newsSummary)
        );
    }

    /**
     * Build prompt for historical analysis
     */
    protected function buildHistoricalAnalysisPrompt(array $historicalData, string $date): string
    {
        return sprintf(
            "Analyze historical trading patterns for EA Grid on %s.\n\n" .
            "Historical Data:\n" .
            "- Day of Week: %s\n" .
            "- Total Trades: %d\n" .
            "- Win Rate: %.2f%%\n" .
            "- Average Profit: %.2f\n" .
            "- Total Profit: %.2f\n\n" .
            "Provide analysis in JSON format:\n" .
            '{"status": "safe|caution|danger", "score": 0-100, "confidence": 0-100, "recommendation": "...", "reasoning": "..."}' . "\n\n" .
            "Consider:\n" .
            "1. Historical performance on this day of week\n" .
            "2. Win rate and profitability trends\n" .
            "3. Risk factors based on past performance\n" .
            "4. Recommendations for EA Grid activation\n\n" .
            "Score: 100 = very safe, 0 = very dangerous",
            $date,
            $historicalData['day_name'] ?? 'Unknown',
            $historicalData['total_trades'] ?? 0,
            $historicalData['winrate'] ?? 0,
            $historicalData['avg_profit'] ?? 0,
            $historicalData['total_profit'] ?? 0
        );
    }

    /**
     * Build prompt for technical analysis
     */
    protected function buildTechnicalAnalysisPrompt(array $technicalData): string
    {
        $summary = [];
        if (isset($technicalData['predictions'])) {
            foreach ($technicalData['predictions'] as $timeframe => $prediction) {
                $summary[] = sprintf(
                    "%s: Status=%s, Risk Score=%d",
                    $timeframe,
                    $prediction['overall_status'] ?? 'unknown',
                    $prediction['overall_risk_score'] ?? 0
                );
            }
        }

        return sprintf(
            "Analyze technical indicators for EA Grid trading.\n\n" .
            "Technical Analysis Summary:\n%s\n\n" .
            "Overall Risk Score: %d/100\n" .
            "Overall Status: %s\n\n" .
            "Provide analysis in JSON format:\n" .
            '{"status": "safe|caution|danger", "score": 0-100, "confidence": 0-100, "recommendation": "...", "reasoning": "..."}' . "\n\n" .
            "Consider:\n" .
            "1. Technical indicators across timeframes (M15, H1, H4)\n" .
            "2. Risk signals from pattern recognition, ATR shock, order flow\n" .
            "3. Market regime classification\n" .
            "4. Overall technical safety for EA Grid\n\n" .
            "Score: 100 = very safe, 0 = very dangerous",
            implode("\n", $summary),
            $technicalData['overall']['risk_score'] ?? 50,
            $technicalData['overall']['status'] ?? 'caution'
        );
    }

    /**
     * Build prompt for MSI analysis
     */
    protected function buildMSIAnalysisPrompt(array $msiData): string
    {
        $details = $msiData['details'] ?? [];
        
        return sprintf(
            "Analyze Market Stability Index (MSI) for EA Grid trading.\n\n" .
            "Market Indicators:\n" .
            "- DXY: %.2f (Score: %.1f)\n" .
            "- VIX: %.2f (Score: %.1f)\n" .
            "- GVZ: %.2f (Score: %.1f)\n" .
            "- US 10Y Yield: %.2f%% (Score: %.1f)\n" .
            "- S&P500 Futures: %.2f (Score: %.1f)\n" .
            "- Correlation: %.3f (Score: %.1f)\n\n" .
            "MSI Score: %.1f/100\n" .
            "MSI Status: %s\n\n" .
            "Provide analysis in JSON format:\n" .
            '{"status": "safe|caution|danger", "score": 0-100, "confidence": 0-100, "recommendation": "...", "reasoning": "..."}' . "\n\n" .
            "Consider:\n" .
            "1. Global market stability indicators\n" .
            "2. Volatility levels (VIX, GVZ)\n" .
            "3. Currency correlation patterns\n" .
            "4. Overall market regime for EA Grid safety\n\n" .
            "Score: 100 = very stable (safe for EA Grid), 0 = very unstable (dangerous)",
            $details['dxy'] ?? 0, $details['dxyScore'] ?? 0,
            $details['vix'] ?? 0, $details['vixScore'] ?? 0,
            $details['gvz'] ?? 0, $details['gvzScore'] ?? 0,
            $details['us10y'] ?? 0, $details['yieldScore'] ?? 0,
            $details['spx'] ?? 0, $details['spxScore'] ?? 0,
            $details['correlation'] ?? 0, $details['correlationScore'] ?? 0,
            $msiData['msi'] ?? 50,
            $msiData['status'] ?? 'CAUTION'
        );
    }

    /**
     * Extract status from parsed data
     */
    protected function extractStatus(array $data): string
    {
        $status = strtolower($data['status'] ?? '');
        if (in_array($status, ['safe', 'caution', 'danger'])) {
            return $status;
        }
        return 'caution';
    }

    /**
     * Extract score from parsed data
     */
    protected function extractScore(array $data): float
    {
        $score = $data['score'] ?? 50;
        return max(0, min(100, (float)$score));
    }

    /**
     * Extract confidence from parsed data
     */
    protected function extractConfidence(array $data): float
    {
        $confidence = $data['confidence'] ?? 70;
        return max(0, min(100, (float)$confidence));
    }

    /**
     * Extract status from text
     */
    protected function extractStatusFromText(string $text): string
    {
        $text = strtolower($text);
        if (strpos($text, 'danger') !== false || strpos($text, 'unsafe') !== false) {
            return 'danger';
        }
        if (strpos($text, 'safe') !== false || strpos($text, 'good') !== false) {
            return 'safe';
        }
        return 'caution';
    }

    /**
     * Extract score from text
     */
    protected function extractScoreFromText(string $text): float
    {
        if (preg_match('/score[:\s]+(\d+)/i', $text, $matches)) {
            return max(0, min(100, (float)$matches[1]));
        }
        return 50;
    }

    /**
     * Extract confidence from text
     */
    protected function extractConfidenceFromText(string $text): float
    {
        if (preg_match('/confidence[:\s]+(\d+)/i', $text, $matches)) {
            return max(0, min(100, (float)$matches[1]));
        }
        return 70;
    }

    /**
     * Extract recommendation from text
     */
    protected function extractRecommendationFromText(string $text): string
    {
        // Try to find recommendation section
        if (preg_match('/recommendation[:\s]+(.+?)(?:\n|$)/i', $text, $matches)) {
            return trim($matches[1]);
        }
        return 'Based on analysis, use rule-based prediction.';
    }

    /**
     * Get default analysis when AI is unavailable
     */
    protected function getDefaultAnalysis(string $context): array
    {
        return [
            'status' => 'caution',
            'score' => 50,
            'confidence' => 0,
            'recommendation' => 'AI analysis unavailable. Using rule-based prediction.',
            'reasoning' => 'Groq AI service is not available or failed.',
            'method' => 'fallback'
        ];
    }
}

