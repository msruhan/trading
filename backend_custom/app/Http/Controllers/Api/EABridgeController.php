<?php

namespace App\Http\Controllers\Api;

use App\Events\TradesSynced;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Balance;
use App\Models\SyncLog;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ]);

        // Create sync log
        $syncLog = SyncLog::create([
            'account_id' => $account->id,
            'status' => 'processing',
            'type' => 'ea_bridge',
            'ip_address' => $request->ip(),
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

            foreach ($validated['trades'] ?? [] as $tradeData) {
                $trade = Trade::updateOrCreate(
                    [
                        'account_id' => $account->id,
                        'ticket' => $tradeData['ticket'],
                    ],
                    [
                        'pair' => strtoupper($tradeData['pair']),
                        'type' => $this->normalizeType($tradeData['type']),
                        'status' => empty($tradeData['close_time']) ? 'open' : 'closed',
                        'open_time' => $tradeData['open_time'],
                        'close_time' => $tradeData['close_time'] ?? null,
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

                if ($trade->wasRecentlyCreated) {
                    $newTrades++;
                } else {
                    $updatedTrades++;
                }

                // Calculate pips and duration for closed trades
                if ($trade->status === 'closed' && $trade->close_price) {
                    $trade->pips = $trade->calculatePips();
                    $trade->duration_minutes = $trade->calculateDuration();
                    $trade->save();
                }
            }

            // Update account status
            $account->update([
                'status' => 'active',
                'last_sync_at' => now(),
                'error_message' => null,
            ]);

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

            return response()->json([
                'success' => true,
                'message' => "Synced: {$newTrades} new, {$updatedTrades} updated trades",
                'new_trades' => $newTrades,
                'updated_trades' => $updatedTrades,
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
     * Normalize trade type from MT4/MT5 format.
     */
    protected function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return match($type) {
            'op_buy', '0' => 'buy',
            'op_sell', '1' => 'sell',
            'op_buylimit', '2' => 'buy_limit',
            'op_selllimit', '3' => 'sell_limit',
            'op_buystop', '4' => 'buy_stop',
            'op_sellstop', '5' => 'sell_stop',
            'buy limit' => 'buy_limit',
            'sell limit' => 'sell_limit',
            'buy stop' => 'buy_stop',
            'sell stop' => 'sell_stop',
            default => str_contains($type, 'buy') ? 'buy' : 'sell',
        };
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

        return response()->json([
            'status' => 'ok',
            'account_id' => $account->id,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}

