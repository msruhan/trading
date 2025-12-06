<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ManualEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManualEntryController extends Controller
{
    /**
     * List journal entries.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
            ->manualEntries()
            ->with(['account:id,name', 'trade:id,ticket,pair,profit']);

        // Filter by date
        if ($request->has('date')) {
            $query->forDate($request->date);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('entry_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('entry_date', '<=', $request->end_date);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        // Filter by account
        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        $entries = $query->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($entries);
    }

    /**
     * Get entries for a specific date.
     */
    public function forDate(Request $request, string $date): JsonResponse
    {
        $entries = $request->user()
            ->manualEntries()
            ->forDate($date)
            ->with(['account:id,name', 'trade:id,ticket,pair,profit'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'date' => $date,
            'entries' => $entries,
        ]);
    }

    /**
     * Get a single entry.
     */
    public function show(Request $request, ManualEntry $manualEntry): JsonResponse
    {
        $this->authorize('view', $manualEntry);

        $manualEntry->load(['account:id,name,broker_name', 'trade']);

        return response()->json([
            'entry' => $manualEntry,
        ]);
    }

    /**
     * Create a new journal entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'trade_id' => 'nullable|exists:trades,id',
            'entry_date' => 'required|date',
            'entry_type' => 'required|in:journal,note,analysis,lesson,strategy',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string',
            'mood' => 'nullable|in:confident,neutral,anxious,frustrated',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
            'is_public' => 'nullable|boolean',
        ]);

        // Verify account ownership if provided
        if (isset($validated['account_id'])) {
            $account = $request->user()->accounts()->find($validated['account_id']);
            if (!$account) {
                return response()->json([
                    'message' => 'Account not found or not owned by user',
                ], 404);
            }
        }

        // Verify trade ownership if provided
        if (isset($validated['trade_id'])) {
            $accountIds = $request->user()->accounts()->pluck('id');
            $trade = \App\Models\Trade::whereIn('account_id', $accountIds)
                ->find($validated['trade_id']);
            if (!$trade) {
                return response()->json([
                    'message' => 'Trade not found or not owned by user',
                ], 404);
            }
        }

        $entry = ManualEntry::create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        return response()->json([
            'entry' => $entry,
            'message' => 'Journal entry created successfully',
        ], 201);
    }

    /**
     * Update a journal entry.
     */
    public function update(Request $request, ManualEntry $manualEntry): JsonResponse
    {
        $this->authorize('update', $manualEntry);

        $validated = $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'trade_id' => 'nullable|exists:trades,id',
            'entry_date' => 'sometimes|date',
            'entry_type' => 'sometimes|in:journal,note,analysis,lesson,strategy',
            'title' => 'nullable|string|max:255',
            'content' => 'sometimes|string',
            'mood' => 'nullable|in:confident,neutral,anxious,frustrated',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
            'is_public' => 'nullable|boolean',
        ]);

        $manualEntry->update($validated);

        return response()->json([
            'entry' => $manualEntry,
            'message' => 'Journal entry updated successfully',
        ]);
    }

    /**
     * Delete a journal entry.
     */
    public function destroy(Request $request, ManualEntry $manualEntry): JsonResponse
    {
        $this->authorize('delete', $manualEntry);

        $manualEntry->delete();

        return response()->json([
            'message' => 'Journal entry deleted successfully',
        ]);
    }

    /**
     * Get all tags used by the user.
     */
    public function tags(Request $request): JsonResponse
    {
        $tags = $request->user()
            ->manualEntries()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->values();

        return response()->json([
            'tags' => $tags,
        ]);
    }
}

