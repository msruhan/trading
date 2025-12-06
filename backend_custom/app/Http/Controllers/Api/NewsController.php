<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    /**
     * List news items.
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

        $news = $query->orderBy('date', 'desc')
            ->orderBy('impact', 'desc')
            ->orderBy('time')
            ->paginate($request->input('per_page', 20));

        return response()->json($news);
    }

    /**
     * Get news for a specific date.
     */
    public function forDate(Request $request, string $date): JsonResponse
    {
        $news = NewsItem::forDate($date)
            ->orderBy('impact', 'desc')
            ->orderBy('time')
            ->get();

        return response()->json([
            'date' => $date,
            'news' => $news,
        ]);
    }

    /**
     * Get today's news.
     */
    public function today(Request $request): JsonResponse
    {
        $news = NewsItem::today()
            ->orderBy('impact', 'desc')
            ->orderBy('time')
            ->get();

        return response()->json([
            'date' => today()->format('Y-m-d'),
            'news' => $news,
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
            ->orderBy('impact', 'desc')
            ->orderBy('time')
            ->get()
            ->groupBy(fn($item) => $item->date->format('Y-m-d'));

        return response()->json([
            'news' => $news,
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
     * Update a news item.
     */
    public function update(Request $request, NewsItem $newsItem): JsonResponse
    {
        // Only allow editing manual entries or admin
        if (!$newsItem->is_manual && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Cannot edit non-manual news items',
            ], 403);
        }

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
        ]);

        $newsItem->update($validated);

        return response()->json([
            'news' => $newsItem,
            'message' => 'News item updated successfully',
        ]);
    }

    /**
     * Delete a news item.
     */
    public function destroy(Request $request, NewsItem $newsItem): JsonResponse
    {
        // Only allow deleting manual entries or admin
        if (!$newsItem->is_manual && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Cannot delete non-manual news items',
            ], 403);
        }

        $newsItem->delete();

        return response()->json([
            'message' => 'News item deleted successfully',
        ]);
    }
}

