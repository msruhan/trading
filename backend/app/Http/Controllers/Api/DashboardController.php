<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get dashboard overview.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->input('days', 30);

        $metrics = $this->dashboardService->getMetrics($user, $days);
        $equityCurve = $this->dashboardService->getEquityCurve($user, $days);
        $dailyPnL = $this->dashboardService->getDailyPnL($user, $days);
        $todaysTrades = $this->dashboardService->getTodaysTrades($user);
        $statsByPair = $this->dashboardService->getStatsByPair($user, $days);

        // Get today's news
        $todaysNews = NewsItem::today()
            ->orderBy('impact', 'desc')
            ->orderBy('time')
            ->limit(5)
            ->get();

        return response()->json([
            'metrics' => $metrics,
            'equity_curve' => $equityCurve,
            'daily_pnl' => $dailyPnL,
            'todays_trades' => $todaysTrades,
            'stats_by_pair' => $statsByPair,
            'news' => $todaysNews,
        ]);
    }

    /**
     * Get metrics only.
     */
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->input('days', 30);

        return response()->json(
            $this->dashboardService->getMetrics($user, $days)
        );
    }

    /**
     * Get equity curve data.
     */
    public function equityCurve(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->input('days', 30);

        return response()->json(
            $this->dashboardService->getEquityCurve($user, $days)
        );
    }

    /**
     * Get daily P/L chart data.
     */
    public function dailyPnL(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->input('days', 30);

        return response()->json(
            $this->dashboardService->getDailyPnL($user, $days)
        );
    }

    /**
     * Get calendar data.
     */
    public function calendar(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        return response()->json(
            $this->dashboardService->getCalendarData($user, $year, $month)
        );
    }

    /**
     * Get statistics by pair.
     */
    public function statsByPair(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->input('days', 30);

        return response()->json(
            $this->dashboardService->getStatsByPair($user, $days)
        );
    }

    /**
     * Get statistics by hour.
     */
    public function statsByHour(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->input('days', 30);

        return response()->json(
            $this->dashboardService->getStatsByHour($user, $days)
        );
    }

    /**
     * Get monthly P/L chart data.
     */
    public function monthlyPnL(Request $request): JsonResponse
    {
        $user = $request->user();
        $months = $request->input('months', 12);

        return response()->json(
            $this->dashboardService->getMonthlyPnL($user, $months)
        );
    }
}

