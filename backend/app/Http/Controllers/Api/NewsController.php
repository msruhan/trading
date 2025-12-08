<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use App\Services\ForexFactoryScraper;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(
        private ForexFactoryScraper $scraper
    ) {}

    /**
     * List news items with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = NewsItem::query();

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
        $news = NewsItem::forDate($date)
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
        $news = NewsItem::today()
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
        $days = $request->input('days', 7);

        $news = NewsItem::where('date', '>=', today())
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
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($request->date);
        
        $result = $this->scraper->syncForDate($date);

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
        $result = $this->scraper->syncWeek();

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

        $date = Carbon::parse($validated['date'])->format('Y-m-d');

        $query = NewsItem::forDate($date);

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
        $validated = $request->validate([
            'ea_status' => 'required|in:safe,caution,danger,unknown',
            'should_disable_ea' => 'boolean',
            'disable_minutes_before' => 'integer|min:0|max:120',
            'disable_minutes_after' => 'integer|min:0|max:120',
            'user_notes' => 'nullable|string|max:1000',
        ]);

        $newsItem->update([
            ...$validated,
            'marked_by' => $request->user()->id,
            'marked_at' => now(),
        ]);

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
        $validated = $request->validate([
            'news_ids' => 'required|array',
            'news_ids.*' => 'exists:news_items,id',
            'ea_status' => 'required|in:safe,caution,danger,unknown',
            'should_disable_ea' => 'boolean',
        ]);

        $updated = NewsItem::whereIn('id', $validated['news_ids'])
            ->update([
                'ea_status' => $validated['ea_status'],
                'should_disable_ea' => $validated['should_disable_ea'] ?? ($validated['ea_status'] === 'danger'),
                'marked_by' => $request->user()->id,
                'marked_at' => now(),
            ]);

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

        // Get today's news that could affect trading
        $dangerNews = NewsItem::today()
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
            'ea_status' => 'nullable|in:safe,caution,danger,unknown',
            'should_disable_ea' => 'boolean',
            'user_notes' => 'nullable|string',
        ]);

        $news = NewsItem::create([
            ...$validated,
            'is_manual' => true,
            'created_by' => $request->user()->id,
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
        return response()->json([
            'news' => $news,
        ]);
    }

    /**
     * Update a news item.
     */
    public function update(Request $request, NewsItem $news): JsonResponse
    {
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
            'ea_status' => 'nullable|in:safe,caution,danger,unknown',
            'should_disable_ea' => 'boolean',
            'disable_minutes_before' => 'integer|min:0|max:120',
            'disable_minutes_after' => 'integer|min:0|max:120',
            'user_notes' => 'nullable|string',
        ]);

        $news->update($validated);

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
        $news->delete();

        return response()->json([
            'message' => 'News item deleted successfully',
        ]);
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
