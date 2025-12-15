<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\YahooFinanceService;
use App\Services\GroqService;
use App\Utils\MSICalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MSIController extends Controller
{
    private YahooFinanceService $yahooService;
    private GroqService $groqService;

    public function __construct(YahooFinanceService $yahooService, GroqService $groqService)
    {
        $this->yahooService = $yahooService;
        $this->groqService = $groqService;
    }

    /**
     * Get live Market Stability Index (MSI)
     *
     * @return JsonResponse
     */
    public function live(): JsonResponse
    {
        try {
            // Fetch market data from Yahoo Finance
            $marketData = $this->yahooService->getMarketData();

            // Calculate MSI
            $msiResult = MSICalculator::calculate([
                'dxy' => $marketData['dxy'],
                'vix' => $marketData['vix'],
                'gvz' => $marketData['gvz'],
                'us10y' => $marketData['us10y'],
                'correlation' => $marketData['correlation'],
                'spx' => $marketData['spx'],
                'spxHistorical' => $marketData['spxHistorical'],
            ]);

            // Prepare response
            $response = [
                'msi' => $msiResult['msi'],
                'status' => $msiResult['status'],
                'details' => [
                    'dxy' => $marketData['dxy'],
                    'vix' => $marketData['vix'],
                    'gvz' => $marketData['gvz'],
                    'us10y' => $marketData['us10y'],
                    'spx' => $marketData['spx'],
                    'correlation' => $marketData['correlation'],
                    'dxyScore' => $msiResult['dxyScore'],
                    'vixScore' => $msiResult['vixScore'],
                    'gvzScore' => $msiResult['gvzScore'],
                    'yieldScore' => $msiResult['yieldScore'],
                    'correlationScore' => $msiResult['correlationScore'],
                    'spxScore' => $msiResult['spxScore'],
                    // Detailed cross-pair correlations
                    'correlations' => [
                        'xauusd_eurusd' => $marketData['correlations']['xauusd_eurusd'] ?? null,
                        'xauusd_gbpusd' => $marketData['correlations']['xauusd_gbpusd'] ?? null,
                        'xauusd_usdjpy' => $marketData['correlations']['xauusd_usdjpy'] ?? null,
                        'eurusd_gbpusd' => $marketData['correlations']['eurusd_gbpusd'] ?? null,
                        'average' => $marketData['correlations']['average'] ?? null,
                    ],
                ],
                'message' => $msiResult['message'],
                'updatedAt' => now()->toIso8601String(),
            ];

            // Enhance with Groq AI if available
            if ($this->groqService->isAvailable()) {
                try {
                    $groqAnalysis = $this->groqService->analyzeMarketStability($msiResult);
                    
                    if (($groqAnalysis['confidence'] ?? 0) >= config('services.groq.min_confidence', 60)) {
                        $response['ai_enhanced'] = true;
                        $response['ai_confidence'] = round($groqAnalysis['confidence'] ?? 0, 1);
                        $response['ai_reasoning'] = $groqAnalysis['reasoning'] ?? null;
                        $response['ai_recommendation'] = $groqAnalysis['recommendation'] ?? null;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to enhance MSI with Groq AI', ['error' => $e->getMessage()]);
                }
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('MSI calculation error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Failed to calculate MSI',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

