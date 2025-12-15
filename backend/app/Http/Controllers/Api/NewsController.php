<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use App\Services\ForexFactoryScraper;
use App\Services\TechnicalAnalysisService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    protected ForexFactoryScraper $scraper;
    protected TechnicalAnalysisService $technicalService;

    public function __construct(ForexFactoryScraper $scraper, TechnicalAnalysisService $technicalService)
    {
        $this->scraper = $scraper;
        $this->technicalService = $technicalService;
    }

    /**
     * List news items with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Filter by user (created_by)
        $query = NewsItem::where('created_by', $user->id);

        // Filter by date
        if ($request->has('date')) {
            $query->forDate($request->date);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }

        // Filter by currency
        if ($request->has('currency')) {
            $query->forCurrency($request->currency);
        }

        // Filter by impact
        if ($request->has('impact')) {
            $query->where('impact', $request->impact);
        }

        // Filter by EA status
        if ($request->has('ea_status')) {
            $query->where('ea_status', $request->ea_status);
        }

        // Filter by should_disable_ea
        if ($request->has('should_disable_ea')) {
            $query->where('should_disable_ea', $request->boolean('should_disable_ea'));
        }

        // Filter by affected pair
        if ($request->has('pair')) {
            $query->affectsPair($request->pair);
        }

        $news = $query->orderBy('date', 'desc')
            ->orderByRaw("FIELD(impact, 'high', 'medium', 'low')")
            ->orderBy('time')
            ->paginate($request->input('per_page', 50));

        return response()->json($news);
    }

    /**
     * Get news for a specific date.
     */
    public function forDate(Request $request, string $date): JsonResponse
    {
        $user = $request->user();
        
        // Filter by user (created_by)
        $news = NewsItem::forDate($date)
            ->where('created_by', $user->id)
            ->orderByRaw("FIELD(impact, 'high', 'medium', 'low')")
            ->orderBy('time')
            ->get();

        // Get EA recommendation for this day
        $eaRecommendation = $this->getEaRecommendation($news);

        return response()->json([
            'date' => $date,
            'news' => $news,
            'ea_recommendation' => $eaRecommendation,
            'stats' => [
                'total' => $news->count(),
                'high_impact' => $news->where('impact', 'high')->count(),
                'medium_impact' => $news->where('impact', 'medium')->count(),
                'low_impact' => $news->where('impact', 'low')->count(),
                'danger' => $news->where('ea_status', 'danger')->count(),
                'caution' => $news->where('ea_status', 'caution')->count(),
                'safe' => $news->where('ea_status', 'safe')->count(),
            ],
        ]);
    }

    /**
     * Get today's news.
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Filter by user (created_by)
        $news = NewsItem::today()
            ->where('created_by', $user->id)
            ->orderByRaw("FIELD(impact, 'high', 'medium', 'low')")
            ->orderBy('time')
            ->get();

        $eaRecommendation = $this->getEaRecommendation($news);

        return response()->json([
            'date' => today()->format('Y-m-d'),
            'news' => $news,
            'ea_recommendation' => $eaRecommendation,
            'current_time' => now()->format('H:i'),
            'stats' => [
                'total' => $news->count(),
                'high_impact' => $news->where('impact', 'high')->count(),
                'should_disable' => $news->where('should_disable_ea', true)->count(),
            ],
        ]);
    }

    /**
     * Get upcoming news.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->input('days', 7);

        // Filter by user (created_by)
        $news = NewsItem::where('created_by', $user->id)
            ->where('date', '>=', today())
            ->where('date', '<=', today()->addDays($days))
            ->orderBy('date')
            ->orderByRaw("FIELD(impact, 'high', 'medium', 'low')")
            ->orderBy('time')
            ->get()
            ->groupBy(fn($item) => $item->date->format('Y-m-d'));

        return response()->json([
            'news' => $news,
        ]);
    }

    /**
     * Sync news from ForexFactory for a specific date.
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $user = $request->user();
        $date = Carbon::parse($validated['date']);
        
        // Sync for current user (created_by = user_id)
        $result = $this->scraper->syncForDate($date, $user->id);

        if ($result['success']) {
            return response()->json([
                'message' => 'News synced successfully',
                'synced' => $result['synced'],
                'updated' => $result['updated'],
                'date' => $date->format('Y-m-d'),
            ]);
        }

        return response()->json([
            'message' => 'Sync completed with errors',
            'errors' => $result['errors'],
            'synced' => $result['synced'],
            'updated' => $result['updated'],
        ], 422);
    }

    /**
     * Sync today's news.
     */
    public function syncToday(Request $request): JsonResponse
    {
        $result = $this->scraper->syncForDate(today());

        if ($result['success']) {
            return response()->json([
                'message' => 'Today\'s news synced successfully',
                'synced' => $result['synced'],
                'updated' => $result['updated'],
                'date' => today()->format('Y-m-d'),
            ]);
        }

        return response()->json([
            'message' => 'Sync completed with errors',
            'errors' => $result['errors'],
            'synced' => $result['synced'],
            'updated' => $result['updated'],
        ], 422);
    }

    /**
     * Sync entire week's news from ForexFactory.
     */
    public function syncWeek(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Sync for current user (created_by = user_id)
        $result = $this->scraper->syncWeek($user->id);

        if ($result['success']) {
            return response()->json([
                'message' => 'Weekly news synced successfully',
                'synced' => $result['synced'],
                'updated' => $result['updated'],
                'dates_processed' => $result['dates_processed'] ?? [],
            ]);
        }

        return response()->json([
            'message' => 'Sync completed with errors',
            'errors' => $result['errors'],
            'synced' => $result['synced'],
            'updated' => $result['updated'],
            'dates_processed' => $result['dates_processed'] ?? [],
        ], 422);
    }

    /**
     * Clear news for a specific date (used for resync).
     *
     * By default this will only delete auto-synced items (is_manual = false)
     * so that manual notes / custom entries tetap aman.
     */
    public function clearForDate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'include_manual' => 'boolean',
        ]);

        $user = $request->user();
        $date = Carbon::parse($validated['date'])->format('Y-m-d');

        // Filter by user (created_by)
        $query = NewsItem::forDate($date)
            ->where('created_by', $user->id);

        // Unless explicitly requested, keep manual items
        if (empty($validated['include_manual']) || $validated['include_manual'] === false) {
            $query->where('is_manual', false);
        }

        $deleted = $query->delete();

        return response()->json([
            'message' => 'News cleared successfully',
            'deleted' => $deleted,
            'date' => $date,
        ]);
    }

    /**
     * Mark/update EA status for a news item.
     */
    public function markEaStatus(Request $request, NewsItem $newsItem): JsonResponse
    {
        // Verify news item belongs to user
        $user = $request->user();
        if ($newsItem->created_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'ea_status' => 'nullable|in:safe,caution,danger', // Allow null for "empty"
            'should_disable_ea' => 'boolean',
            'disable_minutes_before' => 'integer|min:0|max:120',
            'disable_minutes_after' => 'integer|min:0|max:120',
            'user_notes' => 'nullable|string|max:1000',
        ]);

        $updateData = [
            'ea_status' => $validated['ea_status'] ?? null,
            'marked_by' => $request->user()->id,
            'marked_at' => now(),
        ];

        // Only set should_disable_ea if ea_status is 'danger', otherwise set to false
        if ($validated['ea_status'] === 'danger') {
            $updateData['should_disable_ea'] = $validated['should_disable_ea'] ?? true;
        } else {
            $updateData['should_disable_ea'] = false;
        }

        // Add optional fields if provided
        if (isset($validated['disable_minutes_before'])) {
            $updateData['disable_minutes_before'] = $validated['disable_minutes_before'];
        }
        if (isset($validated['disable_minutes_after'])) {
            $updateData['disable_minutes_after'] = $validated['disable_minutes_after'];
        }
        if (isset($validated['user_notes'])) {
            $updateData['user_notes'] = $validated['user_notes'];
        }

        $newsItem->update($updateData);

        return response()->json([
            'message' => 'EA status updated successfully',
            'news' => $newsItem->fresh(),
        ]);
    }

    /**
     * Bulk mark EA status for multiple news items.
     */
    public function bulkMarkEaStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'news_ids' => 'required|array',
            'news_ids.*' => 'exists:news_items,id',
            'ea_status' => 'nullable|in:safe,caution,danger', // Allow null for "empty"
            'should_disable_ea' => 'boolean',
        ]);

        // Verify all news items belong to user
        $newsItems = NewsItem::whereIn('id', $validated['news_ids'])->get();
        foreach ($newsItems as $item) {
            if ($item->created_by !== $user->id) {
                return response()->json(['message' => 'Unauthorized: Some news items do not belong to you'], 403);
            }
        }

        $updateData = [
            'ea_status' => $validated['ea_status'] ?? null, // Explicitly set to null if not provided
            'marked_by' => $user->id,
            'marked_at' => now(),
        ];

        // Only set should_disable_ea if ea_status is 'danger', otherwise set to false
        if ($validated['ea_status'] === 'danger') {
            $updateData['should_disable_ea'] = $validated['should_disable_ea'] ?? true;
        } else {
            $updateData['should_disable_ea'] = false;
        }

        $updated = NewsItem::whereIn('id', $validated['news_ids'])
            ->where('created_by', $user->id) // Additional security check
            ->update($updateData);

        return response()->json([
            'message' => "Updated {$updated} news items",
            'updated' => $updated,
        ]);
    }

    /**
     * Get EA recommendation for current time.
     */
    public function eaRecommendation(Request $request): JsonResponse
    {
        $pairs = $request->input('pairs', []);
        $now = now();
        $user = $request->user();

        // Get today's news that could affect trading (filter by user)
        $dangerNews = NewsItem::today()
            ->where('created_by', $user->id)
            ->shouldDisableEa()
            ->get();

        $shouldDisable = false;
        $activeEvents = [];
        $upcomingEvents = [];

        foreach ($dangerNews as $news) {
            if ($news->shouldDisableEaAt($now)) {
                $shouldDisable = true;
                $activeEvents[] = [
                    'id' => $news->id,
                    'title' => $news->title,
                    'currency' => $news->currency,
                    'time' => $news->formatted_time,
                    'impact' => $news->impact,
                    'disable_window' => $news->disable_window,
                ];
            } elseif ($news->time && Carbon::parse($news->date->format('Y-m-d') . ' ' . $news->time->format('H:i:s'))->isFuture()) {
                $upcomingEvents[] = [
                    'id' => $news->id,
                    'title' => $news->title,
                    'currency' => $news->currency,
                    'time' => $news->formatted_time,
                    'impact' => $news->impact,
                    'disable_window' => $news->disable_window,
                    'minutes_until' => now()->diffInMinutes(Carbon::parse($news->date->format('Y-m-d') . ' ' . $news->time->format('H:i:s')), false),
                ];
            }
        }

        // Sort upcoming by time
        usort($upcomingEvents, fn($a, $b) => $a['minutes_until'] <=> $b['minutes_until']);

        return response()->json([
            'current_time' => $now->format('H:i:s'),
            'should_disable_ea' => $shouldDisable,
            'status' => $shouldDisable ? 'DANGER' : 'SAFE',
            'message' => $shouldDisable 
                ? 'EA should be DISABLED - Active high-impact news event' 
                : 'EA can run safely',
            'active_events' => $activeEvents,
            'upcoming_events' => array_slice($upcomingEvents, 0, 5),
            'next_danger' => !empty($upcomingEvents) ? $upcomingEvents[0] : null,
        ]);
    }

    /**
     * Get EA tracking statistics.
     */
    public function eaStats(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $stats = [
            'total_events' => NewsItem::whereBetween('date', [$startDate, $endDate])->count(),
            'marked_safe' => NewsItem::whereBetween('date', [$startDate, $endDate])->where('ea_status', 'safe')->count(),
            'marked_caution' => NewsItem::whereBetween('date', [$startDate, $endDate])->where('ea_status', 'caution')->count(),
            'marked_danger' => NewsItem::whereBetween('date', [$startDate, $endDate])->where('ea_status', 'danger')->count(),
            'high_impact' => NewsItem::whereBetween('date', [$startDate, $endDate])->where('impact', 'high')->count(),
            'should_disable' => NewsItem::whereBetween('date', [$startDate, $endDate])->where('should_disable_ea', true)->count(),
        ];

        // Get by currency
        $byCurrency = NewsItem::whereBetween('date', [$startDate, $endDate])
            ->selectRaw('currency, COUNT(*) as total, 
                SUM(CASE WHEN ea_status = "danger" THEN 1 ELSE 0 END) as danger_count,
                SUM(CASE WHEN should_disable_ea = 1 THEN 1 ELSE 0 END) as disable_count')
            ->groupBy('currency')
            ->get();

        // Get commonly marked as danger events
        $commonDangerEvents = NewsItem::where('ea_status', 'danger')
            ->selectRaw('title, currency, COUNT(*) as occurrence')
            ->groupBy('title', 'currency')
            ->orderByDesc('occurrence')
            ->limit(10)
            ->get();

        return response()->json([
            'period' => ['start' => $startDate, 'end' => $endDate],
            'stats' => $stats,
            'by_currency' => $byCurrency,
            'common_danger_events' => $commonDangerEvents,
        ]);
    }

    /**
     * Create a manual news item.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'title' => 'required|string|max:500',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'source' => 'nullable|string|max:255',
            'url' => 'nullable|url|max:500',
            'impact' => 'nullable|in:low,medium,high',
            'currency' => 'nullable|string|max:10',
            'actual' => 'nullable|string|max:50',
            'forecast' => 'nullable|string|max:50',
            'previous' => 'nullable|string|max:50',
            'ea_status' => 'nullable|in:safe,caution,danger',
            'should_disable_ea' => 'boolean',
            'user_notes' => 'nullable|string',
        ]);

        $news = NewsItem::create([
            ...$validated,
            'is_manual' => true,
            'created_by' => $user->id, // Link to user (admin/demo)
        ]);

        return response()->json([
            'news' => $news,
            'message' => 'News item created successfully',
        ], 201);
    }

    /**
     * Show a single news item.
     */
    public function show(Request $request, NewsItem $news): JsonResponse
    {
        // Verify news item belongs to user
        $user = $request->user();
        if ($news->created_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
            return response()->json([
            'news' => $news,
        ]);
    }

    /**
     * Update a news item.
     */
    public function update(Request $request, NewsItem $news): JsonResponse
    {
        // Verify news item belongs to user
        $user = $request->user();
        if ($news->created_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Convert empty strings to null for nullable fields
        $data = $request->all();
        $nullableFields = ['time', 'summary', 'content', 'source', 'url', 'actual', 'forecast', 'previous', 'user_notes'];
        foreach ($nullableFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
        }
        }
        $request->merge($data);

        $validated = $request->validate([
            'date' => 'sometimes|date',
            'time' => 'nullable|date_format:H:i',
            'title' => 'sometimes|string|max:500',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'source' => 'nullable|string|max:255',
            'url' => 'nullable|url|max:500',
            'impact' => 'nullable|in:low,medium,high',
            'currency' => 'nullable|string|max:10',
            'actual' => 'nullable|string|max:50',
            'forecast' => 'nullable|string|max:50',
            'previous' => 'nullable|string|max:50',
            'ea_status' => 'nullable|in:safe,caution,danger',
            'should_disable_ea' => 'boolean',
            'disable_minutes_before' => 'integer|min:0|max:120',
            'disable_minutes_after' => 'integer|min:0|max:120',
            'user_notes' => 'nullable|string',
        ]);

        // Handle ea_status null explicitly
        $updateData = $validated;
        if (isset($validated['ea_status']) && $validated['ea_status'] === null) {
            $updateData['ea_status'] = null;
            // If setting to empty, also disable should_disable_ea
            $updateData['should_disable_ea'] = false;
        } elseif (isset($validated['ea_status']) && $validated['ea_status'] === 'danger') {
            // If setting to danger, enable should_disable_ea by default
            $updateData['should_disable_ea'] = $validated['should_disable_ea'] ?? true;
        } elseif (isset($validated['ea_status'])) {
            // For safe/caution, disable should_disable_ea
            $updateData['should_disable_ea'] = false;
        }
        
        $news->update($updateData);

        return response()->json([
            'news' => $news->fresh(),
            'message' => 'News item updated successfully',
        ]);
    }

    /**
     * Delete a news item.
     */
    public function destroy(Request $request, NewsItem $news): JsonResponse
    {
        // Verify news item belongs to user
        $user = $request->user();
        if ($news->created_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $news->delete();

        return response()->json([
            'message' => 'News item deleted successfully',
        ]);
    }

    /**
     * Get predictions for upcoming news - TWO TYPES:
     * 1. Based on ForexFactory actual impact (high/medium/low)
     * 2. Based on historical marking data (safe/caution/danger)
     */
    public function predictions(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->input('date', today()->format('Y-m-d'));
        
        // Filter by user (created_by)
        $upcomingNews = NewsItem::forDate($date)
            ->where('created_by', $user->id)
            ->orderByRaw("FIELD(impact, 'high', 'medium', 'low')")
            ->orderBy('time')
            ->get();
        
        // ============================================
        // PREDICTION 1: Based on ForexFactory Impact
        // ============================================
        $impactBasedPredictions = [];
        $impactBasedDangerWindows = [];
        
        foreach ($upcomingNews as $news) {
            // Predict based on actual impact from ForexFactory
            $impactPrediction = $this->predictByForexFactoryImpact($news);
            
            $impactBasedPredictions[] = [
                'id' => $news->id,
                'title' => $news->title,
                'currency' => $news->currency,
                'impact' => $news->impact, // Actual impact from ForexFactory
                'time' => $news->formatted_time,
                'time_raw' => $news->time ? $news->time->format('H:i') : null,
                'predicted_status' => $impactPrediction['status'],
                'confidence' => $impactPrediction['confidence'],
                'recommendation' => $impactPrediction['recommendation'],
                'reasons' => $impactPrediction['reasons'] ?? [], // Array of reasons
                'factors' => $impactPrediction['factors'] ?? [], // Summary factors
                'source' => 'forexfactory_impact',
            ];
            
            // Collect danger windows based on impact
            if ($news->time && $impactPrediction['status'] === 'danger') {
                $impactBasedDangerWindows[] = [
                    'time' => $news->time->format('H:i'),
                    'before' => 30, // Default 30 minutes
                    'after' => 30,
                    'title' => $news->title,
                    'reason' => 'High Impact from ForexFactory',
                ];
            }
        }
        
        $impactSafeWindows = $this->calculateSafeWindows($impactBasedDangerWindows);
        $impactDayPrediction = $this->getDayPredictionWithSchedule(
            $impactBasedPredictions, 
            $impactSafeWindows, 
            $impactBasedDangerWindows,
            'forexfactory_impact'
        );
        
        // ============================================
        // PREDICTION 2: Based on Historical Marking Data
        // ============================================
        $historicalPredictions = [];
        $historicalDangerWindows = [];
        
        foreach ($upcomingNews as $news) {
            // Predict based on historical marking data
            $historicalPrediction = $this->predictEaStatus($news);
            
            // Hanya tambahkan ke predictions jika:
            // 1. Ada data historis (has_historical_data = true), ATAU
            // 2. User sudah mark news ini (ea_status tidak null)
            $hasHistoricalData = $historicalPrediction['has_historical_data'] ?? false;
            $isMarkedByUser = $news->ea_status !== null;
            
            if ($hasHistoricalData || $isMarkedByUser) {
                $historicalPredictions[] = [
                    'id' => $news->id,
                    'title' => $news->title,
                    'currency' => $news->currency,
                    'impact' => $news->impact,
                    'time' => $news->formatted_time,
                    'time_raw' => $news->time ? $news->time->format('H:i') : null,
                    'current_status' => $news->ea_status, // User's current marking (bisa null jika belum di-mark)
                    'predicted_status' => $historicalPrediction['status'], // Bisa null jika belum ada data historis
                    'confidence' => $historicalPrediction['confidence'],
                    'has_historical_data' => $hasHistoricalData,
                    'historical_data' => $historicalPrediction['historical'],
                    'recommendation' => $historicalPrediction['recommendation'],
                    'should_disable_ea' => $news->should_disable_ea,
                    'disable_minutes_before' => $news->disable_minutes_before ?? 30,
                    'disable_minutes_after' => $news->disable_minutes_after ?? 30,
                    'source' => 'historical_marking',
                ];
                
                // Collect danger windows based on historical data or user marking
                if ($news->time && (
                    ($historicalPrediction['status'] === 'danger' && $hasHistoricalData) || 
                    $news->should_disable_ea || 
                    $news->ea_status === 'danger'
                )) {
                    $historicalDangerWindows[] = [
                        'time' => $news->time->format('H:i'),
                        'before' => $news->disable_minutes_before ?? 30,
                        'after' => $news->disable_minutes_after ?? 30,
                        'title' => $news->title,
                        'reason' => $news->ea_status === 'danger' 
                            ? 'Marked as Danger by User' 
                            : 'Historical Data Prediction',
                    ];
                }
            }
        }
        
        $historicalSafeWindows = $this->calculateSafeWindows($historicalDangerWindows);
        $historicalDayPrediction = $this->getDayPredictionWithSchedule(
            $historicalPredictions, 
            $historicalSafeWindows, 
            $historicalDangerWindows,
            'historical_marking'
        );
        
        return response()->json([
            'date' => $date,
            'prediction_1_forexfactory' => [
                'type' => 'ForexFactory Impact Based',
                'description' => 'Prediksi berdasarkan actual impact (high/medium/low) dari ForexFactory',
                'predictions' => $impactBasedPredictions,
                'day_prediction' => $impactDayPrediction,
                'safe_windows' => $impactSafeWindows,
                'danger_windows' => $impactBasedDangerWindows,
            ],
            'prediction_2_historical' => [
                'type' => 'Historical Marking Based',
                'description' => 'Prediksi berdasarkan data historis news yang sudah Anda tandai (safe/caution/danger)',
                'predictions' => $historicalPredictions,
                'day_prediction' => $historicalDayPrediction,
                'safe_windows' => $historicalSafeWindows,
                'danger_windows' => $historicalDangerWindows,
            ],
        ]);
    }
    
    /**
     * Get technical analysis prediction (Prediction 3).
     * Requires OHLC data from TradingView.
     */
    public function technicalAnalysis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ohlc' => 'required|array',
            'ohlc.1m' => 'nullable|array',
            'ohlc.5m' => 'nullable|array',
            'ohlc.15m' => 'required|array',
            'ohlc.H1' => 'required|array',
            'ohlc.H4' => 'required|array',
            'symbol' => 'nullable|string',
        ]);
        
        $symbol = $validated['symbol'] ?? 'XAUUSD';
        $ohlcData = $validated['ohlc'];
        
        // Validate OHLC structure
        foreach ($ohlcData as $timeframe => $candles) {
            if (!is_array($candles)) {
                continue;
            }
            
            foreach ($candles as $candle) {
                if (!isset($candle['open'], $candle['high'], $candle['low'], $candle['close'])) {
                    return response()->json([
                        'message' => 'Invalid OHLC data structure. Each candle must have open, high, low, close.',
                    ], 422);
                }
            }
        }
        
        // Perform technical analysis for each timeframe separately
        $predictions = [];
        
        // M15 Analysis - Always include H1 and H4 for context
        if (!empty($ohlcData['15m'])) {
            $m15Data = [
                '15m' => $ohlcData['15m'], 
                'H1' => $ohlcData['H1'] ?? [], 
                'H4' => $ohlcData['H4'] ?? []
            ];
            $m15Analysis = $this->technicalService->analyzeForTimeframe($m15Data, '15m');
            $predictions['M15'] = [
                'timeframe' => 'M15',
                'timeframe_label' => '15 Minutes',
                'modules' => $m15Analysis['modules'],
                'overall' => $m15Analysis['overall'],
            ];
        }
        
        // H1 Analysis - Always include H4 for context
        if (!empty($ohlcData['H1'])) {
            $h1Data = [
                'H1' => $ohlcData['H1'], 
                'H4' => $ohlcData['H4'] ?? []
            ];
            $h1Analysis = $this->technicalService->analyzeForTimeframe($h1Data, 'H1');
            $predictions['H1'] = [
                'timeframe' => 'H1',
                'timeframe_label' => '1 Hour',
                'modules' => $h1Analysis['modules'],
                'overall' => $h1Analysis['overall'],
            ];
        }
        
        // H4 Analysis - Include H1 for modules that need it
        if (!empty($ohlcData['H4'])) {
            $h4Data = [
                'H4' => $ohlcData['H4'],
                'H1' => $ohlcData['H1'] ?? [] // Include H1 for modules that need it
            ];
            $h4Analysis = $this->technicalService->analyzeForTimeframe($h4Data, 'H4');
            $predictions['H4'] = [
                'timeframe' => 'H4',
                'timeframe_label' => '4 Hours',
                'modules' => $h4Analysis['modules'],
                'overall' => $h4Analysis['overall'],
            ];
        }
        
        // Calculate combined overall recommendation
        $combinedOverall = $this->calculateCombinedOverall($predictions);
        
        return response()->json([
            'symbol' => $symbol,
            'prediction_3_technical' => [
                'type' => 'Technical Analysis Based',
                'description' => 'Prediksi berdasarkan analisis teknikal per timeframe (M15, H1, H4) dengan 7 modul',
                'predictions' => $predictions,
                'combined_overall' => $combinedOverall,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
    
    /**
     * Calculate combined overall recommendation from multiple timeframe predictions.
     */
    private function calculateCombinedOverall(array $predictions): array
    {
        if (empty($predictions)) {
            return [
                'risk_score' => 0,
                'status' => 'unknown',
                'status_label' => 'Unknown',
                'confidence' => 0,
                'recommendation' => 'Tidak ada data untuk analisis.',
            ];
        }
        
        $totalRisk = 0;
        $totalWeight = 0;
        $dangerCount = 0;
        $cautionCount = 0;
        $safeCount = 0;
        
        // Weight: H4 (40%), H1 (40%), M15 (20%)
        $weights = ['H4' => 40, 'H1' => 40, 'M15' => 20];
        
        foreach ($predictions as $tf => $pred) {
            if (!isset($pred['overall']['risk_score'])) {
                continue;
            }
            
            $weight = $weights[$tf] ?? 20;
            $riskScore = $pred['overall']['risk_score'];
            
            $totalRisk += $riskScore * $weight;
            $totalWeight += $weight;
            
            if ($pred['overall']['status'] === 'danger') {
                $dangerCount++;
            } elseif ($pred['overall']['status'] === 'caution') {
                $cautionCount++;
            } else {
                $safeCount++;
            }
        }
        
        $finalRiskScore = $totalWeight > 0 ? $totalRisk / $totalWeight : 0;
        
        // Determine overall status
        $overallStatus = 'safe';
        if ($finalRiskScore >= 70 || $dangerCount >= 2) {
            $overallStatus = 'danger';
        } elseif ($finalRiskScore >= 50 || $dangerCount >= 1 || $cautionCount >= 2) {
            $overallStatus = 'caution';
        }
        
        // Generate recommendation
        $parts = [];
        if ($overallStatus === 'danger') {
            $parts[] = "🔴 PAUSE EA";
            $parts[] = "Risk Score: " . round($finalRiskScore, 1) . "/100";
            $parts[] = $dangerCount . " timeframe menunjukkan DANGER";
            $parts[] = "Kondisi market tidak cocok untuk EA Grid di semua timeframe.";
        } elseif ($overallStatus === 'caution') {
            $parts[] = "🟡 MONITOR";
            $parts[] = "Risk Score: " . round($finalRiskScore, 1) . "/100";
            if ($dangerCount > 0) {
                $parts[] = $dangerCount . " timeframe menunjukkan DANGER";
            }
            $parts[] = $cautionCount . " timeframe menunjukkan CAUTION";
            $parts[] = "Perhatikan pergerakan market. Pertimbangkan reduce position size.";
        } else {
            $parts[] = "🟢 EA SAFE";
            $parts[] = "Risk Score: " . round($finalRiskScore, 1) . "/100";
            $parts[] = "Kondisi market relatif aman untuk EA Grid.";
        }
        
        return [
            'risk_score' => round($finalRiskScore, 1),
            'status' => $overallStatus,
            'status_label' => $overallStatus === 'danger' ? '🔴 Danger' : ($overallStatus === 'caution' ? '🟡 Caution' : '🟢 Safe'),
            'confidence' => min(100, round($finalRiskScore + 20, 1)),
            'timeframe_summary' => [
                'danger' => $dangerCount,
                'caution' => $cautionCount,
                'safe' => $safeCount,
            ],
            'recommendation' => implode(" | ", $parts),
        ];
    }
    
    /**
     * Predict EA status based on ForexFactory actual impact + High-Impact Events guide.
     * Diperkuat dengan algoritma yang mempertimbangkan multiple factors.
     */
    private function predictByForexFactoryImpact(NewsItem $news): array
    {
        $reasons = []; // Array untuk menyimpan alasan-alasan
        $confidence = 0.0;
        $predictedStatus = null;
        
        // Factor 1: ForexFactory Impact Level
        $impactWeight = 0;
        if ($news->impact === 'high') {
            $predictedStatus = 'danger';
            $impactWeight = 75.0;
            $reasons[] = [
                'factor' => 'ForexFactory Impact',
                'value' => 'High',
                'weight' => 75,
                'explanation' => 'ForexFactory menandai event ini sebagai High Impact, yang biasanya menyebabkan volatilitas tinggi dan pergerakan harga signifikan.'
            ];
        } elseif ($news->impact === 'medium') {
            $predictedStatus = 'caution';
            $impactWeight = 50.0;
            $reasons[] = [
                'factor' => 'ForexFactory Impact',
                'value' => 'Medium',
                'weight' => 50,
                'explanation' => 'ForexFactory menandai event ini sebagai Medium Impact. Market mungkin mengalami pergerakan sedang, perlu monitoring.'
            ];
        } elseif ($news->impact === 'low') {
            $predictedStatus = 'safe';
            $impactWeight = 60.0;
            $reasons[] = [
                'factor' => 'ForexFactory Impact',
                'value' => 'Low',
                'weight' => 60,
                'explanation' => 'ForexFactory menandai event ini sebagai Low Impact. Market cenderung sideways, EA relatif aman.'
            ];
        }
        
        // Factor 2: High-Impact Events Guide (Panduan)
        $highImpactInfo = $this->getHighImpactEventInfo($news->title);
        $guideWeight = 0;
        
        if ($highImpactInfo['is_high_impact']) {
            $eventType = $highImpactInfo['type'];
            $guideWeight = 15.0; // Bonus weight untuk known high-impact events
            
            // Adjust prediction jika event type sangat berbahaya
            if (in_array($eventType, ['NFP', 'FOMC', 'CPI', 'PCE'])) {
                if ($predictedStatus !== 'danger') {
                    $predictedStatus = 'caution'; // Upgrade to caution
                }
                $guideWeight = 20.0; // Higher weight for critical events
            }
            
            $reasons[] = [
                'factor' => 'High-Impact Events Guide',
                'value' => $eventType,
                'weight' => $guideWeight,
                'explanation' => $this->getHighImpactWarning($eventType)
            ];
        }
        
        // Factor 3: Currency Pair Analysis
        $currencyWeight = 0;
        $majorCurrencies = ['USD', 'EUR', 'GBP', 'JPY'];
        if (in_array($news->currency, $majorCurrencies)) {
            $currencyWeight = 5.0;
            $reasons[] = [
                'factor' => 'Currency Analysis',
                'value' => $news->currency . ' (Major Currency)',
                'weight' => 5,
                'explanation' => 'Event pada major currency (' . $news->currency . ') biasanya memiliki dampak lebih luas terhadap market.'
            ];
        }
        
        // Factor 4: Time-based Analysis (jika ada)
        $timeWeight = 0;
        if ($news->time) {
            $hour = (int)$news->time->format('H');
            // Market hours analysis
            if ($hour >= 8 && $hour <= 17) {
                $timeWeight = 5.0;
                $reasons[] = [
                    'factor' => 'Market Hours',
                    'value' => 'Active Trading Hours',
                    'weight' => 5,
                    'explanation' => 'Event terjadi pada jam aktif trading, volume dan volatilitas biasanya lebih tinggi.'
                ];
            }
        }
        
        // Calculate final confidence
        $confidence = min(100, $impactWeight + $guideWeight + $currencyWeight + $timeWeight);
        
        // Ensure confidence minimum
        if ($confidence < 50 && $predictedStatus === 'danger') {
            $confidence = 70.0; // Minimum confidence for danger
        } elseif ($confidence < 40 && $predictedStatus === 'caution') {
            $confidence = 50.0; // Minimum confidence for caution
        } elseif ($confidence < 50 && $predictedStatus === 'safe') {
            $confidence = 60.0; // Minimum confidence for safe
        }
        
        // Generate recommendation dengan detail
        $recommendation = $this->generateImpactRecommendation($predictedStatus, $confidence, $highImpactInfo, $news);
        
        return [
            'status' => $predictedStatus,
            'confidence' => round($confidence, 1),
            'recommendation' => $recommendation,
            'reasons' => $reasons, // Array of reasons
            'factors' => [
                'forexfactory_impact' => $news->impact,
                'high_impact_event' => $highImpactInfo['is_high_impact'],
                'event_type' => $highImpactInfo['type'] ?? null,
                'currency' => $news->currency,
                'total_weight' => round($confidence, 1),
            ],
        ];
    }
    
    /**
     * Generate detailed recommendation for impact-based prediction.
     */
    private function generateImpactRecommendation(string $status, float $confidence, array $highImpactInfo, NewsItem $news): string
    {
        $parts = [];
        
        if ($status === 'danger') {
            $parts[] = "🔴 DISABLE EA";
            if ($highImpactInfo['is_high_impact']) {
                $parts[] = "Event: " . $highImpactInfo['type'];
            }
            $parts[] = "ForexFactory Impact: HIGH";
            $parts[] = "Confidence: " . round($confidence, 0) . "%";
            $parts[] = "Volatilitas tinggi diperkirakan. Disarankan disable EA 30-60 menit sebelum/sesudah event.";
        } elseif ($status === 'caution') {
            $parts[] = "🟡 MONITOR";
            if ($highImpactInfo['is_high_impact']) {
                $parts[] = "Event: " . $highImpactInfo['type'];
            }
            $parts[] = "ForexFactory Impact: MEDIUM";
            $parts[] = "Confidence: " . round($confidence, 0) . "%";
            $parts[] = "Perhatikan pergerakan market. Pertimbangkan reduce position size.";
        } elseif ($status === 'safe') {
            $parts[] = "🟢 EA SAFE";
            $parts[] = "ForexFactory Impact: LOW";
            $parts[] = "Confidence: " . round($confidence, 0) . "%";
            $parts[] = "Market cenderung sideways. EA dapat berjalan normal.";
        }
        
        return implode(" | ", $parts);
    }
    
    /**
     * Calculate safe trading windows based on danger events.
     */
    private function calculateSafeWindows(array $dangerWindows): array
    {
        if (empty($dangerWindows)) {
            return [[
                'start' => '00:00',
                'end' => '23:59',
                'description' => 'EA dapat diaktifkan sepanjang hari'
            ]];
        }
        
        // Convert danger windows to blocked time ranges
        $blockedRanges = [];
        foreach ($dangerWindows as $window) {
            $eventTime = Carbon::createFromFormat('H:i', $window['time']);
            $start = $eventTime->copy()->subMinutes($window['before'])->format('H:i');
            $end = $eventTime->copy()->addMinutes($window['after'])->format('H:i');
            
            $blockedRanges[] = [
                'start' => $start,
                'end' => $end,
                'event' => $window['title'],
            ];
        }
        
        // Sort blocked ranges by start time
        usort($blockedRanges, fn($a, $b) => strcmp($a['start'], $b['start']));
        
        // Merge overlapping ranges
        $mergedBlocked = [];
        foreach ($blockedRanges as $range) {
            if (empty($mergedBlocked)) {
                $mergedBlocked[] = $range;
            } else {
                $last = &$mergedBlocked[count($mergedBlocked) - 1];
                if ($range['start'] <= $last['end']) {
                    // Overlapping, extend the end
                    if ($range['end'] > $last['end']) {
                        $last['end'] = $range['end'];
                    }
                } else {
                    $mergedBlocked[] = $range;
                }
            }
        }
        
        // Calculate safe windows (inverse of blocked)
        $safeWindows = [];
        $currentStart = '00:00';
        
        foreach ($mergedBlocked as $blocked) {
            if ($blocked['start'] > $currentStart) {
                $safeWindows[] = [
                    'start' => $currentStart,
                    'end' => $blocked['start'],
                    'description' => "EA aman dari {$currentStart} - {$blocked['start']}"
                ];
            }
            $currentStart = $blocked['end'];
        }
        
        // Add final safe window if any time left
        if ($currentStart < '23:59') {
            $safeWindows[] = [
                'start' => $currentStart,
                'end' => '23:59',
                'description' => "EA aman dari {$currentStart} - 23:59"
            ];
        }
        
        return $safeWindows;
    }
    
    /**
     * Get day prediction with schedule recommendation.
     */
    private function getDayPredictionWithSchedule(array $predictions, array $safeWindows, array $dangerWindows, string $source = 'mixed'): array
    {
        $totalEvents = count($predictions);
        $dangerCount = count(array_filter($predictions, fn($p) => 
            $p['predicted_status'] === 'danger' || 
            (isset($p['current_status']) && $p['current_status'] === 'danger')
        ));
        $cautionCount = count(array_filter($predictions, fn($p) => $p['predicted_status'] === 'caution'));
        $highImpactCount = count(array_filter($predictions, fn($p) => $p['impact'] === 'high'));
        $markedDangerCount = count(array_filter($predictions, fn($p) => 
            isset($p['current_status']) && $p['current_status'] === 'danger'
        ));
        
        $avgConfidence = $totalEvents > 0 
            ? round(array_sum(array_column($predictions, 'confidence')) / $totalEvents, 1)
            : 0;
        
        // Determine overall status based on source
        $status = 'safe';
        $message = 'Hari ini aman untuk EA';
        
        if ($source === 'historical_marking') {
            // For historical: prioritize user markings
            if ($markedDangerCount > 0 || $dangerCount > 2) {
                $status = 'danger';
                $message = "Hari risiko tinggi: {$dangerCount} event danger (berdasarkan marking Anda)";
            } elseif ($dangerCount > 0 || $highImpactCount > 2) {
                $status = 'caution';
                $message = "Perhatian: {$dangerCount} danger, {$highImpactCount} high-impact events";
            }
        } else {
            // For ForexFactory impact: based on impact level
            if ($dangerCount > 0 || $highImpactCount > 2) {
                $status = 'danger';
                $message = "Hari risiko tinggi: {$dangerCount} high-impact events dari ForexFactory";
            } elseif ($cautionCount > 0 || $highImpactCount > 0) {
                $status = 'caution';
                $message = "Perhatian: {$cautionCount} medium-impact, {$highImpactCount} high-impact events";
            }
        }
        
        // Build schedule recommendation
        $scheduleRecommendation = $this->buildScheduleRecommendation($safeWindows, $dangerWindows);
        
        return [
            'status' => $status,
            'message' => $message,
            'source' => $source,
            'total_events' => $totalEvents,
            'danger_count' => $dangerCount,
            'caution_count' => $cautionCount,
            'high_impact_count' => $highImpactCount,
            'marked_danger_count' => $markedDangerCount,
            'avg_confidence' => $avgConfidence,
            'schedule_recommendation' => $scheduleRecommendation,
            'safe_windows_count' => count($safeWindows),
            'danger_windows_count' => count($dangerWindows),
        ];
    }
    
    /**
     * Build human-readable schedule recommendation.
     */
    private function buildScheduleRecommendation(array $safeWindows, array $dangerWindows): array
    {
        $recommendations = [];
        
        if (empty($dangerWindows)) {
            $recommendations[] = [
                'type' => 'safe',
                'icon' => '🟢',
                'message' => 'EA dapat diaktifkan sepanjang hari (00:00 - 23:59)',
                'time_range' => '00:00 - 23:59',
            ];
            return $recommendations;
        }
        
        // Add safe window recommendations
        foreach ($safeWindows as $window) {
            $duration = $this->calculateDuration($window['start'], $window['end']);
            if ($duration >= 60) { // Only show windows >= 1 hour
                $recommendations[] = [
                    'type' => 'safe',
                    'icon' => '🟢',
                    'message' => "EA AMAN: {$window['start']} - {$window['end']} ({$duration} menit)",
                    'time_range' => "{$window['start']} - {$window['end']}",
                    'duration_minutes' => $duration,
                ];
            }
        }
        
        // Add danger window warnings
        foreach ($dangerWindows as $window) {
            $eventTime = Carbon::createFromFormat('H:i', $window['time']);
            $start = $eventTime->copy()->subMinutes($window['before'])->format('H:i');
            $end = $eventTime->copy()->addMinutes($window['after'])->format('H:i');
            
            $recommendations[] = [
                'type' => 'danger',
                'icon' => '🔴',
                'message' => "DISABLE EA: {$start} - {$end} ({$window['title']})",
                'time_range' => "{$start} - {$end}",
                'event' => $window['title'],
            ];
        }
        
        // Sort by time
        usort($recommendations, function($a, $b) {
            $timeA = explode(' - ', $a['time_range'])[0];
            $timeB = explode(' - ', $b['time_range'])[0];
            return strcmp($timeA, $timeB);
        });
        
        return $recommendations;
    }
    
    /**
     * Calculate duration in minutes between two times.
     */
    private function calculateDuration(string $start, string $end): int
    {
        $startTime = Carbon::createFromFormat('H:i', $start);
        $endTime = Carbon::createFromFormat('H:i', $end);
        return $startTime->diffInMinutes($endTime);
    }
    
    /**
     * Get notable high-impact events for reference (separate from prediction).
     */
    public function notableEvents(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->input('date', today()->format('Y-m-d'));
        
        // Filter by user (created_by)
        $news = NewsItem::forDate($date)
            ->where('created_by', $user->id)
            ->orderBy('time')
            ->get();
        
        $notableEvents = [];
        
        foreach ($news as $item) {
            $info = $this->getHighImpactEventInfo($item->title);
            if ($info['is_high_impact']) {
                $notableEvents[] = [
                    'id' => $item->id,
                    'type' => $info['type'],
                    'title' => $item->title,
                    'time' => $item->formatted_time,
                    'currency' => $item->currency,
                    'actual_impact' => $item->impact, // Actual impact from ForexFactory
                    'ea_status' => $item->ea_status,
                    'note' => $this->getEventNote($info['type']),
                    'historical_stats' => $this->getEventHistoricalStats($item->title, $item->currency, $item->created_by),
                ];
            }
        }
        
        return response()->json([
            'date' => $date,
            'notable_events' => $notableEvents,
            'reference_info' => $this->getHighImpactReference(),
        ]);
    }
    
    /**
     * Get note for event type.
     */
    private function getEventNote(string $type): string
    {
        if ($type === 'NFP') {
            return 'Non-Farm Payroll biasanya high impact, tapi perhatikan actual impact dari ForexFactory';
        } elseif ($type === 'FOMC') {
            return 'FOMC & Fed Rate biasanya menyebabkan volatilitas tinggi';
        } elseif ($type === 'CPI') {
            return 'CPI/Inflasi penting untuk USD, biasanya high impact';
        } elseif ($type === 'PCE') {
            return 'PCE adalah indikator inflasi favorit The Fed';
        } elseif ($type === 'ADP') {
            return 'ADP sebagai preview NFP, dampak bervariasi';
        } elseif ($type === 'JOBLESS') {
            return 'Jobless Claims weekly data, dampak sedang';
        } elseif ($type === 'GDP') {
            return 'GDP bisa high/low impact tergantung rilis (Advance/Final)';
        } elseif ($type === 'ISM') {
            return 'ISM PMI penting untuk sektor manufacturing/services';
        } elseif ($type === 'RETAIL') {
            return 'Retail Sales menunjukkan consumer spending';
        } elseif ($type === 'CB_SPEECH') {
            return 'Central Bank speech bisa mengejutkan market';
        } else {
            return 'Monitor actual impact dari ForexFactory';
        }
    }
    
    /**
     * Get historical stats for specific event.
     */
    private function getEventHistoricalStats(string $title, string $currency, int $userId): ?array
    {
        $normalizedTitle = $this->normalizeNewsTitle($title);
        
        // Filter by user (created_by)
        $historical = NewsItem::where('currency', $currency)
            ->where('created_by', $userId)
            ->whereIn('ea_status', ['safe', 'caution', 'danger'])
            ->whereRaw("LOWER(title) LIKE ?", ['%' . strtolower($normalizedTitle) . '%'])
            ->get();
        
        $total = $historical->count();
        
        if ($total < 2) {
            return null;
        }
        
        return [
            'total' => $total,
            'safe_pct' => round(($historical->where('ea_status', 'safe')->count() / $total) * 100, 1),
            'caution_pct' => round(($historical->where('ea_status', 'caution')->count() / $total) * 100, 1),
            'danger_pct' => round(($historical->where('ea_status', 'danger')->count() / $total) * 100, 1),
        ];
    }
    
    /**
     * Get high-impact events reference info.
     */
    private function getHighImpactReference(): array
    {
        return [
            [
                'type' => 'NFP',
                'name' => 'Non-Farm Payroll',
                'description' => 'Data employment bulanan AS, biasanya dirilis Jumat pertama setiap bulan',
                'typical_impact' => 'High',
                'advice' => 'Disable EA 30-60 menit sebelum dan sesudah rilis',
            ],
            [
                'type' => 'FOMC',
                'name' => 'FOMC / Fed Rate Decision',
                'description' => 'Keputusan suku bunga The Fed, 8x setahun',
                'typical_impact' => 'High',
                'advice' => 'Disable EA selama press conference',
            ],
            [
                'type' => 'CPI',
                'name' => 'Consumer Price Index',
                'description' => 'Data inflasi bulanan',
                'typical_impact' => 'High',
                'advice' => 'Volatilitas tinggi pada USD pairs',
            ],
            [
                'type' => 'PCE',
                'name' => 'PCE Price Index',
                'description' => 'Indikator inflasi favorit The Fed',
                'typical_impact' => 'High',
                'advice' => 'Perhatikan Core PCE',
            ],
            [
                'type' => 'GDP',
                'name' => 'Gross Domestic Product',
                'description' => 'Ada 3 rilis: Advance, Preliminary, Final',
                'typical_impact' => 'Varies (Advance=High, Final=Low)',
                'advice' => 'Advance GDP lebih impactful',
            ],
            [
                'type' => 'ISM',
                'name' => 'ISM PMI',
                'description' => 'Manufacturing & Services PMI',
                'typical_impact' => 'Medium-High',
                'advice' => 'Perhatikan nilai di atas/bawah 50',
            ],
            [
                'type' => 'ADP',
                'name' => 'ADP Employment',
                'description' => 'Preview untuk NFP',
                'typical_impact' => 'Medium',
                'advice' => 'Dampak lebih rendah dari NFP',
            ],
            [
                'type' => 'JOBLESS',
                'name' => 'Jobless Claims',
                'description' => 'Weekly unemployment data',
                'typical_impact' => 'Medium',
                'advice' => 'Rilis setiap Kamis',
            ],
        ];
    }
    
    /**
     * Get historical statistics for prediction learning.
     */
    public function predictionStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->input('days', 90);
        $startDate = now()->subDays($days)->format('Y-m-d');
        
        // Filter by user (created_by)
        $markedNews = NewsItem::where('date', '>=', $startDate)
            ->where('created_by', $user->id)
            ->whereIn('ea_status', ['safe', 'caution', 'danger'])
            ->get();
        
        $stats = [];
        
        foreach ($markedNews as $news) {
            $normalizedTitle = $this->normalizeNewsTitle($news->title);
            $key = $news->currency . '|' . $normalizedTitle;
            
            if (!isset($stats[$key])) {
                $stats[$key] = [
                    'title' => $news->title,
                    'normalized_title' => $normalizedTitle,
                    'currency' => $news->currency,
                    'impact' => $news->impact,
                    'total' => 0,
                    'safe' => 0,
                    'caution' => 0,
                    'danger' => 0,
                    'is_high_impact' => $this->isHighImpactEvent($news->title),
                ];
            }
            
            $stats[$key]['total']++;
            $stats[$key][$news->ea_status]++;
        }
        
        // Calculate percentages
        foreach ($stats as &$stat) {
            if ($stat['total'] > 0) {
                $stat['safe_pct'] = round(($stat['safe'] / $stat['total']) * 100, 1);
                $stat['caution_pct'] = round(($stat['caution'] / $stat['total']) * 100, 1);
                $stat['danger_pct'] = round(($stat['danger'] / $stat['total']) * 100, 1);
            }
        }
        
        // Sort by total occurrences
        uasort($stats, fn($a, $b) => $b['total'] <=> $a['total']);
        
        // Get high impact events statistics (for current user)
        $highImpactStats = $this->getHighImpactEventsStats($startDate, $user->id);
        
        return response()->json([
            'period_days' => $days,
            'total_marked' => count($markedNews),
            'unique_events' => count($stats),
            'events' => array_values($stats),
            'high_impact_stats' => $highImpactStats,
            'learning_summary' => $this->getLearningsSummary($stats),
        ]);
    }
    
    /**
     * Predict EA status for a news item based on historical data.
     * Mencari data historis dari semua tanggal (bukan hanya hari ini),
     * hanya yang sudah di-mark user (ea_status tidak null).
     */
    private function predictEaStatus(NewsItem $news): array
    {
        $normalizedTitle = $this->normalizeNewsTitle($news->title);
        $highImpactInfo = $this->getHighImpactEventInfo($news->title);
        
        // Find similar historical news (same user) - dari SEMUA tanggal, bukan hanya hari ini
        // Hanya ambil yang sudah di-mark user (ea_status IN ['safe', 'caution', 'danger'])
        $historical = NewsItem::where('currency', $news->currency)
            ->where('created_by', $news->created_by) // Same user only
            ->whereNotNull('ea_status') // Hanya yang sudah di-mark (tidak null)
            ->whereIn('ea_status', ['safe', 'caution', 'danger']) // Hanya yang sudah di-mark
            ->where(function($q) use ($normalizedTitle, $news) {
                // Match by normalized title atau similar keywords
                // Prioritaskan exact match atau close match
                $normalizedTitleLower = strtolower($normalizedTitle);
                $keywords = $this->extractKeywords($news->title);
                
                $q->whereRaw("LOWER(title) = ?", [$normalizedTitleLower]) // Exact match
                  ->orWhereRaw("LOWER(title) LIKE ?", ['%' . $normalizedTitleLower . '%']) // Contains
                  ->orWhere('title', 'LIKE', '%' . $keywords . '%'); // Keywords match
            })
            ->orderBy('date', 'desc') // Urutkan dari yang terbaru
            ->get();
        
        $total = $historical->count();
        
        if ($total === 0) {
            // No historical data - return empty prediction dengan flag has_historical_data = false
            return [
                'status' => null, // Tidak ada prediksi jika belum ada data historis
                'confidence' => 0,
                'has_historical_data' => false, // Flag untuk frontend
                'historical' => [
                    'total_occurrences' => 0,
                    'safe_count' => 0,
                    'caution_count' => 0,
                    'danger_count' => 0,
                    'safe_pct' => 0,
                    'caution_pct' => 0,
                    'danger_pct' => 0,
                    'occurrences' => [],
                ],
                'is_high_impact_event' => $highImpactInfo['is_high_impact'],
                'high_impact_type' => $highImpactInfo['type'],
                'recommendation' => 'Belum ada data historis. Tandai news ini dengan Safe/Caution/Danger untuk melatih prediksi.',
            ];
        }
        
        // Hitung jumlah untuk masing-masing status
        $safe = $historical->where('ea_status', 'safe')->count();
        $caution = $historical->where('ea_status', 'caution')->count();
        $danger = $historical->where('ea_status', 'danger')->count();
        
        // Hitung persentase
        $safePct = $total > 0 ? round(($safe / $total) * 100, 1) : 0;
        $cautionPct = $total > 0 ? round(($caution / $total) * 100, 1) : 0;
        $dangerPct = $total > 0 ? round(($danger / $total) * 100, 1) : 0;
        
        // Determine predicted status berdasarkan persentase tertinggi
        $maxPct = max($safePct, $cautionPct, $dangerPct);
        $predictedStatus = 'unknown';
        
        if ($dangerPct === $maxPct && $dangerPct > 0) {
            $predictedStatus = 'danger';
        } elseif ($cautionPct === $maxPct && $cautionPct > 0) {
            $predictedStatus = 'caution';
        } elseif ($safePct === $maxPct && $safePct > 0) {
            $predictedStatus = 'safe';
        } else {
            // Jika semua 0 atau sama, gunakan impact-based
            return $this->predictByImpact($news, $highImpactInfo);
        }
        
        // ============================================
        // ALGORITMA PENGUATAN PREDIKSI 2
        // ============================================
        // Kombinasi dengan data impact dan high-impact events untuk meningkatkan akurasi
        
        $adjustedConfidence = $maxPct;
        $adjustmentReasons = [];
        
        // Factor 1: Sample Size (Semakin banyak data, semakin reliable)
        $sampleSizeWeight = 0;
        if ($total >= 10) {
            $sampleSizeWeight = 10.0;
            $adjustmentReasons[] = [
                'factor' => 'Sample Size',
                'value' => $total . ' occurrences',
                'weight' => 10,
                'explanation' => 'Data historis cukup banyak (' . $total . 'x), prediksi lebih reliable.'
            ];
        } elseif ($total >= 5) {
            $sampleSizeWeight = 5.0;
            $adjustmentReasons[] = [
                'factor' => 'Sample Size',
                'value' => $total . ' occurrences',
                'weight' => 5,
                'explanation' => 'Data historis sedang (' . $total . 'x), prediksi cukup reliable.'
            ];
        } else {
            $adjustmentReasons[] = [
                'factor' => 'Sample Size',
                'value' => $total . ' occurrences',
                'weight' => 0,
                'explanation' => 'Data historis masih sedikit (' . $total . 'x), prediksi perlu konfirmasi lebih lanjut.'
            ];
        }
        
        // Factor 2: Consistency (Semakin konsisten hasilnya, semakin tinggi confidence)
        $consistencyWeight = 0;
        $consistencyThreshold = 70; // Jika salah satu status >= 70%, sangat konsisten
        if ($maxPct >= $consistencyThreshold) {
            $consistencyWeight = 10.0;
            $adjustmentReasons[] = [
                'factor' => 'Consistency',
                'value' => round($maxPct, 0) . '% ' . $predictedStatus,
                'weight' => 10,
                'explanation' => 'Hasil sangat konsisten (' . round($maxPct, 0) . '% ' . $predictedStatus . '), pattern jelas.'
            ];
        } elseif ($maxPct >= 60) {
            $consistencyWeight = 5.0;
            $adjustmentReasons[] = [
                'factor' => 'Consistency',
                'value' => round($maxPct, 0) . '% ' . $predictedStatus,
                'weight' => 5,
                'explanation' => 'Hasil cukup konsisten (' . round($maxPct, 0) . '% ' . $predictedStatus . ').'
            ];
        }
        
        // Factor 3: High-Impact Events Cross-Validation
        $crossValidationWeight = 0;
        if ($highImpactInfo['is_high_impact'] && $news->impact === 'high') {
            // Jika historical data menunjukkan danger/caution untuk high-impact event, boost confidence
            if ($predictedStatus === 'danger' || $predictedStatus === 'caution') {
                $crossValidationWeight = 10.0;
                $adjustmentReasons[] = [
                    'factor' => 'Cross-Validation',
                    'value' => $highImpactInfo['type'] . ' + Historical Data',
                    'weight' => 10,
                    'explanation' => 'High-impact event (' . $highImpactInfo['type'] . ') dikonfirmasi oleh data historis Anda. Prediksi lebih kuat.'
                ];
            }
        }
        
        // Factor 4: Recent Trend (Berat lebih pada data terbaru)
        $recentTrendWeight = 0;
        $recentCount = $historical->take(5)->count(); // 5 terbaru
        if ($recentCount >= 3) {
            $recentSafe = $historical->take(5)->where('ea_status', 'safe')->count();
            $recentDanger = $historical->take(5)->where('ea_status', 'danger')->count();
            $recentCaution = $historical->take(5)->where('ea_status', 'caution')->count();
            
            $recentMax = max($recentSafe, $recentDanger, $recentCaution);
            $recentStatus = $recentDanger >= $recentMax ? 'danger' : ($recentCaution >= $recentMax ? 'caution' : 'safe');
            
            // Jika trend terbaru sama dengan overall prediction, boost confidence
            if ($recentStatus === $predictedStatus) {
                $recentTrendWeight = 5.0;
                $adjustmentReasons[] = [
                    'factor' => 'Recent Trend',
                    'value' => 'Trend terbaru konsisten',
                    'weight' => 5,
                    'explanation' => '5 kemunculan terakhir menunjukkan pattern yang sama dengan keseluruhan data.'
                ];
            }
        }
        
        // Calculate adjusted confidence
        $adjustedConfidence = min(100, $maxPct + $sampleSizeWeight + $consistencyWeight + $crossValidationWeight + $recentTrendWeight);
        
        // Adjust prediction jika ada konflik dengan high-impact events
        if ($highImpactInfo['is_high_impact'] && $news->impact === 'high') {
            // Jika historical menunjukkan safe tapi ini high-impact event, downgrade ke caution
            if ($predictedStatus === 'safe' && $dangerPct >= 20) {
                $predictedStatus = 'caution';
                $adjustmentReasons[] = [
                    'factor' => 'High-Impact Override',
                    'value' => $highImpactInfo['type'],
                    'weight' => 0,
                    'explanation' => 'Event ini termasuk high-impact (' . $highImpactInfo['type'] . '), meskipun historis menunjukkan safe, disarankan caution.'
                ];
            }
        }
        
        // Generate recommendation dengan detail
        $recommendation = $this->generateHistoricalRecommendation(
            $predictedStatus, 
            $adjustedConfidence, 
            $total, 
            $safePct, 
            $cautionPct, 
            $dangerPct,
            $highImpactInfo
        );
        
        return [
            'status' => $predictedStatus,
            'confidence' => round($adjustedConfidence, 1),
            'base_confidence' => round($maxPct, 1), // Confidence sebelum adjustment
            'has_historical_data' => true, // Flag untuk frontend
            'historical' => [
                'total_occurrences' => $total, // Total berapa kali news ini muncul dan sudah di-mark
                'safe_count' => $safe,
                'caution_count' => $caution,
                'danger_count' => $danger,
                'safe_pct' => $safePct,
                'caution_pct' => $cautionPct,
                'danger_pct' => $dangerPct,
                'occurrences' => $historical->map(function($item) {
                    return [
                        'date' => $item->date->format('Y-m-d'),
                        'time' => $item->time ? $item->time->format('H:i') : null,
                        'ea_status' => $item->ea_status,
                        'impact' => $item->impact,
                    ];
                })->toArray(), // Detail setiap kemunculan
            ],
            'algorithm_factors' => $adjustmentReasons, // Alasan-alasan dari algoritma penguatan
            'is_high_impact_event' => $highImpactInfo['is_high_impact'],
            'high_impact_type' => $highImpactInfo['type'],
            'recommendation' => $recommendation,
        ];
    }
    
    /**
     * Generate detailed recommendation for historical-based prediction.
     */
    private function generateHistoricalRecommendation(
        string $status, 
        float $confidence, 
        int $total, 
        float $safePct, 
        float $cautionPct, 
        float $dangerPct,
        array $highImpactInfo
    ): string {
        $parts = [];
        
        if ($status === 'danger') {
            $parts[] = "🔴 DISABLE EA";
            $parts[] = "Berdasarkan " . $total . "x data historis";
            $parts[] = "Danger: " . round($dangerPct, 0) . "% | Caution: " . round($cautionPct, 0) . "% | Safe: " . round($safePct, 0) . "%";
            $parts[] = "Confidence: " . round($confidence, 0) . "%";
            $parts[] = "Data historis menunjukkan pattern DANGER. Disarankan disable EA.";
        } elseif ($status === 'caution') {
            $parts[] = "🟡 MONITOR";
            $parts[] = "Berdasarkan " . $total . "x data historis";
            $parts[] = "Danger: " . round($dangerPct, 0) . "% | Caution: " . round($cautionPct, 0) . "% | Safe: " . round($safePct, 0) . "%";
            $parts[] = "Confidence: " . round($confidence, 0) . "%";
            $parts[] = "Data historis menunjukkan pattern CAUTION. Perhatikan pergerakan market.";
        } elseif ($status === 'safe') {
            $parts[] = "🟢 EA SAFE";
            $parts[] = "Berdasarkan " . $total . "x data historis";
            $parts[] = "Danger: " . round($dangerPct, 0) . "% | Caution: " . round($cautionPct, 0) . "% | Safe: " . round($safePct, 0) . "%";
            $parts[] = "Confidence: " . round($confidence, 0) . "%";
            $parts[] = "Data historis menunjukkan pattern SAFE. EA dapat berjalan normal.";
        }
        
        return implode(" | ", $parts);
    }
    
    /**
     * Predict by impact level when no historical data.
     */
    private function predictByImpact(NewsItem $news, array $highImpactInfo): array
    {
        // Convert impact to predicted status
        if ($news->impact === 'high') {
            $predictedStatus = 'danger';
        } elseif ($news->impact === 'medium') {
            $predictedStatus = 'caution';
        } else {
            $predictedStatus = 'safe';
        }
        
        // Set confidence based on impact
        if ($news->impact === 'high') {
            $confidence = 70.0;
        } elseif ($news->impact === 'medium') {
            $confidence = 50.0;
        } else {
            $confidence = 60.0;
        }
        
        // Higher confidence for known high impact events
        if ($highImpactInfo['is_high_impact']) {
            $predictedStatus = 'danger';
            $confidence = 85.0;
        }
        
        return [
            'status' => $predictedStatus,
            'confidence' => $confidence,
            'historical' => [
                'total' => 0,
                'safe' => 0,
                'caution' => 0,
                'danger' => 0,
                'safe_pct' => 0,
                'caution_pct' => 0,
                'danger_pct' => 0,
            ],
            'is_high_impact_event' => $highImpactInfo['is_high_impact'],
            'high_impact_type' => $highImpactInfo['type'],
            'recommendation' => $this->getRecommendation($predictedStatus, $confidence, $highImpactInfo),
        ];
    }
    
    /**
     * Get recommendation text based on prediction.
     */
    private function getRecommendation(string $status, float $confidence, array $highImpactInfo): string
    {
        if ($highImpactInfo['is_high_impact']) {
            return "⚠️ {$highImpactInfo['type']} - High volatility expected. Consider disabling EA 30-60 min before/after.";
        }
        
        if ($status === 'danger') {
            return $confidence >= 70 
                ? "🔴 DISABLE EA - Historical data shows {$confidence}% danger rate"
                : "🔴 Caution advised - {$confidence}% danger probability";
        } elseif ($status === 'caution') {
            return $confidence >= 60
                ? "🟡 MONITOR CLOSELY - {$confidence}% shows market movement"
                : "🟡 Be alert - Moderate risk detected";
        } elseif ($status === 'safe') {
            return $confidence >= 70
                ? "🟢 EA SAFE - {$confidence}% historical safety rate"
                : "🟢 Likely safe - Monitor for unexpected moves";
        } else {
            return "⚪ Insufficient data - Manual review recommended";
        }
    }
    
    /**
     * Get day prediction summary.
     */
    private function getDayPrediction(array $predictions): array
    {
        $totalEvents = count($predictions);
        $dangerCount = count(array_filter($predictions, fn($p) => $p['predicted_status'] === 'danger'));
        $cautionCount = count(array_filter($predictions, fn($p) => $p['predicted_status'] === 'caution'));
        $highImpactCount = count(array_filter($predictions, fn($p) => $p['is_high_impact_event']));
        
        $avgConfidence = $totalEvents > 0 
            ? round(array_sum(array_column($predictions, 'confidence')) / $totalEvents, 1)
            : 0;
        
        $status = 'safe';
        $message = 'Low risk day - EA can operate normally';
        
        if ($dangerCount > 0 || $highImpactCount > 0) {
            $status = 'danger';
            $message = "High risk: {$dangerCount} danger events, {$highImpactCount} high-impact news. Consider disabling EA.";
        } elseif ($cautionCount > 0) {
            $status = 'caution';
            $message = "Moderate risk: {$cautionCount} caution events. Monitor closely.";
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'total_events' => $totalEvents,
            'danger_count' => $dangerCount,
            'caution_count' => $cautionCount,
            'high_impact_count' => $highImpactCount,
            'avg_confidence' => $avgConfidence,
        ];
    }
    
    /**
     * List of known high-impact events.
     */
    private const HIGH_IMPACT_EVENTS = [
        // NFP
        'NFP' => ['Non-Farm Payroll', 'Nonfarm Payroll', 'NonFarm', 'Employment Change', 'NFP'],
        // FOMC
        'FOMC' => ['FOMC', 'Federal Reserve', 'Fed Interest Rate', 'Fed Rate', 'Powell Speaks', 'Fed Chair', 'Monetary Policy'],
        // CPI
        'CPI' => ['Consumer Price Index', 'CPI m/m', 'CPI y/y', 'Core CPI', 'Inflation Rate'],
        // PCE
        'PCE' => ['PCE Price Index', 'Core PCE', 'Personal Consumption', 'PCE m/m', 'PCE y/y'],
        // ADP
        'ADP' => ['ADP Non-Farm', 'ADP Employment', 'ADP Nonfarm'],
        // Jobless Claims
        'JOBLESS' => ['Jobless Claims', 'Unemployment Claims', 'Initial Claims', 'Continuing Claims'],
        // GDP
        'GDP' => ['GDP', 'Gross Domestic Product', 'Advanced GDP', 'Preliminary GDP', 'Final GDP'],
        // ISM PMI
        'ISM' => ['ISM Manufacturing', 'ISM Services', 'ISM PMI', 'Manufacturing PMI', 'Services PMI'],
        // Retail Sales
        'RETAIL' => ['Retail Sales', 'Core Retail'],
        // Central Bank Speeches
        'CB_SPEECH' => ['Governor Speaks', 'President Speaks', 'Chairman Speaks', 'ECB President', 'BOE Governor', 'BOJ Governor'],
    ];
    
    /**
     * Check if news is a high-impact event.
     */
    private function isHighImpactEvent(string $title): bool
    {
        return $this->getHighImpactEventInfo($title)['is_high_impact'];
    }
    
    /**
     * Get high-impact event info.
     */
    private function getHighImpactEventInfo(string $title): array
    {
        $titleLower = strtolower($title);
        
        foreach (self::HIGH_IMPACT_EVENTS as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($titleLower, strtolower($keyword)) !== false) {
                    return [
                        'is_high_impact' => true,
                        'type' => $type,
                        'matched_keyword' => $keyword,
                    ];
                }
            }
        }
        
        return [
            'is_high_impact' => false,
            'type' => null,
            'matched_keyword' => null,
        ];
    }
    
    /**
     * Get high-impact events for a date.
     */
    private function getHighImpactEventsForDate($news): array
    {
        $highImpactEvents = [];
        
        foreach ($news as $item) {
            $info = $this->getHighImpactEventInfo($item->title);
            if ($info['is_high_impact']) {
                $highImpactEvents[] = [
                    'id' => $item->id,
                    'type' => $info['type'],
                    'title' => $item->title,
                    'time' => $item->formatted_time,
                    'currency' => $item->currency,
                    'impact' => $item->impact,
                    'warning' => $this->getHighImpactWarning($info['type']),
                ];
            }
        }
        
        return $highImpactEvents;
    }
    
    /**
     * Get warning message for high-impact event type.
     */
    private function getHighImpactWarning(string $type): string
    {
        if ($type === 'NFP') {
            return '🔥 NFP - Major market mover! Extreme volatility expected. Disable EA 30-60 min before/after.';
        } elseif ($type === 'FOMC') {
            return '🔥 FOMC - Fed decision can cause rapid price swings. Disable EA during release.';
        } elseif ($type === 'CPI') {
            return '🔥 CPI/Inflation - High volatility on USD pairs. Market can spike 50-100 pips.';
        } elseif ($type === 'PCE') {
            return '🔥 PCE - Fed\'s preferred inflation gauge. Significant market impact expected.';
        } elseif ($type === 'ADP') {
            return '⚠️ ADP Employment - Precursor to NFP. Moderate to high volatility.';
        } elseif ($type === 'JOBLESS') {
            return '⚠️ Jobless Claims - Weekly employment data. Can cause quick price moves.';
        } elseif ($type === 'GDP') {
            return '🔥 GDP - Major economic indicator. High volatility on related currency.';
        } elseif ($type === 'ISM') {
            return '⚠️ ISM PMI - Important business activity indicator. Moderate impact.';
        } elseif ($type === 'RETAIL') {
            return '⚠️ Retail Sales - Consumer spending data. Can move markets.';
        } elseif ($type === 'CB_SPEECH') {
            return '⚠️ Central Bank Speech - May contain policy hints. Watch for surprises.';
        } else {
            return '⚠️ High-impact event - Exercise caution.';
        }
    }
    
    /**
     * Normalize news title for matching.
     */
    private function normalizeNewsTitle(string $title): string
    {
        // Remove common variations
        $normalized = preg_replace('/\s*(m\/m|y\/y|q\/q|MoM|YoY|QoQ)\s*/i', '', $title);
        $normalized = preg_replace('/\s*(preliminary|final|advanced|revised|flash)\s*/i', '', $normalized);
        $normalized = preg_replace('/\s*(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\s*/i', '', $normalized);
        $normalized = preg_replace('/\d+/', '', $normalized); // Remove numbers
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized)); // Normalize spaces
        
        return $normalized;
    }
    
    /**
     * Extract main keywords from title.
     */
    private function extractKeywords(string $title): string
    {
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with'];
        $words = explode(' ', strtolower($title));
        $keywords = array_filter($words, fn($w) => strlen($w) > 3 && !in_array($w, $stopWords));
        
        return implode(' ', array_slice($keywords, 0, 3));
    }
    
    /**
     * Get high-impact events statistics.
     */
    private function getHighImpactEventsStats(string $startDate, int $userId): array
    {
        $stats = [];
        
        foreach (self::HIGH_IMPACT_EVENTS as $type => $keywords) {
            $query = NewsItem::where('date', '>=', $startDate)
                ->where('created_by', $userId) // Filter by user
                ->whereIn('ea_status', ['safe', 'caution', 'danger'])
                ->where(function($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $q->orWhere('title', 'LIKE', '%' . $keyword . '%');
                    }
                });
            
            $events = $query->get();
            $total = $events->count();
            
            if ($total > 0) {
                $stats[$type] = [
                    'type' => $type,
                    'total' => $total,
                    'safe' => $events->where('ea_status', 'safe')->count(),
                    'caution' => $events->where('ea_status', 'caution')->count(),
                    'danger' => $events->where('ea_status', 'danger')->count(),
                    'safe_pct' => round(($events->where('ea_status', 'safe')->count() / $total) * 100, 1),
                    'caution_pct' => round(($events->where('ea_status', 'caution')->count() / $total) * 100, 1),
                    'danger_pct' => round(($events->where('ea_status', 'danger')->count() / $total) * 100, 1),
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * Get learning summary from statistics.
     */
    private function getLearningsSummary(array $stats): array
    {
        $mostDangerous = [];
        $mostSafe = [];
        
        foreach ($stats as $stat) {
            if ($stat['total'] >= 3) { // At least 3 occurrences
                if ($stat['danger_pct'] >= 60) {
                    $mostDangerous[] = [
                        'title' => $stat['title'],
                        'currency' => $stat['currency'],
                        'danger_pct' => $stat['danger_pct'],
                    ];
                }
                if ($stat['safe_pct'] >= 70) {
                    $mostSafe[] = [
                        'title' => $stat['title'],
                        'currency' => $stat['currency'],
                        'safe_pct' => $stat['safe_pct'],
                    ];
                }
            }
        }
        
        // Sort by percentage
        usort($mostDangerous, fn($a, $b) => $b['danger_pct'] <=> $a['danger_pct']);
        usort($mostSafe, fn($a, $b) => $b['safe_pct'] <=> $a['safe_pct']);
        
        return [
            'most_dangerous' => array_slice($mostDangerous, 0, 10),
            'most_safe' => array_slice($mostSafe, 0, 10),
            'advice' => 'Based on historical data, consider auto-marking similar events in the future.',
        ];
    }
    
    /**
     * Get EA recommendation based on news collection.
     */
    private function getEaRecommendation($news): array
    {
        $highImpact = $news->where('impact', 'high')->count();
        $danger = $news->where('ea_status', 'danger')->count();
        $shouldDisable = $news->where('should_disable_ea', true)->count();

        if ($danger > 0 || $shouldDisable > 0) {
            return [
                'status' => 'danger',
                'message' => 'High-risk day - Consider disabling EA during news events',
                'color' => 'red',
            ];
        }

        if ($highImpact > 0) {
            return [
                'status' => 'caution',
                'message' => 'Moderate risk - Monitor news events closely',
                'color' => 'yellow',
            ];
        }

        return [
            'status' => 'safe',
            'message' => 'Low risk day - EA can operate normally',
            'color' => 'green',
        ];
    }
}
