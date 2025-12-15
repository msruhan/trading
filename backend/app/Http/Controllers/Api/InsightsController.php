<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    protected InsightsService $insightsService;

    public function __construct(InsightsService $insightsService)
    {
        $this->insightsService = $insightsService;
    }

    /**
     * Get all trading insights.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->input('days', 90);

        $insights = $this->insightsService->getInsights($user, $days);

        return response()->json([
            'success' => true,
            'data' => $insights,
            'period_days' => $days,
        ]);
    }
}

