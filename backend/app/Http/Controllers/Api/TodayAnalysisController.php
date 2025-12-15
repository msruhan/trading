<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TodayAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodayAnalysisController extends Controller
{
    protected TodayAnalysisService $analysisService;

    public function __construct(TodayAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Get today's comprehensive analysis.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->input('date', null);

        try {
            $analysis = $this->analysisService->getTodayAnalysis($user, $date);

            return response()->json([
                'success' => true,
                'data' => $analysis,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate analysis: ' . $e->getMessage(),
            ], 500);
        }
    }
}

