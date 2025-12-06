<?php

namespace App\Events;

use App\Models\Account;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradesSynced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Account $account,
        public int $newTrades = 0,
        public int $updatedTrades = 0
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->account->user_id),
            new PrivateChannel('account.' . $this->account->id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->account->load('latestBalance');

        return [
            'account_id' => $this->account->id,
            'account_name' => $this->account->name,
            'new_trades' => $this->newTrades,
            'updated_trades' => $this->updatedTrades,
            'last_sync_at' => $this->account->last_sync_at?->toIso8601String(),
            'balance' => $this->account->latestBalance?->balance,
            'equity' => $this->account->latestBalance?->equity,
            'floating_pl' => $this->account->latestBalance?->floating_pl,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'trades.synced';
    }
}

