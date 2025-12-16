<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncAccountJob;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    /**
     * List all accounts for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()
            ->accounts()
            ->with(['latestBalance', 'latestSyncLog'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'accounts' => $accounts,
        ]);
    }

    /**
     * Create a new trading account.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'broker_name' => 'required|string|max:100',
            'server' => 'required|string|max:255',
            'login' => 'required|string|max:50',
            'investor_password' => 'required|string|max:100',
            'account_type' => 'nullable|in:demo,live',
            'platform' => 'nullable|in:mt4,mt5,ctrader,other',
            'currency' => 'nullable|string|max:10',
            'leverage' => 'nullable|string|max:20',
            'initial_balance' => 'nullable|numeric|min:0',
            'sync_interval' => 'nullable|integer|min:1|max:60',
            'is_auto_sync' => 'nullable|boolean',
        ]);

        $account = new Account([
            'user_id' => $request->user()->id,
            'name' => $validated['name'] ?? $validated['broker_name'] . ' - ' . substr($validated['login'], -4),
            'broker_name' => $validated['broker_name'],
            'server' => $validated['server'],
            'login' => $validated['login'],
            'login_masked' => Account::maskLogin($validated['login']),
            'account_type' => $validated['account_type'] ?? 'demo',
            'platform' => $validated['platform'] ?? 'mt4',
            'currency' => $validated['currency'] ?? 'USD',
            'leverage' => $validated['leverage'] ?? null,
            'initial_balance' => $validated['initial_balance'] ?? 0,
            'sync_interval' => $validated['sync_interval'] ?? 5,
            'is_auto_sync' => $validated['is_auto_sync'] ?? true,
            'status' => 'active',
        ]);

        // Encrypt and store investor password
        $account->setInvestorPassword($validated['investor_password']);
        
        // Generate API token for EA Bridge
        $apiToken = $account->generateApiToken();
        
        $account->save();

        ActivityLog::log('account', 'create', 'New trading account added', $account);

        return response()->json([
            'account' => $account,
            'api_token' => $apiToken, // Only shown once
            'message' => 'Account created successfully. Save the API token - it won\'t be shown again.',
        ], 201);
    }

    /**
     * Get account details.
     */
    public function show(Request $request, Account $account): JsonResponse
    {
        // Check if user owns the account
        if ($request->user()->id !== $account->user_id && !$request->user()->isAdmin()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to view this account.',
            ], 403);
        }
        
        $this->authorize('view', $account);

        $account->load(['latestBalance', 'syncLogs' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }]);

        $stats = $account->getStats();
        $dailyPnL = $account->getDailyPnL(30);
        $equityCurveRaw = $account->getEquityCurve(30);
        
        // Transform equity curve data to match frontend format
        // Frontend expects: [{ date: 'Y-m-d', equity: number, balance: number }]
        $equityCurve = array_map(function ($item) {
            // Handle both Carbon instance and string timestamp
            $timestamp = $item['timestamp'] ?? null;
            if ($timestamp instanceof \Carbon\Carbon) {
                $date = $timestamp->format('Y-m-d');
            } elseif (is_string($timestamp)) {
                $date = date('Y-m-d', strtotime($timestamp));
            } else {
                $date = now()->format('Y-m-d');
            }
            
            return [
                'date' => $date,
                'equity' => (float) ($item['equity'] ?? 0),
                'balance' => (float) ($item['balance'] ?? 0),
            ];
        }, $equityCurveRaw);
        
        // Get monthly P/L (last 12 months)
        $monthlyPnL = Trade::where('account_id', $account->id)
            ->where('status', 'closed')
            ->where('close_time', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw('YEAR(close_time) as year, MONTH(close_time) as month, 
                         SUM(profit) as profit, COUNT(*) as trades')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                $date = \Carbon\Carbon::create((int) $item->year, (int) $item->month, 1);
                return [
                    'year' => (int) $item->year,
                    'month' => (int) $item->month,
                    'label' => $date->format('M Y'),
                    'profit' => round((float) $item->profit, 2),
                    'trades' => (int) $item->trades,
                ];
            })
            ->toArray();

        // Get recent trades (last 50)
        $recentTrades = $account->trades()
            ->with('account:id,name,broker_name')
            ->orderByDesc('close_time')
            ->orderByDesc('open_time')
            ->limit(50)
            ->get();

        // Get balance from latest successful sync log if available (most accurate)
        // This ensures we use the exact balance/equity that was sent from EA
        $latestSyncLog = $account->syncLogs()
            ->where('status', 'success')
            ->whereNotNull('payload')
            ->orderBy('created_at', 'desc')
            ->first();
        
        // If we have balance from sync log, override latestBalance with it
        if ($latestSyncLog && isset($latestSyncLog->payload['balance'])) {
            $balanceFromSyncLog = [
                'balance' => (float) ($latestSyncLog->payload['balance']['balance'] ?? 0),
                'equity' => (float) ($latestSyncLog->payload['balance']['equity'] ?? 0),
                'margin' => (float) ($latestSyncLog->payload['balance']['margin'] ?? 0),
                'free_margin' => (float) ($latestSyncLog->payload['balance']['free_margin'] ?? 0),
                'margin_level' => (float) ($latestSyncLog->payload['balance']['margin_level'] ?? 0),
            ];
            
            // Update ALL balance snapshots for today with correct values from sync log
            // This ensures equity curve shows correct balance for today
            // Delete all balance snapshots for today and create a new one with correct values
            $today = now()->format('Y-m-d');
            $account->balances()
                ->whereDate('timestamp', $today)
                ->delete();
            
            // Create new balance snapshot for today with correct values from sync log
            \App\Models\Balance::create([
                'account_id' => $account->id,
                'timestamp' => now(),
                'balance' => $balanceFromSyncLog['balance'],
                'equity' => $balanceFromSyncLog['equity'],
                'margin' => $balanceFromSyncLog['margin'],
                'free_margin' => $balanceFromSyncLog['free_margin'],
                'margin_level' => $balanceFromSyncLog['margin_level'],
                'floating_pl' => $balanceFromSyncLog['equity'] - $balanceFromSyncLog['balance'],
                'open_trades_count' => 0,
            ]);
            
            // Override latestBalance with values from sync log (most accurate)
            if ($account->latestBalance) {
                $account->latestBalance->balance = $balanceFromSyncLog['balance'];
                $account->latestBalance->equity = $balanceFromSyncLog['equity'];
                $account->latestBalance->margin = $balanceFromSyncLog['margin'];
                $account->latestBalance->free_margin = $balanceFromSyncLog['free_margin'];
                $account->latestBalance->margin_level = $balanceFromSyncLog['margin_level'];
            } else {
                // Create a temporary balance object if latestBalance doesn't exist
                $account->setRelation('latestBalance', new \App\Models\Balance([
                    'balance' => $balanceFromSyncLog['balance'],
                    'equity' => $balanceFromSyncLog['equity'],
                    'margin' => $balanceFromSyncLog['margin'],
                    'free_margin' => $balanceFromSyncLog['free_margin'],
                    'margin_level' => $balanceFromSyncLog['margin_level'],
                ]));
            }
            
            // Reload equity curve to get updated balance for today
            $equityCurveRaw = $account->getEquityCurve(30);
            $equityCurve = array_map(function ($item) {
                $timestamp = $item['timestamp'] ?? null;
                if ($timestamp instanceof \Carbon\Carbon) {
                    $date = $timestamp->format('Y-m-d');
                } elseif (is_string($timestamp)) {
                    $date = date('Y-m-d', strtotime($timestamp));
                } else {
                    $date = now()->format('Y-m-d');
                }
                
                return [
                    'date' => $date,
                    'equity' => (float) ($item['equity'] ?? 0),
                    'balance' => (float) ($item['balance'] ?? 0),
                ];
            }, $equityCurveRaw);
        }

        // Get period stats and account info
        $periodStats = $account->getPeriodStats();
        $accountInfo = $account->getAccountInfo();

        return response()->json([
            'account' => $account,
            'stats' => $stats,
            'daily_pnl' => $dailyPnL,
            'equity_curve' => $equityCurve,
            'monthly_pnl' => $monthlyPnL,
            'recent_trades' => $recentTrades,
            'period_stats' => $periodStats,
            'account_info' => $accountInfo,
        ]);
    }
    
    /**
     * Get equity curve data for a specific month.
     */
    public function equityCurve(Request $request, Account $account): JsonResponse
    {
        $this->authorize('view', $account);
        
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        
        $data = $account->getEquityCurveForMonth((int) $year, (int) $month);
        
        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Update account settings.
     */
    public function update(Request $request, Account $account): JsonResponse
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'investor_password' => 'nullable|string|max:100',
            'sync_interval' => 'nullable|integer|min:1|max:60',
            'is_auto_sync' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive',
        ]);

        if (isset($validated['investor_password'])) {
            $account->setInvestorPassword($validated['investor_password']);
            unset($validated['investor_password']);
        }

        $account->update(array_filter($validated, fn($v) => $v !== null));

        ActivityLog::log('account', 'update', 'Account settings updated', $account);

        return response()->json([
            'account' => $account,
            'message' => 'Account updated successfully',
        ]);
    }

    /**
     * Delete an account.
     */
    public function destroy(Request $request, Account $account): JsonResponse
    {
        $this->authorize('delete', $account);

        ActivityLog::log('account', 'delete', 'Account deleted: ' . $account->name, $account);

        $account->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ]);
    }

    /**
     * Trigger manual sync for an account.
     */
    public function sync(Request $request, Account $account): JsonResponse
    {
        $this->authorize('update', $account);

        if ($account->status === 'syncing') {
            return response()->json([
                'message' => 'Sync already in progress',
            ], 409);
        }

        // Dispatch sync job
        SyncAccountJob::dispatch($account, 'manual');

        $account->update(['status' => 'syncing']);

        ActivityLog::logSync('manual_sync_triggered', $account, 'Manual sync initiated');

        return response()->json([
            'message' => 'Sync job queued successfully',
        ]);
    }

    /**
     * Get EA information from account meta and latest sync log.
     */
    public function getEAInfo(Request $request, Account $account): JsonResponse
    {
        // Authorize
        $this->authorize('view', $account);

        $meta = $account->meta ?? [];
        $marketRegime = $meta['market_regime'] ?? null;
        $eaInfoFromMeta = $meta['ea_info'] ?? null;
        
        // Get latest sync log for additional info
        $latestSyncLog = $account->syncLogs()
            ->where('status', 'success')
            ->orderBy('created_at', 'desc')
            ->first();

        // Count open trades
        $openTrades = $account->trades()
            ->whereNull('close_time')
            ->get();
        
        $openPositions = [
            'total' => $openTrades->count(),
            'intercepted_buy' => $openTrades->where('type', 'buy')->count(),
            'intercepted_sell' => $openTrades->where('type', 'sell')->count(),
        ];

        // Initialize EA info with defaults
        $eaInfo = [
            'magic_buy' => null,
            'magic_sell' => null,
            'trading_mode' => null,
            'intercept_all' => false,
            'is_paused' => false,
            'market_regime' => null,
            'adx_current' => null,
            'open_positions' => $openPositions,
            'last_sync_at' => $account->last_sync_at?->toIso8601String(),
        ];

        // Get EA info from meta (stored during sync)
        if ($eaInfoFromMeta) {
            $eaInfo['magic_buy'] = $eaInfoFromMeta['magic_buy'] ?? null;
            $eaInfo['magic_sell'] = $eaInfoFromMeta['magic_sell'] ?? null;
            $eaInfo['trading_mode'] = $eaInfoFromMeta['trading_mode'] ?? null;
            $eaInfo['intercept_all'] = $eaInfoFromMeta['intercept_all'] ?? false;
            $eaInfo['is_paused'] = $eaInfoFromMeta['is_paused'] ?? false;
            $eaInfo['adx_current'] = $eaInfoFromMeta['adx_current'] ?? null;
        }

        // Get market regime from meta or latest sync log
        if ($marketRegime) {
            $eaInfo['market_regime'] = [
                'regime' => $marketRegime['regime'] ?? 'UNKNOWN',
                'updated_at' => $marketRegime['updated_at'] ?? null,
            ];
        } elseif ($latestSyncLog && isset($latestSyncLog->payload['market_regime'])) {
            $eaInfo['market_regime'] = [
                'regime' => $latestSyncLog->payload['market_regime']['regime'] ?? 'UNKNOWN',
                'updated_at' => $latestSyncLog->created_at->toIso8601String(),
            ];
        }

        return response()->json([
            'ea_info' => $eaInfo,
        ]);
    }

    /**
     * Trigger EA sync now (for on-demand sync mode).
     */
    public function triggerSync(Request $request, Account $account): JsonResponse
    {
        $this->authorize('update', $account);

        try {
            // Check if column exists before updating
            $columns = DB::select("SHOW COLUMNS FROM `accounts` LIKE 'sync_requested_at'");
            
            if (empty($columns)) {
                // Column doesn't exist, create it
                DB::statement("ALTER TABLE `accounts` ADD COLUMN `sync_requested_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_sync_at`");
            }
            
            // Set sync_requested_at flag - EA will check this and sync when ready
            $account->update(['sync_requested_at' => now()]);

            ActivityLog::logSync('ea_sync_triggered', $account, 'EA sync triggered from web');

            return response()->json([
                'success' => true,
                'message' => 'Sync request sent to EA. The EA will sync on the next check.',
            ]);
        } catch (\Exception $e) {
            // If column doesn't exist and we can't create it, try to create it manually
            try {
                DB::statement("ALTER TABLE `accounts` ADD COLUMN `sync_requested_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_sync_at`");
                $account->refresh(); // Refresh to get the new column
                $account->update(['sync_requested_at' => now()]);
                
                ActivityLog::logSync('ea_sync_triggered', $account, 'EA sync triggered from web');
                
                return response()->json([
                    'success' => true,
                    'message' => 'Sync request sent to EA. The EA will sync on the next check. (Column was auto-created)',
                ]);
            } catch (\Exception $e2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to trigger sync. Please run the SQL migration manually.',
                    'sql_command' => 'ALTER TABLE `accounts` ADD COLUMN `sync_requested_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_sync_at`',
                    'error' => $e2->getMessage(),
                    'instructions' => 'Open phpMyAdmin, select your database, go to SQL tab, and run the SQL command above.',
                ], 500);
            }
        }
    }

    /**
     * Regenerate API token for EA Bridge.
     */
    public function regenerateToken(Request $request, Account $account): JsonResponse
    {
        $this->authorize('update', $account);

        $newToken = $account->generateApiToken();

        ActivityLog::log('account', 'token_regenerated', 'API token regenerated', $account);

        return response()->json([
            'api_token' => $newToken,
            'message' => 'API token regenerated. Save it - it won\'t be shown again.',
        ]);
    }

    /**
     * Get sync logs for an account.
     */
    public function syncLogs(Request $request, Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        $logs = $account->syncLogs()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($logs);
    }

    /**
     * Clear all data for an account (trades, balances, sync logs).
     * This allows user to start fresh and sync again.
     */
    public function clearData(Request $request, Account $account): JsonResponse
    {
        $this->authorize('update', $account);

        try {
            \DB::beginTransaction();

            // Delete all trades
            $tradesCount = $account->trades()->count();
            $account->trades()->delete();

            // Delete all balance snapshots
            $balancesCount = $account->balances()->count();
            $account->balances()->delete();

            // Delete all sync logs
            $syncLogsCount = $account->syncLogs()->count();
            $account->syncLogs()->delete();

            // Reset account status
            $account->update([
                'status' => 'active',
                'last_sync_at' => null,
                'error_message' => null,
            ]);

            \DB::commit();

            ActivityLog::log('account', 'clear_data', "Cleared all account data: {$tradesCount} trades, {$balancesCount} balances, {$syncLogsCount} sync logs", $account);

            return response()->json([
                'message' => 'All account data cleared successfully',
                'deleted' => [
                    'trades' => $tradesCount,
                    'balances' => $balancesCount,
                    'sync_logs' => $syncLogsCount,
                ],
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();

            ActivityLog::log('account', 'clear_data_failed', 'Failed to clear account data: ' . $e->getMessage(), $account);

            return response()->json([
                'error' => 'Failed to clear data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

