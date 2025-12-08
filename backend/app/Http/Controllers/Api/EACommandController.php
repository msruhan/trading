<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\EaCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EACommandController extends Controller
{
    /**
     * Create a new EA command.
     */
    public function store(Request $request, Account $account): JsonResponse
    {
        // Authorize
        $this->authorize('update', $account);

        // Auto-create ea_commands table if it doesn't exist (for PHP version compatibility)
        try {
            if (!Schema::hasTable('ea_commands')) {
                DB::statement("
                    CREATE TABLE IF NOT EXISTS `ea_commands` (
                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                      `account_id` bigint(20) unsigned NOT NULL,
                      `command` varchar(255) NOT NULL COMMENT 'close_all, pause, resume, schedule',
                      `params` json DEFAULT NULL,
                      `status` enum('pending','executing','completed','failed') NOT NULL DEFAULT 'pending',
                      `result` text DEFAULT NULL,
                      `error_message` text DEFAULT NULL,
                      `executed_at` timestamp NULL DEFAULT NULL,
                      `created_at` timestamp NULL DEFAULT NULL,
                      `updated_at` timestamp NULL DEFAULT NULL,
                      PRIMARY KEY (`id`),
                      KEY `ea_commands_account_id_status_index` (`account_id`,`status`),
                      KEY `ea_commands_account_id_created_at_index` (`account_id`,`created_at`),
                      CONSTRAINT `ea_commands_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }
        } catch (\Exception $e) {
            // If table creation fails, log but continue (might already exist)
            \Log::warning('Failed to auto-create ea_commands table: ' . $e->getMessage());
        }

        $validated = $request->validate([
            'command' => 'required|string|in:close_all,pause,resume,schedule',
            'params' => 'nullable|array',
            'params.days' => 'nullable|array', // For schedule: [1,2,3,4,5] (Monday=1, Sunday=7)
            'params.start_time' => 'nullable|string', // For schedule: '10:00'
            'params.end_time' => 'nullable|string', // For schedule: '15:00'
            'params.magic_buy' => 'nullable|integer|min:0', // For close_all/schedule: magic number for BUY orders
            'params.magic_sell' => 'nullable|integer|min:0', // For close_all/schedule: magic number for SELL orders
            'params.intercept_all' => 'nullable|boolean', // Intercept ALL trading on this account (ignores magic_buy/magic_sell)
        ]);

        // Create command
        $command = EaCommand::create([
            'account_id' => $account->id,
            'command' => $validated['command'],
            'params' => $validated['params'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Command created successfully',
            'command' => $command,
        ], 201);
    }

    /**
     * Get pending commands for an account.
     */
    public function pending(Account $account): JsonResponse
    {
        // Authorize
        $this->authorize('view', $account);

        $commands = $account->eaCommands()
            ->pending()
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'commands' => $commands,
        ]);
    }

    /**
     * Get all commands for an account.
     */
    public function index(Account $account): JsonResponse
    {
        // Authorize
        $this->authorize('view', $account);

        $commands = $account->eaCommands()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($commands);
    }

    /**
     * Update command status (called by EA after execution).
     */
    public function updateStatus(Request $request, EaCommand $command): JsonResponse
    {
        // Verify this command belongs to the authenticated account
        $account = $this->authenticateRequest($request);
        
        if (!$account || $command->account_id !== $account->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:executing,completed,failed',
            'result' => 'nullable|string',
            'error_message' => 'nullable|string',
        ]);

        if ($validated['status'] === 'completed') {
            $command->markCompleted($validated['result'] ?? null);
        } elseif ($validated['status'] === 'failed') {
            $command->markFailed($validated['error_message'] ?? 'Unknown error');
        } else {
            $command->markExecuting();
        }

        return response()->json([
            'success' => true,
            'command' => $command->fresh(),
        ]);
    }

    /**
     * Authenticate request using HMAC token (same as EABridgeController).
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
}

