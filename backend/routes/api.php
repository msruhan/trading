<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChartAnalysisController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EABridgeController;
use App\Http\Controllers\Api\EACommandController;
use App\Http\Controllers\Api\InsightsController;
use App\Http\Controllers\Api\ManualEntryController;
use App\Http\Controllers\Api\MSIController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TodayAnalysisController;
use App\Http\Controllers\Api\TradeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// API Version 1
Route::prefix('v1')->group(function () {
    
    // Public routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // EA Bridge incoming endpoint (uses custom token auth)
    Route::post('/incoming/trades', [EABridgeController::class, 'receiveTrades']);
    Route::post('/incoming/check-data', [EABridgeController::class, 'checkDataChanged']);
    Route::get('/incoming/health', [EABridgeController::class, 'healthCheck']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        
        // Auth
        Route::prefix('auth')->group(function () {
            Route::get('/user', [AuthController::class, 'user']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/password', [AuthController::class, 'updatePassword']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        });

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index']);
            Route::get('/metrics', [DashboardController::class, 'metrics']);
            Route::get('/equity-curve', [DashboardController::class, 'equityCurve']);
            Route::get('/daily-pnl', [DashboardController::class, 'dailyPnL']);
            Route::get('/monthly-pnl', [DashboardController::class, 'monthlyPnL']);
            Route::get('/calendar', [DashboardController::class, 'calendar']);
            Route::get('/stats/pair', [DashboardController::class, 'statsByPair']);
            Route::get('/stats/hour', [DashboardController::class, 'statsByHour']);
        });

        // Accounts
        Route::apiResource('accounts', AccountController::class);
        Route::post('/accounts/{account}/sync', [AccountController::class, 'sync']);
        Route::post('/accounts/{account}/trigger-sync', [AccountController::class, 'triggerSync']);
        Route::get('/accounts/{account}/ea-info', [AccountController::class, 'getEAInfo']);
        Route::post('/accounts/{account}/regenerate-token', [AccountController::class, 'regenerateToken']);
        Route::post('/accounts/{account}/clear-data', [AccountController::class, 'clearData']);
        Route::get('/accounts/{account}/sync-logs', [AccountController::class, 'syncLogs']);
        Route::get('/accounts/{account}/equity-curve', [AccountController::class, 'equityCurve']);

        // EA Commands
        Route::prefix('accounts/{account}/ea-commands')->group(function () {
            Route::get('/', [EACommandController::class, 'index']);
            Route::get('/pending', [EACommandController::class, 'pending']);
            Route::post('/', [EACommandController::class, 'store']);
        });
        Route::put('/ea-commands/{command}/status', [EACommandController::class, 'updateStatus']);

        // Trades
        Route::get('/trades/open', [TradeController::class, 'openTrades']);
        Route::get('/trades/summary', [TradeController::class, 'summary']);
        Route::post('/trades/manual', [TradeController::class, 'store']);
        Route::apiResource('trades', TradeController::class)->except(['store']);
        Route::post('/upload/statement', [TradeController::class, 'uploadStatement']);

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/monthly', [ReportController::class, 'monthly']);
            Route::get('/monthly/csv', [ReportController::class, 'exportCSV']);
            Route::get('/monthly/pdf', [ReportController::class, 'exportPDF']);
        });

        // News
        Route::get('/news/today', [NewsController::class, 'today']);
        Route::get('/news/upcoming', [NewsController::class, 'upcoming']);
        Route::get('/news/date/{date}', [NewsController::class, 'forDate']);
        Route::post('/news/sync', [NewsController::class, 'sync']);
        Route::post('/news/sync-today', [NewsController::class, 'syncToday']);
        Route::post('/news/sync-week', [NewsController::class, 'syncWeek']);
        Route::post('/news/clear', [NewsController::class, 'clearForDate']);
        Route::get('/news/ea-recommendation', [NewsController::class, 'eaRecommendation']);
        Route::get('/news/ea-stats', [NewsController::class, 'eaStats']);
        Route::put('/news/{newsItem}/mark-ea', [NewsController::class, 'markEaStatus']);
        Route::post('/news/bulk-mark-ea', [NewsController::class, 'bulkMarkEaStatus']);
        // Predictions
    Route::get('/news/predictions', [NewsController::class, 'predictions']);
    Route::get('/news/prediction-stats', [NewsController::class, 'predictionStats']);
    Route::post('/news/technical-analysis', [NewsController::class, 'technicalAnalysis']);
        Route::get('/news/notable-events', [NewsController::class, 'notableEvents']);
        Route::apiResource('news', NewsController::class);

        // Journal / Manual Entries
        Route::get('/journal/tags', [ManualEntryController::class, 'tags']);
        Route::get('/journal/date/{date}', [ManualEntryController::class, 'forDate']);
        Route::apiResource('journal', ManualEntryController::class)->parameters([
            'journal' => 'manualEntry'
        ]);

        // Chart Analyses
        Route::get('/chart-analyses', [ChartAnalysisController::class, 'index']);
        Route::get('/chart-analyses/{symbol}/{interval}', [ChartAnalysisController::class, 'getBySymbol']);
        Route::apiResource('chart-analyses', ChartAnalysisController::class)->except(['index']);

        // MSI (Market Stability Index)
        Route::prefix('msi')->group(function () {
            Route::get('/live', [MSIController::class, 'live']);
        });

        // Insights
        Route::prefix('insights')->group(function () {
            Route::get('/', [InsightsController::class, 'index']);
        });

        // Today Analysis
        Route::prefix('today-analysis')->group(function () {
            Route::get('/', [TodayAnalysisController::class, 'index']);
        });

    });
});

// Fallback for undefined routes
Route::fallback(function () {
    return response()->json([
        'message' => 'Endpoint not found',
    ], 404);
});

