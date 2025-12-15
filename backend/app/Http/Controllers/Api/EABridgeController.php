<?php

namespace App\Http\Controllers\Api;

use App\Events\TradesSynced;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Balance;
use App\Models\EaCommand;
use App\Models\SyncLog;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EABridgeController extends Controller
{
    /**
     * Receive trades from EA Bridge.
     * This endpoint is authenticated via HMAC token.
     */
    public function receiveTrades(Request $request): JsonResponse
    {
        // Validate HMAC signature
        $account = $this->authenticateRequest($request);
        
        if (!$account) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'balance' => 'required|array',
            'balance.balance' => 'required|numeric',
            'balance.equity' => 'required|numeric',
            'balance.margin' => 'nullable|numeric',
            'balance.free_margin' => 'nullable|numeric',
            'balance.margin_level' => 'nullable|numeric',
            'trades' => 'nullable|array',
            'trades.*.ticket' => 'required|integer',
            'trades.*.pair' => 'required|string',
            'trades.*.type' => 'required|string',
            'trades.*.open_time' => 'required|string',
            'trades.*.close_time' => 'nullable|string',
            'trades.*.open_price' => 'required|numeric',
            'trades.*.close_price' => 'nullable|numeric',
            'trades.*.lots' => 'required|numeric',
            'trades.*.profit' => 'nullable|numeric',
            'trades.*.swap' => 'nullable|numeric',
            'trades.*.commission' => 'nullable|numeric',
            'trades.*.stop_loss' => 'nullable|numeric',
            'trades.*.take_profit' => 'nullable|numeric',
            'trades.*.comment' => 'nullable|string',
            'trades.*.magic_number' => 'nullable|integer',
            'market_regime' => 'nullable|array',
            'market_regime.regime' => 'nullable|string|in:SIDEWAYS,BULLISH,BEARISH,UNKNOWN',
        ]);

        // Create sync log
        $syncLog = SyncLog::create([
            'account_id' => $account->id,
            'status' => 'processing',
            'source' => 'ea_bridge',
            'payload' => $validated,
        ]);
        $syncLog->start();

        try {
            DB::beginTransaction();

            // Update balance
            $openTradesCount = collect($validated['trades'] ?? [])
                ->filter(fn($t) => empty($t['close_time']))
                ->count();

            Balance::create([
                'account_id' => $account->id,
                'timestamp' => now(),
                'balance' => $validated['balance']['balance'],
                'equity' => $validated['balance']['equity'],
                'margin' => $validated['balance']['margin'] ?? 0,
                'free_margin' => $validated['balance']['free_margin'] ?? 0,
                'margin_level' => $validated['balance']['margin_level'] ?? null,
                'floating_pl' => $validated['balance']['equity'] - $validated['balance']['balance'],
                'open_trades_count' => $openTradesCount,
            ]);

            // Process trades
            $newTrades = 0;
            $updatedTrades = 0;
            $totalTradesReceived = count($validated['trades'] ?? []);
            
            // Track daily balance changes from closed trades
            $dailyBalances = [];

            Log::info('Processing trades from EA', [
                'account_id' => $account->id,
                'total_trades_received' => $totalTradesReceived,
                'trades_sample' => array_slice($validated['trades'] ?? [], 0, 3), // First 3 trades for debugging
            ]);

            foreach ($validated['trades'] ?? [] as $tradeData) {
                try {
                    // Parse and normalize datetime strings (MT4 format: YYYY-MM-DD HH:MM:SS)
                    $openTime = $this->parseDateTime($tradeData['open_time']);
                    $closeTime = !empty($tradeData['close_time']) ? $this->parseDateTime($tradeData['close_time']) : null;
                    
                $trade = Trade::updateOrCreate(
                    [
                        'account_id' => $account->id,
                        'ticket' => $tradeData['ticket'],
                    ],
                    [
                        'pair' => strtoupper($tradeData['pair']),
                        'type' => $this->normalizeType($tradeData['type']),
                        'status' => empty($tradeData['close_time']) ? 'open' : 'closed',
                            'open_time' => $openTime,
                            'close_time' => $closeTime,
                        'open_price' => $tradeData['open_price'],
                        'close_price' => $tradeData['close_price'] ?? null,
                        'lots' => $tradeData['lots'],
                        'profit' => $tradeData['profit'] ?? null,
                        'swap' => $tradeData['swap'] ?? 0,
                        'commission' => $tradeData['commission'] ?? 0,
                        'stop_loss' => $tradeData['stop_loss'] ?? null,
                        'take_profit' => $tradeData['take_profit'] ?? null,
                        'comment' => $tradeData['comment'] ?? null,
                        'magic_number' => $tradeData['magic_number'] ?? null,
                    ]
                );
                } catch (\Exception $e) {
                    Log::error('Failed to process trade', [
                        'account_id' => $account->id,
                        'trade_data' => $tradeData,
                        'error' => $e->getMessage(),
                    ]);
                    continue; // Skip this trade and continue with next
                }

                if ($trade->wasRecentlyCreated) {
                    $newTrades++;
                    Log::debug('New trade created', [
                        'account_id' => $account->id,
                        'ticket' => $trade->ticket,
                        'pair' => $trade->pair,
                    ]);
                } else {
                    $updatedTrades++;
                    Log::debug('Trade updated', [
                        'account_id' => $account->id,
                        'ticket' => $trade->ticket,
                        'pair' => $trade->pair,
                    ]);
                }

                // Calculate pips and duration for closed trades
                if ($trade->status === 'closed' && $trade->close_price) {
                    $trade->pips = $trade->calculatePips();
                    $trade->duration_minutes = $trade->calculateDuration();
                    $trade->save();
                    
                    // Track balance change for this trade's close date
                    if ($trade->close_time) {
                        $closeDate = $trade->close_time->format('Y-m-d');
                        if (!isset($dailyBalances[$closeDate])) {
                            $dailyBalances[$closeDate] = [
                                'date' => $closeDate,
                                'profit' => 0,
                                'trades_count' => 0,
                            ];
                        }
                        $dailyBalances[$closeDate]['profit'] += (float) ($trade->profit ?? 0);
                        $dailyBalances[$closeDate]['trades_count']++;
                    }
                }
            }
            
            // Create historical balance snapshots based on closed trades
            // This allows equity curve to show data from trade close dates, not just sync dates
            if (!empty($dailyBalances)) {
                // Get initial balance (from account or first balance record)
                $initialBalance = $account->initial_balance ?? 0;
                $firstBalance = $account->balances()->orderBy('timestamp')->first();
                if ($firstBalance) {
                    $initialBalance = (float) $firstBalance->balance;
                }
                
                // Sort dates chronologically
                ksort($dailyBalances);
                
                $runningBalance = $initialBalance;
                $runningEquity = $initialBalance;
                
                foreach ($dailyBalances as $date => $data) {
                    // Calculate balance at end of this day
                    $runningBalance += $data['profit'];
                    $runningEquity = $runningBalance; // For closed trades, equity = balance
                    
                    // Check if balance snapshot already exists for this date
                    $existingBalance = $account->balances()
                        ->whereDate('timestamp', $date)
                        ->first();
                    
                    if (!$existingBalance) {
                        // Create balance snapshot for this date
                        // Only create if date is in the past (not today) to avoid overwriting real-time sync data
                        $balanceDate = \Carbon\Carbon::parse($date);
                        if ($balanceDate->isPast() && !$balanceDate->isToday()) {
                            // Use end of day (23:59:59) to ensure it's before any real-time sync
                            // Real-time sync uses now() which will always be newer than 23:59:59 of past dates
                            // This ensures real-time sync data (with current timestamp) will always be latest
                            Balance::create([
                                'account_id' => $account->id,
                                'timestamp' => $balanceDate->copy()->endOfDay(),
                                'balance' => $runningBalance,
                                'equity' => $runningEquity,
                                'margin' => 0,
                                'free_margin' => $runningEquity,
                                'margin_level' => 0.00, // Use 0.00 instead of null (column doesn't allow null)
                                'floating_pl' => 0, // No open trades for historical data
                                'open_trades_count' => 0,
                            ]);
                        }
                    } else {
                        // Don't update existing balance if it's from a real sync
                        // Real-time sync has timestamp from now(), historical has endOfDay() timestamp
                        // Check if existing balance is historical (has endOfDay timestamp, not current timestamp)
                        $existingTimestamp = \Carbon\Carbon::parse($existingBalance->timestamp);
                        $isHistorical = $existingTimestamp->format('H:i:s') === '23:59:59' 
                            || $existingTimestamp->isPast();
                        
                        if ($isHistorical && $existingBalance->margin == 0 && $existingBalance->open_trades_count == 0) {
                            // This is a generated historical snapshot, safe to update
                            $existingBalance->update([
                                'balance' => $runningBalance,
                                'equity' => $runningEquity,
                            ]);
                        }
                        // Otherwise, preserve the real sync data (has current timestamp)
                    }
                }
            }

            // Update initial_balance if not set (first sync)
            if ($account->initial_balance <= 0) {
                // Calculate initial balance from current balance minus all closed trades profit
                $totalProfitFromTrades = $account->trades()
                    ->where('status', 'closed')
                    ->sum('profit');
                
                $calculatedInitialBalance = $validated['balance']['balance'] - (float) $totalProfitFromTrades;
                
                // Only update if calculated initial balance is reasonable (positive and not too different from current)
                if ($calculatedInitialBalance > 0 && $calculatedInitialBalance <= $validated['balance']['balance']) {
                    $account->initial_balance = $calculatedInitialBalance;
                } else {
                    // Fallback: use current balance as initial (assume no trades yet or first sync)
                    $account->initial_balance = $validated['balance']['balance'];
                }
            }
            
            // Update account status and market regime
            $updateData = [
                'status' => 'active',
                'last_sync_at' => now(),
                'error_message' => null,
            ];
            
            // Store market regime in meta field
            if (isset($validated['market_regime']['regime'])) {
                $meta = $account->meta ?? [];
                $meta['market_regime'] = [
                    'regime' => $validated['market_regime']['regime'],
                    'updated_at' => now()->toIso8601String(),
                ];
                $updateData['meta'] = $meta;
            }
            
            $account->update($updateData);

            DB::commit();

            $syncLog->succeed(
                "Synced successfully: {$newTrades} new, {$updatedTrades} updated",
                $newTrades,
                $updatedTrades
            );

            // Broadcast event for real-time updates
            event(new TradesSynced($account, $newTrades, $updatedTrades));

            ActivityLog::logSync('ea_sync_success', $account, 'EA Bridge sync completed', [
                'new_trades' => $newTrades,
                'updated_trades' => $updatedTrades,
            ]);

            // Get pending commands for this account (check if table exists first)
            $pendingCommands = [];
            try {
                if (Schema::hasTable('ea_commands')) {
                    $pendingCommands = $account->eaCommands()
                        ->pending()
                        ->orderBy('created_at', 'asc')
                        ->get()
                        ->map(function ($cmd) {
                            return [
                                'id' => $cmd->id,
                                'command' => $cmd->command,
                                'params' => $cmd->params,
                            ];
                        });
                }
            } catch (\Exception $e) {
                // Table doesn't exist or error - return empty array
                Log::warning('EA Commands table not found or error: ' . $e->getMessage());
            }

            // Check if sync was requested from web (sync_requested_at is set)
            $syncRequested = $account->sync_requested_at !== null;
            
            // Clear sync_requested_at after processing
            if ($syncRequested) {
                $account->update(['sync_requested_at' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => "Synced: {$newTrades} new, {$updatedTrades} updated trades",
                'new_trades' => $newTrades,
                'updated_trades' => $updatedTrades,
                'commands' => $pendingCommands, // Return pending commands for EA to execute
                'sync_requested' => $syncRequested, // Indicate if this sync was triggered from web
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            $syncLog->fail($e->getMessage());

            $account->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            ActivityLog::logSync('ea_sync_failed', $account, $e->getMessage());

            return response()->json([
                'error' => 'Sync failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Authenticate request using HMAC.
     */
    protected function authenticateRequest(Request $request): ?Account
    {
        $token = $request->header('X-API-Token');
        $accountId = $request->header('X-Account-ID');

        if (!$token || !$accountId) {
            return null;
        }

        $account = Account::find($accountId);

        if (!$account || !$account->verifyApiToken($token)) {
            return null;
        }

        return $account;
    }

    /**
     * Parse datetime string from MT4 format to Carbon instance.
     * Handles formats: "YYYY-MM-DD HH:MM:SS" or "YYYY.MM.DD HH:MM:SS"
     */
    protected function parseDateTime(?string $dateTime): ?\Carbon\Carbon
    {
        if (empty($dateTime)) {
            return null;
        }

        // Replace dots with dashes if needed (MT4 format: YYYY.MM.DD)
        $normalized = str_replace('.', '-', $dateTime);
        
        try {
            return \Carbon\Carbon::parse($normalized);
        } catch (\Exception $e) {
            Log::warning('Failed to parse datetime', [
                'original' => $dateTime,
                'normalized' => $normalized,
                'error' => $e->getMessage(),
            ]);
            // Try alternative parsing
            try {
                return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $normalized);
            } catch (\Exception $e2) {
                Log::error('Failed to parse datetime with alternative format', [
                    'normalized' => $normalized,
                    'error' => $e2->getMessage(),
                ]);
                return null;
            }
        }
    }

    /**
     * Normalize trade type from MT4/MT5 format.
     */
    protected function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        if (in_array($type, ['op_buy', '0', 'buy'])) {
            return 'buy';
        }
        if (in_array($type, ['op_sell', '1', 'sell'])) {
            return 'sell';
        }
        if (in_array($type, ['op_buylimit', '2', 'buy_limit', 'buy limit'])) {
            return 'buy_limit';
        }
        if (in_array($type, ['op_selllimit', '3', 'sell_limit', 'sell limit'])) {
            return 'sell_limit';
        }
        if (in_array($type, ['op_buystop', '4', 'buy_stop', 'buy stop'])) {
            return 'buy_stop';
        }
        if (in_array($type, ['op_sellstop', '5', 'sell_stop', 'sell stop'])) {
            return 'sell_stop';
        }

        return str_contains($type, 'buy') ? 'buy' : 'sell';
    }

    /**
     * Health check endpoint for EA Bridge.
     */
    public function healthCheck(Request $request): JsonResponse
    {
        $account = $this->authenticateRequest($request);

        if (!$account) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        // Check if sync is requested from web
        $syncRequested = $account->sync_requested_at !== null;

        return response()->json([
            'status' => 'ok',
            'account_id' => $account->id,
            'server_time' => now()->toIso8601String(),
            'sync_requested' => $syncRequested, // Indicate if sync was requested from web
        ]);
    }

    /**
     * Check if data has changed by comparing hash.
     */
    public function checkDataChanged(Request $request): JsonResponse
    {
        $account = $this->authenticateRequest($request);

        if (!$account) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'data_hash' => 'required|string',
        ]);

        // Calculate current data hash from database
        $currentBalance = $account->balances()
            ->orderBy('timestamp', 'desc')
            ->first();

        $openTrades = $account->trades()
            ->where('status', 'open')
            ->orderBy('open_time', 'desc')
            ->get();

        // Build hash string similar to EA
        $hashStr = '';
        if ($currentBalance) {
            $hashStr .= number_format($currentBalance->balance, 2, '.', '');
            $hashStr .= '|';
            $hashStr .= number_format($currentBalance->equity, 2, '.', '');
            $hashStr .= '|';
            $hashStr .= number_format($currentBalance->margin ?? 0, 2, '.', '');
            $hashStr .= '|';
        } else {
            $hashStr .= '0.00|0.00|0.00|';
        }

        $openTradesCount = $openTrades->count();
        $totalOpenProfit = $openTrades->sum('profit');
        $openTickets = $openTrades->map(function ($trade) {
            return $trade->ticket . ':' . number_format($trade->profit ?? 0, 2, '.', '');
        })->implode(',');

        $hashStr .= $openTradesCount;
        $hashStr .= '|';
        $hashStr .= number_format($totalOpenProfit, 2, '.', '');
        $hashStr .= '|';
        $hashStr .= $openTickets;

        // Check if account has no data (fresh start after clear data)
        $hasNoData = $account->trades()->count() == 0 && $currentBalance == null;
        
        // Compare hashes
        $noChanges = ($validated['data_hash'] === $hashStr);
        
        // Force sync if account has no data (after clear data) or if sync was requested
        $forceSync = $hasNoData || $account->sync_requested_at !== null;

        // Get pending commands even if no changes
        $pendingCommands = [];
        try {
            if (Schema::hasTable('ea_commands')) {
                $pendingCommands = $account->eaCommands()
                    ->pending()
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(function ($cmd) {
                        return [
                            'id' => $cmd->id,
                            'command' => $cmd->command,
                            'params' => $cmd->params,
                        ];
                    });
            }
        } catch (\Exception $e) {
            Log::warning('EA Commands table not found or error: ' . $e->getMessage());
        }

        // Check if sync was requested from web
        $syncRequested = $account->sync_requested_at !== null;

        return response()->json([
            'no_changes' => $noChanges && !$forceSync, // Don't skip if force sync
            'force_sync' => $forceSync, // Force sync flag
            'has_no_data' => $hasNoData, // Indicate if account has no data
            'commands' => $pendingCommands,
            'sync_requested' => $syncRequested,
        ]);
    }
}

