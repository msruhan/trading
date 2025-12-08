<?php

namespace App\Services;

use Carbon\Carbon;

class TechnicalAnalysisService
{
    /**
     * Analyze technical indicators and return comprehensive prediction.
     * 
     * @param array $ohlcData Multi-timeframe OHLC data
     * @return array
     */
    public function analyze(array $ohlcData): array
    {
        $results = [];
        
        // Module 1: Trend Strength Model (TSM)
        $results['trend_strength'] = $this->analyzeTrendStrength($ohlcData);
        
        // Module 2: Probability of Breakout (POB)
        $results['breakout_probability'] = $this->analyzeBreakoutProbability($ohlcData);
        
        // Module 3: Liquidity Map Model
        $results['liquidity_map'] = $this->analyzeLiquidityMap($ohlcData);
        
        // Module 4: Pattern Recognition
        $results['patterns'] = $this->recognizePatterns($ohlcData);
        
        // Module 5: Dynamic ATR Shock Detector
        $results['atr_shock'] = $this->detectATRShock($ohlcData);
        
        // Module 6: Order Flow Proxy Model
        $results['order_flow'] = $this->analyzeOrderFlow($ohlcData);
        
        // Module 7: Market Regime Classification
        $results['market_regime'] = $this->classifyMarketRegime($ohlcData);
        
        // Calculate overall risk score and recommendation
        $overall = $this->calculateOverallRisk($results);
        
        return [
            'modules' => $results,
            'overall' => $overall,
            'timestamp' => now()->toIso8601String(),
        ];
    }
    
    /**
     * Analyze technical indicators for a specific timeframe.
     * 
     * @param array $ohlcData OHLC data (can include multiple timeframes for context)
     * @param string $primaryTimeframe The primary timeframe to analyze (15m, H1, H4)
     * @return array
     */
    public function analyzeForTimeframe(array $ohlcData, string $primaryTimeframe): array
    {
        // Use the primary timeframe as the main data source
        // Other timeframes can be used for context (e.g., H4 for H1 analysis)
        $results = [];
        
        // Module 1: Trend Strength Model (TSM) - based on primary timeframe
        $results['trend_strength'] = $this->analyzeTrendStrengthForTimeframe($ohlcData, $primaryTimeframe);
        
        // Module 2: Probability of Breakout (POB) - based on primary timeframe
        $results['breakout_probability'] = $this->analyzeBreakoutProbabilityForTimeframe($ohlcData, $primaryTimeframe);
        
        // Module 3: Liquidity Map Model - based on primary timeframe
        $results['liquidity_map'] = $this->analyzeLiquidityMapForTimeframe($ohlcData, $primaryTimeframe);
        
        // Module 4: Pattern Recognition - based on primary timeframe
        $results['patterns'] = $this->recognizePatternsForTimeframe($ohlcData, $primaryTimeframe);
        
        // Module 5: Dynamic ATR Shock Detector - based on primary timeframe
        $results['atr_shock'] = $this->detectATRShockForTimeframe($ohlcData, $primaryTimeframe);
        
        // Module 6: Order Flow Proxy Model - based on primary timeframe
        $results['order_flow'] = $this->analyzeOrderFlowForTimeframe($ohlcData, $primaryTimeframe);
        
        // Module 7: Market Regime Classification - based on primary timeframe
        $results['market_regime'] = $this->classifyMarketRegimeForTimeframe($ohlcData, $primaryTimeframe);
        
        // Calculate overall risk score and recommendation
        $overall = $this->calculateOverallRisk($results);
        
        return [
            'modules' => $results,
            'overall' => $overall,
            'timestamp' => now()->toIso8601String(),
        ];
    }
    
    /**
     * Module 1: Trend Strength Model (TSM)
     * Analyze trend strength using MA slope, ADX, and candle size.
     */
    private function analyzeTrendStrength(array $ohlcData): array
    {
        // Try to get H1 first, then fallback to primary timeframe
        $primary = $this->getTimeframeData($ohlcData, 'H1');
        if (empty($primary)) {
            // Try 15m
            $primary = $this->getTimeframeData($ohlcData, '15m');
        }
        if (empty($primary)) {
            // Try H4
            $primary = $this->getTimeframeData($ohlcData, 'H4');
        }
        
        if (empty($primary) || count($primary) < 20) {
            return $this->defaultModuleResult('trend_strength', 'insufficient_data');
        }
        
        $h1 = $primary; // Use primary data
        
        // Calculate MA (20 period)
        $ma20 = $this->calculateMA($h1, 20);
        $ma10 = $this->calculateMA($h1, 10);
        
        if (count($ma20) < 2) {
            return $this->defaultModuleResult('trend_strength', 'insufficient_data');
        }
        
        // Calculate MA slope
        $maNow = end($ma20);
        $maPrev = $ma20[count($ma20) - 2];
        $period = 1; // 1 hour
        $slope = ($maNow - $maPrev) / $period;
        
        // Calculate ADX (14 period)
        $adx = $this->calculateADX($h1, 14);
        
        // Calculate average candle size
        $candleSizes = [];
        foreach (array_slice($h1, -20) as $candle) {
            $candleSizes[] = abs($candle['close'] - $candle['open']);
        }
        $avgCandleSize = array_sum($candleSizes) / count($candleSizes);
        $currentCandleSize = abs(end($h1)['close'] - end($h1)['open']);
        
        // Classify trend strength
        $status = 'normal';
        $confidence = 50;
        $reasons = [];
        
        // ADX classification
        if ($adx >= 25) {
            if ($adx >= 50) {
                $status = 'strong_trend';
                $confidence = 85;
                $reasons[] = [
                    'factor' => 'ADX',
                    'value' => round($adx, 2),
                    'weight' => 40,
                    'explanation' => 'ADX sangat tinggi (' . round($adx, 2) . '), menunjukkan trend sangat kuat. EA sebaiknya di-pause.'
                ];
            } else {
                $status = 'moderately_trending';
                $confidence = 65;
                $reasons[] = [
                    'factor' => 'ADX',
                    'value' => round($adx, 2),
                    'weight' => 30,
                    'explanation' => 'ADX sedang (' . round($adx, 2) . '), menunjukkan trend sedang. Monitor dengan hati-hati.'
                ];
            }
        } else {
            $reasons[] = [
                'factor' => 'ADX',
                'value' => round($adx, 2),
                'weight' => 20,
                'explanation' => 'ADX rendah (' . round($adx, 2) . '), market cenderung sideways. EA relatif aman.'
            ];
        }
        
        // MA Slope analysis
        $slopePct = abs($slope) / end($h1)['close'] * 100;
        if ($slopePct > 0.1) {
            if ($status === 'normal') {
                $status = 'moderately_trending';
                $confidence = max($confidence, 60);
            }
            $reasons[] = [
                'factor' => 'MA Slope',
                'value' => round($slopePct, 3) . '%/hour',
                'weight' => 20,
                'explanation' => 'MA slope signifikan (' . round($slopePct, 3) . '%/hour), menunjukkan pergerakan trend.'
            ];
        }
        
        // Candle size analysis
        if ($currentCandleSize > 1.5 * $avgCandleSize) {
            if ($status === 'normal') {
                $status = 'moderately_trending';
                $confidence = max($confidence, 55);
            }
            $reasons[] = [
                'factor' => 'Candle Size',
                'value' => round($currentCandleSize, 2) . ' vs avg ' . round($avgCandleSize, 2),
                'weight' => 15,
                'explanation' => 'Candle size besar (' . round($currentCandleSize / $avgCandleSize, 2) . 'x rata-rata), menunjukkan volatilitas tinggi.'
            ];
        }
        
        return [
            'module' => 'Trend Strength Model (TSM)',
            'status' => $status,
            'status_label' => $this->getStatusLabel($status),
            'confidence' => $confidence,
            'indicators' => [
                'adx' => round($adx, 2),
                'ma_slope' => round($slope, 4),
                'ma_slope_pct' => round($slopePct, 3),
                'current_candle_size' => round($currentCandleSize, 2),
                'avg_candle_size' => round($avgCandleSize, 2),
                'ma20' => round($maNow, 2),
                'ma10' => round(end($ma10), 2),
            ],
            'reasons' => $reasons,
            'recommendation' => $this->getTSMRecommendation($status, $adx),
        ];
    }
    
    /**
     * Module 2: Probability of Breakout (POB Model)
     * Analyze compression and breakout probability.
     */
    private function analyzeBreakoutProbability(array $ohlcData): array
    {
        $h1 = $this->getTimeframeData($ohlcData, 'H1');
        $h4 = $this->getTimeframeData($ohlcData, 'H4');
        
        if (empty($h1) || count($h1) < 20) {
            return $this->defaultModuleResult('breakout_probability', 'insufficient_data');
        }
        
        // Calculate H1 range
        $h1Ranges = [];
        foreach (array_slice($h1, -20) as $candle) {
            $h1Ranges[] = $candle['high'] - $candle['low'];
        }
        $avgH1Range = array_sum($h1Ranges) / count($h1Ranges);
        $currentH1Range = end($h1)['high'] - end($h1)['low'];
        
        // Calculate H4 range if available
        $h4Range = null;
        $avgH4Range = null;
        if (!empty($h4) && count($h4) >= 20) {
            $h4Ranges = [];
            foreach (array_slice($h4, -20) as $candle) {
                $h4Ranges[] = $candle['high'] - $candle['low'];
            }
            $avgH4Range = array_sum($h4Ranges) / count($h4Ranges);
            $h4Range = end($h4)['high'] - end($h4)['low'];
        }
        
        // Detect compression
        $compressionRatio = $currentH1Range / $avgH1Range;
        $isCompressed = $compressionRatio < 0.5;
        
        // Calculate breakout probability
        $probability = 20; // Default safe
        $status = 'safe';
        $confidence = 50;
        $reasons = [];
        
        if ($isCompressed) {
            if ($compressionRatio < 0.3) {
                $probability = 80;
                $status = 'high_risk';
                $confidence = 85;
                $reasons[] = [
                    'factor' => 'Compression',
                    'value' => round($compressionRatio * 100, 1) . '% of average',
                    'weight' => 40,
                    'explanation' => 'Range sangat terkompresi (' . round($compressionRatio * 100, 1) . '% dari rata-rata). Potensi breakout besar sangat tinggi (80%).'
                ];
            } elseif ($compressionRatio < 0.5) {
                $probability = 50;
                $status = 'caution';
                $confidence = 65;
                $reasons[] = [
                    'factor' => 'Compression',
                    'value' => round($compressionRatio * 100, 1) . '% of average',
                    'weight' => 30,
                    'explanation' => 'Range terkompresi (' . round($compressionRatio * 100, 1) . '% dari rata-rata). Potensi breakout sedang (50%).'
                ];
            }
        } else {
            $reasons[] = [
                'factor' => 'Range Analysis',
                'value' => round($compressionRatio * 100, 1) . '% of average',
                'weight' => 20,
                'explanation' => 'Range normal (' . round($compressionRatio * 100, 1) . '% dari rata-rata). Potensi breakout rendah (20%).'
            ];
        }
        
        // H4 compression check
        if ($h4Range !== null && $avgH4Range !== null) {
            $h4CompressionRatio = $h4Range / $avgH4Range;
            if ($h4CompressionRatio < 0.5) {
                $probability = min(100, $probability + 15);
                if ($status === 'safe') {
                    $status = 'caution';
                }
                $reasons[] = [
                    'factor' => 'H4 Compression',
                    'value' => round($h4CompressionRatio * 100, 1) . '% of average',
                    'weight' => 15,
                    'explanation' => 'H4 juga terkompresi, meningkatkan probabilitas breakout.'
                ];
            }
        }
        
        return [
            'module' => 'Probability of Breakout (POB)',
            'status' => $status,
            'status_label' => $this->getStatusLabel($status),
            'confidence' => $confidence,
            'probability' => $probability,
            'indicators' => [
                'h1_range' => round($currentH1Range, 2),
                'h1_avg_range' => round($avgH1Range, 2),
                'compression_ratio' => round($compressionRatio, 3),
                'h4_range' => $h4Range ? round($h4Range, 2) : null,
                'h4_avg_range' => $avgH4Range ? round($avgH4Range, 2) : null,
            ],
            'reasons' => $reasons,
            'recommendation' => $this->getPOBRecommendation($probability, $status),
        ];
    }
    
    /**
     * Module 3: Liquidity Map Model
     * Detect equal highs/lows, FVG, and swing structure.
     */
    private function analyzeLiquidityMap(array $ohlcData): array
    {
        $h1 = $this->getTimeframeData($ohlcData, 'H1');
        $h4 = $this->getTimeframeData($ohlcData, 'H4');
        
        if (empty($h1) || count($h1) < 10) {
            return $this->defaultModuleResult('liquidity_map', 'insufficient_data');
        }
        
        $currentPrice = end($h1)['close'];
        $threshold = $currentPrice * 0.001; // 0.1% threshold
        
        // Find equal highs and lows
        $equalHighs = [];
        $equalLows = [];
        $highs = array_column($h1, 'high');
        $lows = array_column($h1, 'low');
        
        // Check for equal highs (last 20 candles)
        $recentHighs = array_slice($highs, -20);
        for ($i = 0; $i < count($recentHighs) - 1; $i++) {
            for ($j = $i + 1; $j < count($recentHighs); $j++) {
                if (abs($recentHighs[$i] - $recentHighs[$j]) < $threshold) {
                    $equalHighs[] = ($recentHighs[$i] + $recentHighs[$j]) / 2;
                }
            }
        }
        
        // Check for equal lows
        $recentLows = array_slice($lows, -20);
        for ($i = 0; $i < count($recentLows) - 1; $i++) {
            for ($j = $i + 1; $j < count($recentLows); $j++) {
                if (abs($recentLows[$i] - $recentLows[$j]) < $threshold) {
                    $equalLows[] = ($recentLows[$i] + $recentLows[$j]) / 2;
                }
            }
        }
        
        // Find Fair Value Gaps (FVG) / Imbalance
        $fgvs = [];
        for ($i = 1; $i < count($h1) - 1; $i++) {
            $prev = $h1[$i - 1];
            $curr = $h1[$i];
            $next = $h1[$i + 1];
            
            // Bullish FVG: gap up
            if ($prev['high'] < $next['low'] && $curr['low'] > $prev['high']) {
                $fgvs[] = [
                    'type' => 'bullish',
                    'start' => $prev['high'],
                    'end' => $next['low'],
                    'filled' => $currentPrice >= $prev['high'] && $currentPrice <= $next['low'],
                ];
            }
            
            // Bearish FVG: gap down
            if ($prev['low'] > $next['high'] && $curr['high'] < $prev['low']) {
                $fgvs[] = [
                    'type' => 'bearish',
                    'start' => $next['high'],
                    'end' => $prev['low'],
                    'filled' => $currentPrice <= $prev['low'] && $currentPrice >= $next['high'],
                ];
            }
        }
        
        // Find swing highs and lows
        $swingHighs = [];
        $swingLows = [];
        for ($i = 2; $i < count($h1) - 2; $i++) {
            // Swing high
            if ($h1[$i]['high'] > $h1[$i-1]['high'] && 
                $h1[$i]['high'] > $h1[$i-2]['high'] &&
                $h1[$i]['high'] > $h1[$i+1]['high'] &&
                $h1[$i]['high'] > $h1[$i+2]['high']) {
                $swingHighs[] = $h1[$i]['high'];
            }
            
            // Swing low
            if ($h1[$i]['low'] < $h1[$i-1]['low'] && 
                $h1[$i]['low'] < $h1[$i-2]['low'] &&
                $h1[$i]['low'] < $h1[$i+1]['low'] &&
                $h1[$i]['low'] < $h1[$i+2]['low']) {
                $swingLows[] = $h1[$i]['low'];
            }
        }
        
        // Check if price is near liquidity zones
        $nearLiquidity = false;
        $liquidityZones = [];
        $dangerDistance = $currentPrice * 0.002; // 0.2% distance
        
        foreach ($equalHighs as $high) {
            if (abs($currentPrice - $high) < $dangerDistance) {
                $nearLiquidity = true;
                $liquidityZones[] = ['type' => 'equal_high', 'price' => $high, 'distance' => abs($currentPrice - $high)];
            }
        }
        
        foreach ($equalLows as $low) {
            if (abs($currentPrice - $low) < $dangerDistance) {
                $nearLiquidity = true;
                $liquidityZones[] = ['type' => 'equal_low', 'price' => $low, 'distance' => abs($currentPrice - $low)];
            }
        }
        
        // Check unfilled FVGs
        $unfilledFvgs = array_filter($fgvs, fn($fvg) => !$fvg['filled']);
        if (count($unfilledFvgs) > 0) {
            foreach ($unfilledFvgs as $fvg) {
                if ($currentPrice >= $fvg['start'] && $currentPrice <= $fvg['end']) {
                    $nearLiquidity = true;
                    $liquidityZones[] = ['type' => 'fvg', 'price' => ($fvg['start'] + $fvg['end']) / 2, 'distance' => 0];
                }
            }
        }
        
        // Determine status
        $status = $nearLiquidity ? 'danger' : 'safe';
        $confidence = $nearLiquidity ? 75 : 60;
        
        $reasons = [];
        if ($nearLiquidity) {
            $reasons[] = [
                'factor' => 'Liquidity Zone',
                'value' => count($liquidityZones) . ' zones detected',
                'weight' => 35,
                'explanation' => 'Harga mendekati area likuiditas (' . count($liquidityZones) . ' zona terdeteksi). Potensi pergerakan besar saat liquidity diambil.'
            ];
        } else {
            $reasons[] = [
                'factor' => 'Liquidity Distance',
                'value' => 'Safe distance',
                'weight' => 25,
                'explanation' => 'Harga tidak dekat dengan area likuiditas utama. Relatif aman.'
            ];
        }
        
        if (count($equalHighs) > 0 || count($equalLows) > 0) {
            $reasons[] = [
                'factor' => 'Equal Highs/Lows',
                'value' => count($equalHighs) . ' highs, ' . count($equalLows) . ' lows',
                'weight' => 20,
                'explanation' => 'Equal highs/lows terdeteksi, menunjukkan area konsolidasi dan potensi liquidity pool.'
            ];
        }
        
        if (count($unfilledFvgs) > 0) {
            $reasons[] = [
                'factor' => 'Unfilled FVG',
                'value' => count($unfilledFvgs) . ' gaps',
                'weight' => 15,
                'explanation' => 'Fair Value Gaps belum terisi, harga mungkin akan kembali untuk mengisi gap.'
            ];
        }
        
        return [
            'module' => 'Liquidity Map Model',
            'status' => $status,
            'status_label' => $this->getStatusLabel($status),
            'confidence' => $confidence,
            'indicators' => [
                'current_price' => round($currentPrice, 2),
                'equal_highs_count' => count($equalHighs),
                'equal_lows_count' => count($equalLows),
                'fgv_count' => count($fgvs),
                'unfilled_fvg_count' => count($unfilledFvgs),
                'swing_highs_count' => count($swingHighs),
                'swing_lows_count' => count($swingLows),
                'near_liquidity' => $nearLiquidity,
                'liquidity_zones' => $liquidityZones,
            ],
            'reasons' => $reasons,
            'recommendation' => $this->getLiquidityRecommendation($status, $nearLiquidity, count($liquidityZones)),
        ];
    }
    
    /**
     * Module 4: Pattern Recognition
     * Detect candlestick patterns.
     */
    private function recognizePatterns(array $ohlcData): array
    {
        $h1 = $this->getTimeframeData($ohlcData, 'H1');
        $m15 = $this->getTimeframeData($ohlcData, '15m');
        
        if (empty($h1) || count($h1) < 5) {
            return $this->defaultModuleResult('pattern_recognition', 'insufficient_data');
        }
        
        $patterns = [];
        $reasons = [];
        
        // Analyze last 5 candles
        $recent = array_slice($h1, -5);
        
        // 1. Marubozu detection
        foreach ($recent as $idx => $candle) {
            $range = $candle['high'] - $candle['low'];
            $body = abs($candle['close'] - $candle['open']);
            
            if ($range > 0 && $body / $range > 0.8) {
                $patterns[] = [
                    'type' => 'marubozu',
                    'direction' => $candle['close'] > $candle['open'] ? 'bullish' : 'bearish',
                    'strength' => 'strong',
                    'candle_index' => $idx,
                ];
            }
        }
        
        // 2. Engulfing pattern
        if (count($recent) >= 2) {
            $prev = $recent[count($recent) - 2];
            $curr = $recent[count($recent) - 1];
            
            // Bullish engulfing
            if ($prev['close'] < $prev['open'] && 
                $curr['close'] > $curr['open'] &&
                $curr['open'] < $prev['close'] &&
                $curr['close'] > $prev['open']) {
                $patterns[] = [
                    'type' => 'engulfing',
                    'direction' => 'bullish',
                    'strength' => 'moderate',
                ];
            }
            
            // Bearish engulfing
            if ($prev['close'] > $prev['open'] && 
                $curr['close'] < $curr['open'] &&
                $curr['open'] > $prev['close'] &&
                $curr['close'] < $prev['open']) {
                $patterns[] = [
                    'type' => 'engulfing',
                    'direction' => 'bearish',
                    'strength' => 'moderate',
                ];
            }
        }
        
        // 3. Double top/bottom (simplified)
        $highs = array_column($recent, 'high');
        $lows = array_column($recent, 'low');
        $maxHigh = max($highs);
        $minLow = min($lows);
        $threshold = ($maxHigh - $minLow) * 0.02; // 2% threshold
        
        $highCount = 0;
        $lowCount = 0;
        foreach ($highs as $high) {
            if (abs($high - $maxHigh) < $threshold) $highCount++;
        }
        foreach ($lows as $low) {
            if (abs($low - $minLow) < $threshold) $lowCount++;
        }
        
        if ($highCount >= 2) {
            $patterns[] = [
                'type' => 'double_top',
                'direction' => 'bearish',
                'strength' => 'moderate',
            ];
        }
        
        if ($lowCount >= 2) {
            $patterns[] = [
                'type' => 'double_bottom',
                'direction' => 'bullish',
                'strength' => 'moderate',
            ];
        }
        
        // 4. 3-candle strong trend
        if (count($recent) >= 3) {
            $last3 = array_slice($recent, -3);
            $allBullish = true;
            $allBearish = true;
            
            foreach ($last3 as $candle) {
                if ($candle['close'] <= $candle['open']) $allBullish = false;
                if ($candle['close'] >= $candle['open']) $allBearish = false;
            }
            
            if ($allBullish) {
                $patterns[] = [
                    'type' => 'strong_trend',
                    'direction' => 'bullish',
                    'strength' => 'strong',
                    'candles' => 3,
                ];
            } elseif ($allBearish) {
                $patterns[] = [
                    'type' => 'strong_trend',
                    'direction' => 'bearish',
                    'strength' => 'strong',
                    'candles' => 3,
                ];
            }
        }
        
        // 5. Long wick (rejection)
        foreach ($recent as $idx => $candle) {
            $range = $candle['high'] - $candle['low'];
            $body = abs($candle['close'] - $candle['open']);
            $upperWick = $candle['high'] - max($candle['close'], $candle['open']);
            $lowerWick = min($candle['close'], $candle['open']) - $candle['low'];
            
            if ($range > 0) {
                if ($upperWick / $range > 0.5) {
                    $patterns[] = [
                        'type' => 'rejection',
                        'direction' => 'bearish',
                        'strength' => 'moderate',
                        'location' => 'upper',
                        'candle_index' => $idx,
                    ];
                }
                
                if ($lowerWick / $range > 0.5) {
                    $patterns[] = [
                        'type' => 'rejection',
                        'direction' => 'bullish',
                        'strength' => 'moderate',
                        'location' => 'lower',
                        'candle_index' => $idx,
                    ];
                }
            }
        }
        
        // Calculate status based on patterns
        $status = 'safe';
        $confidence = 50;
        $dangerPatterns = array_filter($patterns, fn($p) => 
            $p['type'] === 'marubozu' || 
            $p['type'] === 'strong_trend' ||
            ($p['type'] === 'engulfing' && $p['strength'] === 'strong')
        );
        
        if (count($dangerPatterns) > 0) {
            $status = 'caution';
            $confidence = 70;
        }
        
        if (count($dangerPatterns) >= 2) {
            $status = 'danger';
            $confidence = 85;
        }
        
        // Build reasons
        if (count($patterns) > 0) {
            $patternNames = array_map(fn($p) => $p['type'], $patterns);
            $reasons[] = [
                'factor' => 'Pattern Detection',
                'value' => count($patterns) . ' patterns: ' . implode(', ', array_unique($patternNames)),
                'weight' => count($dangerPatterns) * 15,
                'explanation' => 'Ditemukan ' . count($patterns) . ' pola candlestick. ' . 
                    (count($dangerPatterns) > 0 ? count($dangerPatterns) . ' pola menunjukkan trend kuat.' : 'Pola menunjukkan kondisi normal.')
            ];
        } else {
            $reasons[] = [
                'factor' => 'Pattern Detection',
                'value' => 'No significant patterns',
                'weight' => 10,
                'explanation' => 'Tidak ada pola candlestick signifikan yang terdeteksi. Market dalam kondisi normal.'
            ];
        }
        
        return [
            'module' => 'Pattern Recognition',
            'status' => $status,
            'status_label' => $this->getStatusLabel($status),
            'confidence' => $confidence,
            'indicators' => [
                'patterns_detected' => count($patterns),
                'danger_patterns' => count($dangerPatterns),
                'patterns' => $patterns,
            ],
            'reasons' => $reasons,
            'recommendation' => $this->getPatternRecommendation($status, count($patterns), count($dangerPatterns)),
        ];
    }
    
    /**
     * Module 5: Dynamic ATR Shock Detector
     * Detect volatility shocks using ATR.
     */
    private function detectATRShock(array $ohlcData): array
    {
        $h1 = $this->getTimeframeData($ohlcData, 'H1');
        
        if (empty($h1) || count($h1) < 20) {
            return $this->defaultModuleResult('atr_shock', 'insufficient_data');
        }
        
        // Calculate ATR(14)
        $atr14 = $this->calculateATR($h1, 14);
        
        if (empty($atr14)) {
            return $this->defaultModuleResult('atr_shock', 'insufficient_data');
        }
        
        $currentATR = end($atr14);
        $avgATR = array_sum($atr14) / count($atr14);
        
        // Calculate ratio
        $atrRatio = $currentATR / $avgATR;
        
        // Classify shock level
        $status = 'normal';
        $confidence = 50;
        $reasons = [];
        
        if ($atrRatio >= 2.0) {
            $status = 'extreme_shock';
            $confidence = 90;
            $reasons[] = [
                'factor' => 'ATR Shock',
                'value' => round($atrRatio, 2) . 'x average',
                'weight' => 45,
                'explanation' => 'ATR sangat tinggi (' . round($atrRatio, 2) . 'x rata-rata). Extreme shock terdeteksi. EA HARUS di-pause.'
            ];
        } elseif ($atrRatio >= 1.5) {
            $status = 'shock';
            $confidence = 75;
            $reasons[] = [
                'factor' => 'ATR Shock',
                'value' => round($atrRatio, 2) . 'x average',
                'weight' => 35,
                'explanation' => 'ATR tinggi (' . round($atrRatio, 2) . 'x rata-rata). Shock terdeteksi. EA sebaiknya di-pause.'
            ];
        } else {
            $reasons[] = [
                'factor' => 'ATR Analysis',
                'value' => round($atrRatio, 2) . 'x average',
                'weight' => 20,
                'explanation' => 'ATR dalam batas normal (' . round($atrRatio, 2) . 'x rata-rata). Volatilitas terkendali.'
            ];
        }
        
        return [
            'module' => 'Dynamic ATR Shock Detector',
            'status' => $status,
            'status_label' => $this->getStatusLabel($status),
            'confidence' => $confidence,
            'indicators' => [
                'atr14' => round($currentATR, 2),
                'atr_avg' => round($avgATR, 2),
                'atr_ratio' => round($atrRatio, 2),
            ],
            'reasons' => $reasons,
            'recommendation' => $this->getATRRecommendation($status, $atrRatio),
        ];
    }
    
    /**
     * Module 6: Order Flow Proxy Model
     * Analyze order flow using body/wick analysis.
     */
    private function analyzeOrderFlow(array $ohlcData): array
    {
        $h1 = $this->getTimeframeData($ohlcData, 'H1');
        $m15 = $this->getTimeframeData($ohlcData, '15m');
        
        if (empty($h1) || count($h1) < 5) {
            return $this->defaultModuleResult('order_flow', 'insufficient_data');
        }
        
        $recent = array_slice($h1, -5);
        $current = end($recent);
        
        // Calculate wicks and body
        $upperWick = $current['high'] - max($current['close'], $current['open']);
        $lowerWick = min($current['close'], $current['open']) - $current['low'];
        $body = abs($current['close'] - $current['open']);
        $range = $current['high'] - $current['low'];
        
        // Analyze order flow signals
        $signals = [];
        $reasons = [];
        
        // Large body + small wick = continuation
        if ($range > 0) {
            $bodyRatio = $body / $range;
            $upperWickRatio = $upperWick / $range;
            $lowerWickRatio = $lowerWick / $range;
            
            if ($bodyRatio > 0.7 && ($upperWickRatio < 0.15 || $lowerWickRatio < 0.15)) {
                $signals[] = [
                    'type' => 'continuation',
                    'direction' => $current['close'] > $current['open'] ? 'bullish' : 'bearish',
                    'strength' => 'strong',
                ];
            }
            
            // Long wick = rejection
            if ($upperWickRatio > 0.5) {
                $signals[] = [
                    'type' => 'rejection',
                    'direction' => 'bearish',
                    'strength' => 'moderate',
                ];
            }
            
            if ($lowerWickRatio > 0.5) {
                $signals[] = [
                    'type' => 'rejection',
                    'direction' => 'bullish',
                    'strength' => 'moderate',
                ];
            }
        }
        
        // Rapid range expansion
        if (count($recent) >= 3) {
            $ranges = [];
            foreach ($recent as $candle) {
                $ranges[] = $candle['high'] - $candle['low'];
            }
            
            $avgRange = array_sum(array_slice($ranges, 0, -1)) / (count($ranges) - 1);
            $currentRange = end($ranges);
            
            if ($currentRange > 1.5 * $avgRange) {
                $signals[] = [
                    'type' => 'range_expansion',
                    'direction' => 'neutral',
                    'strength' => 'strong',
                ];
            }
        }
        
        // Determine status
        $status = 'safe';
        $confidence = 50;
        
        $strongSignals = array_filter($signals, fn($s) => $s['strength'] === 'strong');
        if (count($strongSignals) > 0) {
            $status = 'caution';
            $confidence = 70;
        }
        
        if (count($strongSignals) >= 2) {
            $status = 'danger';
            $confidence = 85;
        }
        
        if (count($signals) > 0) {
            $reasons[] = [
                'factor' => 'Order Flow Signals',
                'value' => count($signals) . ' signals detected',
                'weight' => count($strongSignals) * 20,
                'explanation' => 'Ditemukan ' . count($signals) . ' sinyal order flow. ' . 
                    (count($strongSignals) > 0 ? count($strongSignals) . ' sinyal kuat menunjukkan tekanan order besar.' : '')
            ];
        } else {
            $reasons[] = [
                'factor' => 'Order Flow',
                'value' => 'Balanced',
                'weight' => 15,
                'explanation' => 'Order flow seimbang, tidak ada tekanan order yang signifikan.'
            ];
        }
        
        return [
            'module' => 'Order Flow Proxy Model',
            'status' => $status,
            'status_label' => $this->getStatusLabel($status),
            'confidence' => $confidence,
            'indicators' => [
                'body_ratio' => $range > 0 ? round($body / $range, 3) : 0,
                'upper_wick_ratio' => $range > 0 ? round($upperWick / $range, 3) : 0,
                'lower_wick_ratio' => $range > 0 ? round($lowerWick / $range, 3) : 0,
                'signals_count' => count($signals),
                'strong_signals_count' => count($strongSignals),
                'signals' => $signals,
            ],
            'reasons' => $reasons,
            'recommendation' => $this->getOrderFlowRecommendation($status, count($signals), count($strongSignals)),
        ];
    }
    
    /**
     * Module 7: Market Regime Classification
     * Classify market regime using ML-like approach.
     */
    private function classifyMarketRegime(array $ohlcData): array
    {
        $h1 = $this->getTimeframeData($ohlcData, 'H1');
        
        if (empty($h1) || count($h1) < 20) {
            return $this->defaultModuleResult('market_regime', 'insufficient_data');
        }
        
        // Calculate indicators
        $atr = $this->calculateATR($h1, 14);
        $rsi = $this->calculateRSI($h1, 14);
        $ma20 = $this->calculateMA($h1, 20);
        
        if (empty($atr) || empty($rsi) || empty($ma20)) {
            return $this->defaultModuleResult('market_regime', 'insufficient_data');
        }
        
        $currentATR = end($atr);
        $avgATR = array_sum($atr) / count($atr);
        $currentRSI = end($rsi);
        $currentPrice = end($h1)['close'];
        $ma20Value = end($ma20);
        
        // Calculate MA slope
        $maSlope = count($ma20) >= 2 ? (end($ma20) - $ma20[count($ma20) - 2]) : 0;
        $maSlopePct = $ma20Value > 0 ? abs($maSlope) / $ma20Value * 100 : 0;
        
        // Calculate volatility
        $volatilities = [];
        foreach (array_slice($h1, -20) as $candle) {
            $volatilities[] = abs($candle['close'] - $candle['open']) / $candle['open'];
        }
        $volatility = array_sum($volatilities) / count($volatilities);
        
        // Determine session
        $hour = now()->hour;
        $session = 'asia';
        if ($hour >= 8 && $hour < 16) {
            $session = 'europe';
        } elseif ($hour >= 13 && $hour < 21) {
            $session = 'us';
        }
        
        // Determine day of week
        $dayOfWeek = now()->dayOfWeek; // 0 = Sunday, 1 = Monday, etc.
        $isMonday = $dayOfWeek === 1;
        
        // Classify regime using rule-based approach (simplified ML)
        $regime = 0; // Sideways
        $confidence = 50;
        $reasons = [];
        
        // Rule 1: ATR-based
        $atrRatio = $currentATR / $avgATR;
        if ($atrRatio >= 2.0) {
            $regime = 3; // Shock event
            $confidence = 90;
            $reasons[] = [
                'factor' => 'ATR Shock',
                'value' => round($atrRatio, 2) . 'x average',
                'weight' => 40,
                'explanation' => 'ATR extreme (' . round($atrRatio, 2) . 'x), menunjukkan shock event.'
            ];
        } elseif ($atrRatio >= 1.5) {
            if ($regime < 2) {
                $regime = 2; // Strong trend
                $confidence = 75;
            }
            $reasons[] = [
                'factor' => 'ATR Elevated',
                'value' => round($atrRatio, 2) . 'x average',
                'weight' => 25,
                'explanation' => 'ATR tinggi menunjukkan volatilitas meningkat.'
            ];
        }
        
        // Rule 2: MA Slope
        if ($maSlopePct > 0.15) {
            if ($regime < 2) {
                $regime = 2; // Strong trend
                $confidence = max($confidence, 80);
            }
            $reasons[] = [
                'factor' => 'MA Slope',
                'value' => round($maSlopePct, 3) . '%/hour',
                'weight' => 20,
                'explanation' => 'MA slope signifikan menunjukkan trend kuat.'
            ];
        } elseif ($maSlopePct > 0.05) {
            if ($regime < 1) {
                $regime = 1; // Light trend
                $confidence = max($confidence, 60);
            }
            $reasons[] = [
                'factor' => 'MA Slope',
                'value' => round($maSlopePct, 3) . '%/hour',
                'weight' => 15,
                'explanation' => 'MA slope ringan menunjukkan trend sedang.'
            ];
        }
        
        // Rule 3: RSI extremes
        if ($currentRSI >= 70) {
            if ($regime < 1) {
                $regime = 1;
            }
            $reasons[] = [
                'factor' => 'RSI Overbought',
                'value' => round($currentRSI, 2),
                'weight' => 15,
                'explanation' => 'RSI overbought (' . round($currentRSI, 2) . '), potensi reversal.'
            ];
        } elseif ($currentRSI <= 30) {
            if ($regime < 1) {
                $regime = 1;
            }
            $reasons[] = [
                'factor' => 'RSI Oversold',
                'value' => round($currentRSI, 2),
                'weight' => 15,
                'explanation' => 'RSI oversold (' . round($currentRSI, 2) . '), potensi reversal.'
            ];
        }
        
        // Rule 4: Volatility
        if ($volatility > 0.01) { // 1% volatility
            if ($regime < 1) {
                $regime = 1;
            }
            $reasons[] = [
                'factor' => 'Volatility',
                'value' => round($volatility * 100, 2) . '%',
                'weight' => 10,
                'explanation' => 'Volatilitas tinggi (' . round($volatility * 100, 2) . '%).'
            ];
        }
        
        // Rule 5: Session effect
        if ($session === 'us' || $session === 'europe') {
            $reasons[] = [
                'factor' => 'Market Session',
                'value' => strtoupper($session),
                'weight' => 5,
                'explanation' => 'Sesi ' . strtoupper($session) . ' biasanya lebih volatile.'
            ];
        }
        
        // Rule 6: Monday effect
        if ($isMonday) {
            $reasons[] = [
                'factor' => 'Day of Week',
                'value' => 'Monday',
                'weight' => 5,
                'explanation' => 'Monday sering terjadi fake moves dan gap.'
            ];
        }
        
        // Map regime to status
        $statusMap = [
            0 => 'safe',      // Sideways
            1 => 'caution',   // Light trend
            2 => 'danger',    // Strong trend
            3 => 'danger',    // Shock event
        ];
        
        $status = $statusMap[$regime] ?? 'safe';
        $regimeLabels = [
            0 => 'Sideways',
            1 => 'Light Trend',
            2 => 'Strong Trend',
            3 => 'Shock Event',
        ];
        
        return [
            'module' => 'Market Regime Classification',
            'status' => $status,
            'status_label' => $this->getStatusLabel($status),
            'confidence' => $confidence,
            'regime' => $regime,
            'regime_label' => $regimeLabels[$regime],
            'indicators' => [
                'atr_ratio' => round($atrRatio, 2),
                'rsi' => round($currentRSI, 2),
                'ma_slope_pct' => round($maSlopePct, 3),
                'volatility' => round($volatility * 100, 2),
                'session' => $session,
                'day_of_week' => $dayOfWeek,
                'is_monday' => $isMonday,
            ],
            'reasons' => $reasons,
            'recommendation' => $this->getRegimeRecommendation($regime, $regimeLabels[$regime]),
        ];
    }
    
    /**
     * Calculate overall risk score and recommendation.
     */
    private function calculateOverallRisk(array $modules): array
    {
        $scores = [];
        $weights = [
            'trend_strength' => 20,
            'breakout_probability' => 15,
            'liquidity_map' => 15,
            'patterns' => 15,
            'atr_shock' => 20, // Highest weight - most important for EA Grid
            'order_flow' => 10,
            'market_regime' => 5,
        ];
        
        $statusScores = [
            'safe' => 0,
            'normal' => 0,
            'caution' => 20,
            'moderately_trending' => 30,
            'high_risk' => 40,
            'danger' => 60,
            'strong_trend' => 70,
            'shock' => 80,
            'extreme_shock' => 100,
        ];
        
        $totalWeight = 0;
        $weightedScore = 0;
        
        foreach ($modules as $moduleName => $moduleData) {
            if (!isset($moduleData['status']) || $moduleData['status'] === 'insufficient_data') {
                continue;
            }
            
            $status = $moduleData['status'];
            $score = $statusScores[$status] ?? 0;
            $weight = $weights[$moduleName] ?? 10;
            
            $scores[$moduleName] = [
                'status' => $status,
                'score' => $score,
                'weight' => $weight,
                'contribution' => $score * $weight / 100,
            ];
            
            $weightedScore += $score * $weight;
            $totalWeight += $weight;
        }
        
        if ($totalWeight === 0) {
            return [
                'risk_score' => 0,
                'status' => 'unknown',
                'status_label' => 'Unknown',
                'confidence' => 0,
                'recommendation' => 'Data tidak cukup untuk analisis teknikal.',
            ];
        }
        
        $finalScore = $weightedScore / $totalWeight;
        
        // Determine overall status
        $overallStatus = 'safe';
        if ($finalScore >= 70) {
            $overallStatus = 'danger';
        } elseif ($finalScore >= 50) {
            $overallStatus = 'caution';
        } elseif ($finalScore >= 30) {
            $overallStatus = 'moderate';
        }
        
        // Count danger modules
        $dangerCount = 0;
        $cautionCount = 0;
        foreach ($scores as $scoreData) {
            if (in_array($scoreData['status'], ['danger', 'strong_trend', 'shock', 'extreme_shock', 'high_risk'])) {
                $dangerCount++;
            } elseif (in_array($scoreData['status'], ['caution', 'moderately_trending'])) {
                $cautionCount++;
            }
        }
        
        // Generate recommendation
        $recommendation = $this->generateOverallRecommendation($overallStatus, $finalScore, $dangerCount, $cautionCount);
        
        return [
            'risk_score' => round($finalScore, 1),
            'status' => $overallStatus,
            'status_label' => $this->getStatusLabel($overallStatus),
            'confidence' => min(100, round($finalScore + 20, 1)), // Boost confidence based on score
            'module_scores' => $scores,
            'danger_modules' => $dangerCount,
            'caution_modules' => $cautionCount,
            'safe_modules' => count($scores) - $dangerCount - $cautionCount,
            'recommendation' => $recommendation,
        ];
    }
    
    // ============================================
    // HELPER METHODS
    // ============================================
    
    /**
     * Get timeframe data from OHLC array.
     */
    private function getTimeframeData(array $ohlcData, string $timeframe): array
    {
        return $ohlcData[$timeframe] ?? [];
    }
    
    /**
     * Wrapper methods for timeframe-specific analysis.
     * These methods use the primary timeframe data but can reference other timeframes for context.
     */
    private function analyzeTrendStrengthForTimeframe(array $ohlcData, string $primaryTimeframe): array
    {
        // Use primary timeframe, but can use H1 for M15, H4 for H1
        $primaryData = $this->getTimeframeData($ohlcData, $primaryTimeframe);
        if (empty($primaryData)) {
            return $this->defaultModuleResult('trend_strength', 'insufficient_data');
        }
        
        // Create modified ohlcData with primary timeframe as main data
        $modifiedData = [$primaryTimeframe => $primaryData];
        if ($primaryTimeframe === '15m' && isset($ohlcData['H1'])) {
            $modifiedData['H1'] = $ohlcData['H1'];
        } elseif ($primaryTimeframe === 'H1' && isset($ohlcData['H4'])) {
            $modifiedData['H4'] = $ohlcData['H4'];
        }
        
        return $this->analyzeTrendStrength($modifiedData);
    }
    
    private function analyzeBreakoutProbabilityForTimeframe(array $ohlcData, string $primaryTimeframe): array
    {
        $primaryData = $this->getTimeframeData($ohlcData, $primaryTimeframe);
        if (empty($primaryData)) {
            return $this->defaultModuleResult('breakout_probability', 'insufficient_data');
        }
        
        $modifiedData = [$primaryTimeframe => $primaryData];
        // For H1, can use H4 for comparison
        if ($primaryTimeframe === 'H1' && isset($ohlcData['H4'])) {
            $modifiedData['H4'] = $ohlcData['H4'];
        }
        
        return $this->analyzeBreakoutProbability($modifiedData);
    }
    
    private function analyzeLiquidityMapForTimeframe(array $ohlcData, string $primaryTimeframe): array
    {
        $primaryData = $this->getTimeframeData($ohlcData, $primaryTimeframe);
        if (empty($primaryData)) {
            return $this->defaultModuleResult('liquidity_map', 'insufficient_data');
        }
        
        $modifiedData = [$primaryTimeframe => $primaryData];
        if ($primaryTimeframe === 'H1' && isset($ohlcData['H4'])) {
            $modifiedData['H4'] = $ohlcData['H4'];
        }
        
        return $this->analyzeLiquidityMap($modifiedData);
    }
    
    private function recognizePatternsForTimeframe(array $ohlcData, string $primaryTimeframe): array
    {
        $primaryData = $this->getTimeframeData($ohlcData, $primaryTimeframe);
        if (empty($primaryData)) {
            return $this->defaultModuleResult('pattern_recognition', 'insufficient_data');
        }
        
        $modifiedData = [$primaryTimeframe => $primaryData];
        if ($primaryTimeframe === '15m' && isset($ohlcData['H1'])) {
            $modifiedData['H1'] = $ohlcData['H1'];
        }
        
        return $this->recognizePatterns($modifiedData);
    }
    
    private function detectATRShockForTimeframe(array $ohlcData, string $primaryTimeframe): array
    {
        $primaryData = $this->getTimeframeData($ohlcData, $primaryTimeframe);
        if (empty($primaryData)) {
            return $this->defaultModuleResult('atr_shock', 'insufficient_data');
        }
        
        $modifiedData = [$primaryTimeframe => $primaryData];
        return $this->detectATRShock($modifiedData);
    }
    
    private function analyzeOrderFlowForTimeframe(array $ohlcData, string $primaryTimeframe): array
    {
        $primaryData = $this->getTimeframeData($ohlcData, $primaryTimeframe);
        if (empty($primaryData)) {
            return $this->defaultModuleResult('order_flow', 'insufficient_data');
        }
        
        $modifiedData = [$primaryTimeframe => $primaryData];
        if ($primaryTimeframe === '15m' && isset($ohlcData['H1'])) {
            $modifiedData['H1'] = $ohlcData['H1'];
        }
        
        return $this->analyzeOrderFlow($modifiedData);
    }
    
    private function classifyMarketRegimeForTimeframe(array $ohlcData, string $primaryTimeframe): array
    {
        $primaryData = $this->getTimeframeData($ohlcData, $primaryTimeframe);
        if (empty($primaryData)) {
            return $this->defaultModuleResult('market_regime', 'insufficient_data');
        }
        
        $modifiedData = [$primaryTimeframe => $primaryData];
        return $this->classifyMarketRegime($modifiedData);
    }
    
    /**
     * Calculate Moving Average.
     */
    private function calculateMA(array $candles, int $period): array
    {
        $ma = [];
        for ($i = $period - 1; $i < count($candles); $i++) {
            $sum = 0;
            for ($j = $i - $period + 1; $j <= $i; $j++) {
                $sum += $candles[$j]['close'];
            }
            $ma[] = $sum / $period;
        }
        return $ma;
    }
    
    /**
     * Calculate ADX (Average Directional Index).
     */
    private function calculateADX(array $candles, int $period): float
    {
        if (count($candles) < $period + 1) {
            return 0;
        }
        
        // Calculate +DM and -DM
        $plusDM = [];
        $minusDM = [];
        $tr = [];
        
        for ($i = 1; $i < count($candles); $i++) {
            $highDiff = $candles[$i]['high'] - $candles[$i-1]['high'];
            $lowDiff = $candles[$i-1]['low'] - $candles[$i]['low'];
            
            $plusDM[] = $highDiff > $lowDiff && $highDiff > 0 ? $highDiff : 0;
            $minusDM[] = $lowDiff > $highDiff && $lowDiff > 0 ? $lowDiff : 0;
            
            $tr[] = max(
                $candles[$i]['high'] - $candles[$i]['low'],
                abs($candles[$i]['high'] - $candles[$i-1]['close']),
                abs($candles[$i]['low'] - $candles[$i-1]['close'])
            );
        }
        
        // Smooth DM and TR
        $smoothedPlusDM = array_sum(array_slice($plusDM, -$period)) / $period;
        $smoothedMinusDM = array_sum(array_slice($minusDM, -$period)) / $period;
        $smoothedTR = array_sum(array_slice($tr, -$period)) / $period;
        
        if ($smoothedTR == 0) {
            return 0;
        }
        
        // Calculate DI+ and DI-
        $diPlus = ($smoothedPlusDM / $smoothedTR) * 100;
        $diMinus = ($smoothedMinusDM / $smoothedTR) * 100;
        
        // Calculate DX
        $diSum = $diPlus + $diMinus;
        if ($diSum == 0) {
            return 0;
        }
        
        $dx = abs($diPlus - $diMinus) / $diSum * 100;
        
        return $dx;
    }
    
    /**
     * Calculate ATR (Average True Range).
     */
    private function calculateATR(array $candles, int $period): array
    {
        if (count($candles) < $period + 1) {
            return [];
        }
        
        $tr = [];
        for ($i = 1; $i < count($candles); $i++) {
            $tr[] = max(
                $candles[$i]['high'] - $candles[$i]['low'],
                abs($candles[$i]['high'] - $candles[$i-1]['close']),
                abs($candles[$i]['low'] - $candles[$i-1]['close'])
            );
        }
        
        $atr = [];
        for ($i = $period - 1; $i < count($tr); $i++) {
            $sum = 0;
            for ($j = $i - $period + 1; $j <= $i; $j++) {
                $sum += $tr[$j];
            }
            $atr[] = $sum / $period;
        }
        
        return $atr;
    }
    
    /**
     * Calculate RSI (Relative Strength Index).
     */
    private function calculateRSI(array $candles, int $period): array
    {
        if (count($candles) < $period + 1) {
            return [];
        }
        
        $gains = [];
        $losses = [];
        
        for ($i = 1; $i < count($candles); $i++) {
            $change = $candles[$i]['close'] - $candles[$i-1]['close'];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }
        
        $rsi = [];
        for ($i = $period - 1; $i < count($gains); $i++) {
            $avgGain = array_sum(array_slice($gains, $i - $period + 1, $period)) / $period;
            $avgLoss = array_sum(array_slice($losses, $i - $period + 1, $period)) / $period;
            
            if ($avgLoss == 0) {
                $rsi[] = 100;
            } else {
                $rs = $avgGain / $avgLoss;
                $rsi[] = 100 - (100 / (1 + $rs));
            }
        }
        
        return $rsi;
    }
    
    /**
     * Get status label.
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'safe' => '🟢 Safe',
            'normal' => '🟢 Normal',
            'moderate' => '🟡 Moderate',
            'caution' => '🟡 Caution',
            'moderately_trending' => '🟡 Moderately Trending',
            'high_risk' => '🔴 High Risk',
            'danger' => '🔴 Danger',
            'strong_trend' => '🔴 Strong Trend',
            'shock' => '🔴 Shock',
            'extreme_shock' => '🔴 Extreme Shock',
        ];
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * Default module result when data is insufficient.
     */
    private function defaultModuleResult(string $module, string $reason): array
    {
        return [
            'module' => $module,
            'status' => 'insufficient_data',
            'status_label' => '⚠️ Insufficient Data',
            'confidence' => 0,
            'indicators' => [],
            'reasons' => [[
                'factor' => 'Data',
                'value' => 'Insufficient',
                'weight' => 0,
                'explanation' => 'Data OHLC tidak cukup untuk analisis. Pastikan data dari TradingView tersedia.'
            ]],
            'recommendation' => 'Data tidak cukup. Pastikan chart TradingView terhubung dan mengirim data OHLC.',
        ];
    }
    
    /**
     * Generate recommendations for each module.
     */
    private function getTSMRecommendation(string $status, float $adx): string
    {
        if ($status === 'strong_trend') {
            return "🔴 PAUSE EA - Trend sangat kuat (ADX: " . round($adx, 2) . "). Market dalam kondisi trending, tidak cocok untuk EA Grid.";
        } elseif ($status === 'moderately_trending') {
            return "🟡 MONITOR - Trend sedang (ADX: " . round($adx, 2) . "). Perhatikan pergerakan, pertimbangkan reduce position size.";
        } else {
            return "🟢 EA SAFE - Market sideways (ADX: " . round($adx, 2) . "). Kondisi ideal untuk EA Grid.";
        }
    }
    
    private function getPOBRecommendation(int $probability, string $status): string
    {
        if ($probability >= 80) {
            return "🔴 PAUSE EA - Probabilitas breakout sangat tinggi (80%+). Range sangat terkompresi, potensi pergerakan besar.";
        } elseif ($probability >= 50) {
            return "🟡 MONITOR - Probabilitas breakout sedang (50%). Range terkompresi, waspada pergerakan tiba-tiba.";
        } else {
            return "🟢 EA SAFE - Probabilitas breakout rendah (20%). Range normal, kondisi stabil.";
        }
    }
    
    private function getLiquidityRecommendation(string $status, bool $nearLiquidity, int $zoneCount): string
    {
        if ($nearLiquidity && $zoneCount > 0) {
            return "🔴 CAUTION - Harga dekat area likuiditas (" . $zoneCount . " zona). Potensi pergerakan besar saat liquidity diambil.";
        } else {
            return "🟢 EA SAFE - Harga tidak dekat area likuiditas utama. Relatif aman.";
        }
    }
    
    private function getPatternRecommendation(string $status, int $patternCount, int $dangerCount): string
    {
        if ($dangerCount >= 2) {
            return "🔴 PAUSE EA - Multiple pola trend kuat terdeteksi (" . $dangerCount . " pola). Market menunjukkan momentum kuat.";
        } elseif ($dangerCount >= 1) {
            return "🟡 MONITOR - Pola trend terdeteksi (" . $patternCount . " pola). Perhatikan pergerakan selanjutnya.";
        } else {
            return "🟢 EA SAFE - Tidak ada pola trend signifikan. Market dalam kondisi normal.";
        }
    }
    
    private function getATRRecommendation(string $status, float $atrRatio): string
    {
        if ($status === 'extreme_shock') {
            return "🔴 PAUSE EA - Extreme shock terdeteksi (ATR: " . round($atrRatio, 2) . "x). Volatilitas sangat tinggi, EA HARUS di-pause.";
        } elseif ($status === 'shock') {
            return "🔴 PAUSE EA - Shock terdeteksi (ATR: " . round($atrRatio, 2) . "x). Volatilitas tinggi, EA sebaiknya di-pause.";
        } else {
            return "🟢 EA SAFE - ATR dalam batas normal (" . round($atrRatio, 2) . "x). Volatilitas terkendali.";
        }
    }
    
    private function getOrderFlowRecommendation(string $status, int $signalCount, int $strongCount): string
    {
        if ($strongCount >= 2) {
            return "🔴 CAUTION - Multiple sinyal order flow kuat (" . $strongCount . " sinyal). Tekanan order besar terdeteksi.";
        } elseif ($strongCount >= 1) {
            return "🟡 MONITOR - Sinyal order flow terdeteksi (" . $signalCount . " sinyal). Perhatikan tekanan order.";
        } else {
            return "🟢 EA SAFE - Order flow seimbang. Tidak ada tekanan order signifikan.";
        }
    }
    
    private function getRegimeRecommendation(int $regime, string $regimeLabel): string
    {
        if ($regime === 3) {
            return "🔴 PAUSE EA - Shock Event terdeteksi. Market dalam kondisi ekstrem, EA HARUS di-pause.";
        } elseif ($regime === 2) {
            return "🔴 PAUSE EA - Strong Trend terdeteksi. Market trending kuat, tidak cocok untuk EA Grid.";
        } elseif ($regime === 1) {
            return "🟡 MONITOR - Light Trend terdeteksi. Perhatikan pergerakan, pertimbangkan adjust position.";
        } else {
            return "🟢 EA SAFE - Sideways market. Kondisi ideal untuk EA Grid.";
        }
    }
    
    private function generateOverallRecommendation(string $status, float $score, int $dangerCount, int $cautionCount): string
    {
        $parts = [];
        
        if ($status === 'danger') {
            $parts[] = "🔴 PAUSE EA";
            $parts[] = "Risk Score: " . round($score, 1) . "/100";
            $parts[] = $dangerCount . " modul menunjukkan DANGER";
            if ($cautionCount > 0) {
                $parts[] = $cautionCount . " modul menunjukkan CAUTION";
            }
            $parts[] = "Kondisi market tidak cocok untuk EA Grid. Disarankan pause sampai kondisi membaik.";
        } elseif ($status === 'caution') {
            $parts[] = "🟡 MONITOR";
            $parts[] = "Risk Score: " . round($score, 1) . "/100";
            if ($dangerCount > 0) {
                $parts[] = $dangerCount . " modul menunjukkan DANGER";
            }
            $parts[] = $cautionCount . " modul menunjukkan CAUTION";
            $parts[] = "Perhatikan pergerakan market. Pertimbangkan reduce position size atau pause sementara.";
        } else {
            $parts[] = "🟢 EA SAFE";
            $parts[] = "Risk Score: " . round($score, 1) . "/100";
            $parts[] = "Kondisi market relatif aman untuk EA Grid.";
        }
        
        return implode(" | ", $parts);
    }
}

