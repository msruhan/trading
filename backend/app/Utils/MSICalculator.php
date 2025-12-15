<?php

namespace App\Utils;

class MSICalculator
{
    /**
     * Calculate Market Stability Index (MSI)
     *
     * @param array $data Contains: dxy, vix, gvz, us10y, correlation, spx, spxHistorical
     * @return array
     */
    public static function calculate(array $data): array
    {
        $dxy = $data['dxy'] ?? null;
        $vix = $data['vix'] ?? null;
        $gvz = $data['gvz'] ?? null;
        $us10y = $data['us10y'] ?? null;
        $correlation = $data['correlation'] ?? null;
        $spx = $data['spx'] ?? null;
        $spxHistorical = $data['spxHistorical'] ?? null;

        // Calculate individual scores
        $dxyScore = self::calculateDXYScore($dxy);
        $vixScore = self::calculateVIXScore($vix);
        $gvzScore = self::calculateGVZScore($gvz);
        $yieldScore = self::calculateYieldScore($us10y);
        $correlationScore = self::calculateCorrelationScore($correlation);
        $spxScore = self::calculateSPXScore($spx, $spxHistorical);

        // Calculate weighted MSI with new weights
        // MSI = (dxyScore * 0.20) + (vixScore * 0.20) + (gvzScore * 0.20) + 
        //       (yieldScore * 0.15) + (correlationScore * 0.10) + (spxScore * 0.15)
        $weights = [
            'dxy' => 0.20,
            'vix' => 0.20,
            'gvz' => 0.20,
            'yield' => 0.15,
            'correlation' => 0.10,
            'spx' => 0.15,
        ];

        $weightedSum = 0;
        $totalWeight = 0;

        if ($dxyScore !== null) {
            $weightedSum += $dxyScore * $weights['dxy'];
            $totalWeight += $weights['dxy'];
        }
        if ($vixScore !== null) {
            $weightedSum += $vixScore * $weights['vix'];
            $totalWeight += $weights['vix'];
        }
        if ($gvzScore !== null) {
            $weightedSum += $gvzScore * $weights['gvz'];
            $totalWeight += $weights['gvz'];
        }
        if ($yieldScore !== null) {
            $weightedSum += $yieldScore * $weights['yield'];
            $totalWeight += $weights['yield'];
        }
        if ($correlationScore !== null) {
            $weightedSum += $correlationScore * $weights['correlation'];
            $totalWeight += $weights['correlation'];
        }
        if ($spxScore !== null) {
            $weightedSum += $spxScore * $weights['spx'];
            $totalWeight += $weights['spx'];
        }

        if ($totalWeight == 0) {
            return [
                'msi' => 0,
                'status' => 'DANGER',
                'dxyScore' => null,
                'vixScore' => null,
                'gvzScore' => null,
                'yieldScore' => null,
                'correlationScore' => null,
                'spxScore' => null,
                'message' => 'Insufficient data to calculate MSI',
            ];
        }

        $msi = round($weightedSum / $totalWeight);

        // Determine status
        $status = self::determineStatus($msi);

        return [
            'msi' => $msi,
            'status' => $status,
            'dxyScore' => $dxyScore,
            'vixScore' => $vixScore,
            'gvzScore' => $gvzScore,
            'yieldScore' => $yieldScore,
            'correlationScore' => $correlationScore,
            'spxScore' => $spxScore,
            'message' => self::generateMessage($msi, $status, [
                'dxy' => $dxy,
                'vix' => $vix,
                'gvz' => $gvz,
                'us10y' => $us10y,
                'correlation' => $correlation,
                'spx' => $spx,
            ]),
        ];
    }

    /**
     * Calculate DXY (Dollar Index) stability score
     * 
     * Rule: If DXY is trending strongly → 20, Sideways → 80
     * 
     * Note: This is simplified. In production, you'd analyze DXY movement
     * over time to determine if it's trending or sideways.
     *
     * @param float|null $dxy
     * @return int|null
     */
    private static function calculateDXYScore(?float $dxy): ?int
    {
        if ($dxy === null) {
            return null;
        }

        // Simplified: Assume DXY is stable if it's in a reasonable range
        // In production, you'd analyze historical DXY data to determine trend strength
        // For now, we'll use a simple heuristic based on typical DXY range (90-110)
        
        // If DXY is in normal range (90-110), consider it stable
        if ($dxy >= 90 && $dxy <= 110) {
            // Check if it's near extremes (trending) or middle (sideways)
            $distanceFromMiddle = abs($dxy - 100);
            
            // If close to middle (95-105), more stable
            if ($distanceFromMiddle <= 5) {
                return 80; // Sideways/stable
            } else {
                return 50; // Moderate movement
            }
        }
        
        // Extreme values indicate strong trend
        return 20;
    }

    /**
     * Calculate VIX (Volatility Index) stability score
     * 
     * Rule:
     * - < 15 → 90 (Low volatility, stable)
     * - 15-20 → 70 (Moderate volatility)
     * - 20-25 → 40 (High volatility)
     * - > 25 → 10 (Extreme volatility)
     *
     * @param float|null $vix
     * @return int|null
     */
    private static function calculateVIXScore(?float $vix): ?int
    {
        if ($vix === null) {
            return null;
        }

        if ($vix < 15) {
            return 90;
        } elseif ($vix >= 15 && $vix < 20) {
            return 70;
        } elseif ($vix >= 20 && $vix < 25) {
            return 40;
        } else {
            return 10;
        }
    }

    /**
     * Calculate US 10-Year Treasury Yield stability score
     * 
     * Rule: Stable → 70, Moving extremely → 20
     * 
     * Note: This is simplified. In production, you'd analyze yield movement
     * over time to determine stability.
     *
     * @param float|null $yield
     * @return int|null
     */
    private static function calculateYieldScore(?float $yield): ?int
    {
        if ($yield === null) {
            return null;
        }

        // Typical 10-year yield range: 2-5%
        // If yield is in normal range and not at extremes, consider stable
        if ($yield >= 2.0 && $yield <= 5.0) {
            $distanceFromMiddle = abs($yield - 3.5);
            
            // If close to middle (2.5-4.5), more stable
            if ($distanceFromMiddle <= 1.0) {
                return 70; // Stable
            } else {
                return 50; // Moderate movement
            }
        }
        
        // Extreme values indicate instability
        return 20;
    }

    /**
     * Calculate correlation stability score
     * 
     * Rule:
     * - |corr| < 0.3 → 90 (Low correlation, markets not moving together)
     * - 0.3-0.6 → 60 (Moderate correlation)
     * - > 0.6 → 30 (High correlation, markets moving together - risky for grid)
     *
     * @param float|null $correlation
     * @return int|null
     */
    private static function calculateCorrelationScore(?float $correlation): ?int
    {
        if ($correlation === null) {
            return null;
        }

        $absCorr = abs($correlation);

        if ($absCorr < 0.3) {
            return 90; // Low correlation - good for grid
        } elseif ($absCorr >= 0.3 && $absCorr < 0.6) {
            return 60; // Moderate correlation
        } else {
            return 30; // High correlation - risky
        }
    }

    /**
     * Calculate GVZ (Gold Volatility Index) stability score
     * 
     * Rule:
     * - GVZ < 12 → score = 90 (emas sangat stabil, sangat aman untuk EA Grid)
     * - 12-15 → score = 70
     * - 15-18 → score = 45
     * - >= 18 → score = 20 (emas rawan trending kuat)
     *
     * @param float|null $gvz
     * @return int|null
     */
    private static function calculateGVZScore(?float $gvz): ?int
    {
        if ($gvz === null) {
            return null;
        }

        if ($gvz < 12) {
            return 90; // Very stable gold, very safe for EA Grid
        } elseif ($gvz >= 12 && $gvz < 15) {
            return 70; // Stable gold
        } elseif ($gvz >= 15 && $gvz < 18) {
            return 45; // Moderate volatility
        } else {
            return 20; // High volatility, gold prone to strong trending
        }
    }

    /**
     * Calculate S&P500 Futures (ES) stability score
     * 
     * Rule:
     * - ES bergerak sideways → score = 80
     * - ES uptrend stabil → score = 60
     * - ES downtrend stabil → score = 40
     * - ES volatile (pergerakan besar) → score = 20
     *
     * @param float|null $spx Current price
     * @param array|null $spxHistorical Historical prices for trend analysis
     * @return int|null
     */
    private static function calculateSPXScore(?float $spx, ?array $spxHistorical): ?int
    {
        if ($spx === null) {
            return null;
        }

        // If we have historical data, analyze trend
        if ($spxHistorical !== null && count($spxHistorical) >= 10) {
            // Calculate percentage change over the period
            $firstPrice = $spxHistorical[0];
            $lastPrice = end($spxHistorical);
            
            if ($firstPrice > 0) {
                $percentChange = (($lastPrice - $firstPrice) / $firstPrice) * 100;
                
                // Calculate volatility (standard deviation of returns)
                $returns = [];
                for ($i = 1; $i < count($spxHistorical); $i++) {
                    if ($spxHistorical[$i-1] > 0) {
                        $returns[] = (($spxHistorical[$i] - $spxHistorical[$i-1]) / $spxHistorical[$i-1]) * 100;
                    }
                }
                
                if (count($returns) > 0) {
                    $mean = array_sum($returns) / count($returns);
                    $variance = 0;
                    foreach ($returns as $ret) {
                        $variance += pow($ret - $mean, 2);
                    }
                    $stdDev = sqrt($variance / count($returns));
                    
                    // High volatility (stdDev > 1.5%) → score = 20
                    if ($stdDev > 1.5) {
                        return 20; // Volatile
                    }
                    
                    // Sideways: small change (< 0.5%) → score = 80
                    if (abs($percentChange) < 0.5) {
                        return 80; // Sideways
                    }
                    
                    // Uptrend: positive change (0.5% - 2%) → score = 60
                    if ($percentChange > 0 && $percentChange <= 2.0) {
                        return 60; // Stable uptrend
                    }
                    
                    // Downtrend: negative change (-0.5% to -2%) → score = 40
                    if ($percentChange < 0 && $percentChange >= -2.0) {
                        return 40; // Stable downtrend
                    }
                    
                    // Extreme movement (> 2% or < -2%) → score = 20
                    return 20; // Volatile/extreme movement
                }
            }
        }
        
        // Fallback: if no historical data, assume moderate stability
        return 50;
    }

    /**
     * Determine MSI status based on score
     *
     * @param int $msi
     * @return string
     */
    private static function determineStatus(int $msi): string
    {
        if ($msi >= 60) {
            return 'SAFE';
        } elseif ($msi >= 40) {
            return 'CAUTION';
        } else {
            return 'DANGER';
        }
    }

    /**
     * Generate human-readable message
     *
     * @param int $msi
     * @param string $status
     * @param array $data
     * @return string
     */
    private static function generateMessage(int $msi, string $status, array $data): string
    {
        $parts = [];

        // DXY assessment
        if ($data['dxy'] !== null) {
            $dxyScore = self::calculateDXYScore($data['dxy']);
            if ($dxyScore >= 70) {
                $parts[] = 'DXY sideways';
            } else {
                $parts[] = 'DXY trending';
            }
        }

        // VIX assessment
        if ($data['vix'] !== null) {
            $vix = $data['vix'];
            if ($vix < 15) {
                $parts[] = 'VIX rendah';
            } elseif ($vix < 20) {
                $parts[] = 'VIX sedang';
            } else {
                $parts[] = 'VIX tinggi';
            }
        }

        // GVZ assessment
        if (isset($data['gvz']) && $data['gvz'] !== null) {
            $gvz = $data['gvz'];
            if ($gvz < 12) {
                $parts[] = 'GVZ rendah (emas stabil)';
            } elseif ($gvz < 15) {
                $parts[] = 'GVZ sedang';
            } elseif ($gvz < 18) {
                $parts[] = 'GVZ tinggi';
            } else {
                $parts[] = 'GVZ sangat tinggi (emas volatile)';
            }
        }

        // Yield assessment
        if ($data['us10y'] !== null) {
            $yieldScore = self::calculateYieldScore($data['us10y']);
            if ($yieldScore >= 60) {
                $parts[] = 'Yield stabil';
            } else {
                $parts[] = 'Yield volatile';
            }
        }

        // Correlation assessment
        if ($data['correlation'] !== null) {
            $corr = abs($data['correlation']);
            if ($corr < 0.3) {
                $parts[] = 'Korelasi rendah';
            } elseif ($corr < 0.6) {
                $parts[] = 'Korelasi sedang';
            } else {
                $parts[] = 'Korelasi tinggi';
            }
        }

        // S&P500 assessment
        if (isset($data['spx']) && $data['spx'] !== null) {
            $parts[] = 'S&P500 Futures';
        }

        $assessment = implode(', ', $parts);

        if ($status === 'SAFE') {
            return "Market global stabil → cocok untuk EA Grid. {$assessment}.";
        } elseif ($status === 'CAUTION') {
            return "Market perlu perhatian. {$assessment}. Gunakan mode konservatif.";
        } else {
            return "Market berpotensi trending besar → hindari EA Grid. {$assessment}.";
        }
    }
}

