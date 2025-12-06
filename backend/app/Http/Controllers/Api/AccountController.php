<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncAccountJob;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $equityCurve = $account->getEquityCurve(30);
        
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

        return response()->json([
            'account' => $account,
            'stats' => $stats,
            'daily_pnl' => $dailyPnL,
            'equity_curve' => $equityCurve,
            'monthly_pnl' => $monthlyPnL,
            'recent_trades' => $recentTrades,
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
}

