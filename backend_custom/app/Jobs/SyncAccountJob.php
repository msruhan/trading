<?php

namespace App\Jobs;

use App\Events\TradesSynced;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\SyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 300;

    public function __construct(
        public Account $account,
        public string $type = 'auto'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $syncLog = SyncLog::create([
            'account_id' => $this->account->id,
            'status' => 'processing',
            'type' => $this->type,
        ]);
        $syncLog->start();

        try {
            $this->account->update(['status' => 'syncing']);

            // Note: In a real implementation, this would connect to MT4/MT5 server
            // using the investor credentials. For now, this is a placeholder that
            // demonstrates the sync flow. The actual sync happens via EA Bridge.
            
            // Simulate successful sync for demo purposes
            $syncLog->succeed('Sync completed via scheduled job', 0, 0);

            $this->account->update([
                'status' => 'active',
                'last_sync_at' => now(),
                'error_message' => null,
            ]);

            ActivityLog::logSync($this->type . '_sync_success', $this->account, 'Scheduled sync completed');

            // Broadcast update
            event(new TradesSynced($this->account, 0, 0));

        } catch (\Exception $e) {
            $syncLog->fail($e->getMessage());

            $this->account->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            ActivityLog::logSync($this->type . '_sync_failed', $this->account, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->account->update([
            'status' => 'error',
            'error_message' => 'Sync failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
        ]);
    }
}

