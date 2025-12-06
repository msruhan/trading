<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Trade;
use App\Services\MT4StatementParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    /**
     * List trades with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $accountIds = $user->accounts()->pluck('id');

        $query = Trade::whereIn('account_id', $accountIds)
            ->with('account:id,name,broker_name');

        // Filter by account
        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        // Filter by pair
        if ($request->has('pair')) {
            $query->where('pair', strtoupper($request->pair));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('close_time', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('close_time', '<=', $request->end_date);
        }

        // Filter profitable/losing
        if ($request->has('profitable')) {
            $query->where('profit', $request->boolean('profitable') ? '>' : '<', 0);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'close_time');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $trades = $query->paginate($request->input('per_page', 20));

        return response()->json($trades);
    }

    /**
     * Get single trade details.
     */
    public function show(Request $request, Trade $trade): JsonResponse
    {
        $this->authorize('view', $trade);

        $trade->load(['account:id,name,broker_name', 'manualEntries']);

        return response()->json([
            'trade' => $trade,
        ]);
    }

    /**
     * Add manual trade.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'pair' => 'required|string|max:20',
            'type' => 'required|in:buy,sell,buy_limit,sell_limit,buy_stop,sell_stop',
            'status' => 'nullable|in:open,closed',
            'open_time' => 'required|date',
            'close_time' => 'nullable|date|after_or_equal:open_time',
            'open_price' => 'required|numeric|min:0',
            'close_price' => 'nullable|numeric|min:0',
            'stop_loss' => 'nullable|numeric|min:0',
            'take_profit' => 'nullable|numeric|min:0',
            'lots' => 'required|numeric|min:0.01',
            'profit' => 'nullable|numeric',
            'swap' => 'nullable|numeric',
            'commission' => 'nullable|numeric',
            'comment' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        // Verify account ownership
        $account = Account::findOrFail($validated['account_id']);
        $this->authorize('update', $account);

        // Generate unique ticket for manual trade
        $maxTicket = Trade::where('account_id', $validated['account_id'])
            ->where('is_manual', true)
            ->max('ticket');
        $ticket = max(900000000, ($maxTicket ?? 899999999) + 1);

        $trade = Trade::create([
            ...$validated,
            'ticket' => $ticket,
            'pair' => strtoupper($validated['pair']),
            'status' => $validated['status'] ?? ($validated['close_time'] ? 'closed' : 'open'),
            'is_manual' => true,
        ]);

        // Calculate pips and duration if closed
        if ($trade->close_time) {
            $trade->pips = $trade->calculatePips();
            $trade->duration_minutes = $trade->calculateDuration();
            $trade->save();
        }

        ActivityLog::logTrade('manual_create', $trade, 'Manual trade added');

        return response()->json([
            'trade' => $trade,
            'message' => 'Trade added successfully',
        ], 201);
    }

    /**
     * Update manual trade.
     */
    public function update(Request $request, Trade $trade): JsonResponse
    {
        $this->authorize('update', $trade);

        if (!$trade->is_manual) {
            return response()->json([
                'message' => 'Only manual trades can be edited',
            ], 403);
        }

        $validated = $request->validate([
            'pair' => 'sometimes|string|max:20',
            'type' => 'sometimes|in:buy,sell,buy_limit,sell_limit,buy_stop,sell_stop',
            'status' => 'sometimes|in:open,closed',
            'open_time' => 'sometimes|date',
            'close_time' => 'nullable|date',
            'open_price' => 'sometimes|numeric|min:0',
            'close_price' => 'nullable|numeric|min:0',
            'stop_loss' => 'nullable|numeric|min:0',
            'take_profit' => 'nullable|numeric|min:0',
            'lots' => 'sometimes|numeric|min:0.01',
            'profit' => 'nullable|numeric',
            'swap' => 'nullable|numeric',
            'commission' => 'nullable|numeric',
            'comment' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        if (isset($validated['pair'])) {
            $validated['pair'] = strtoupper($validated['pair']);
        }

        $trade->update($validated);

        // Recalculate pips and duration
        if ($trade->close_time) {
            $trade->pips = $trade->calculatePips();
            $trade->duration_minutes = $trade->calculateDuration();
            $trade->save();
        }

        ActivityLog::logTrade('manual_update', $trade, 'Manual trade updated');

        return response()->json([
            'trade' => $trade,
            'message' => 'Trade updated successfully',
        ]);
    }

    /**
     * Delete manual trade.
     */
    public function destroy(Request $request, Trade $trade): JsonResponse
    {
        $this->authorize('delete', $trade);

        if (!$trade->is_manual) {
            return response()->json([
                'message' => 'Only manual trades can be deleted',
            ], 403);
        }

        ActivityLog::logTrade('manual_delete', $trade, 'Manual trade deleted');

        $trade->delete();

        return response()->json([
            'message' => 'Trade deleted successfully',
        ]);
    }

    /**
     * Upload and parse statement file.
     */
    public function uploadStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'file' => 'required|file|mimes:csv,txt,html,htm|max:5120',
        ]);

        $account = Account::findOrFail($validated['account_id']);
        $this->authorize('update', $account);

        $file = $request->file('file');
        $content = file_get_contents($file->getPathname());
        $extension = strtolower($file->getClientOriginalExtension());

        $parser = new MT4StatementParser();

        if (in_array($extension, ['html', 'htm'])) {
            $trades = $parser->parseHTML($content);
        } else {
            $trades = $parser->parseCSV($content);
        }

        if (empty($trades)) {
            return response()->json([
                'message' => 'No trades found in the uploaded file',
            ], 422);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($trades as $tradeData) {
            // Check if trade already exists
            $exists = Trade::where('account_id', $account->id)
                ->where('ticket', $tradeData['ticket'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Trade::create([
                'account_id' => $account->id,
                ...$tradeData,
                'is_manual' => false,
            ]);

            $imported++;
        }

        ActivityLog::log('trade', 'import', "Imported {$imported} trades from statement", $account);

        return response()->json([
            'message' => "Successfully imported {$imported} trades. Skipped {$skipped} duplicates.",
            'imported' => $imported,
            'skipped' => $skipped,
            'total_in_file' => count($trades),
        ]);
    }

    /**
     * Get open trades.
     */
    public function openTrades(Request $request): JsonResponse
    {
        $user = $request->user();
        $accountIds = $user->accounts()->pluck('id');

        $trades = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'open')
            ->with('account:id,name,broker_name')
            ->orderBy('open_time', 'desc')
            ->get();

        return response()->json([
            'trades' => $trades,
        ]);
    }

    /**
     * Get trades summary/stats.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $accountIds = $user->accounts()->pluck('id');

        $query = Trade::whereIn('account_id', $accountIds)
            ->where('status', 'closed');

        // Apply date filter
        if ($request->has('start_date')) {
            $query->where('close_time', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('close_time', '<=', $request->end_date);
        }

        $trades = $query->get();

        $totalTrades = $trades->count();
        $winningTrades = $trades->where('profit', '>', 0)->count();
        $losingTrades = $trades->where('profit', '<', 0)->count();
        $breakeven = $trades->where('profit', 0)->count();

        $totalProfit = $trades->sum('profit');
        $grossProfit = $trades->where('profit', '>', 0)->sum('profit');
        $grossLoss = abs($trades->where('profit', '<', 0)->sum('profit'));

        $avgWin = $winningTrades > 0 ? $grossProfit / $winningTrades : 0;
        $avgLoss = $losingTrades > 0 ? $grossLoss / $losingTrades : 0;

        return response()->json([
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades,
            'losing_trades' => $losingTrades,
            'breakeven' => $breakeven,
            'winrate' => $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0,
            'total_profit' => round($totalProfit, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_loss' => round($grossLoss, 2),
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : 0,
            'average_win' => round($avgWin, 2),
            'average_loss' => round($avgLoss, 2),
            'expectancy' => $totalTrades > 0 ? round($totalProfit / $totalTrades, 2) : 0,
        ]);
    }
}

