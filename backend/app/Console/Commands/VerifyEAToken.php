<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

class VerifyEAToken extends Command
{
    protected $signature = 'ea:verify-token {account_id} {token}';
    protected $description = 'Verify EA token for an account';

    public function handle()
    {
        $accountId = $this->argument('account_id');
        $token = $this->argument('token');
        
        $account = Account::find($accountId);

        if (!$account) {
            $this->error("Account ID {$accountId} not found!");
            return 1;
        }

        $this->info("Account ID: {$account->id}");
        $this->info("Account Name: {$account->name}");
        $this->info("User ID: {$account->user_id}");
        
        $isValid = $account->verifyApiToken($token);
        
        if ($isValid) {
            $this->info("✅ Token is VALID for this account!");
        } else {
            $this->error("❌ Token is INVALID for this account!");
            $this->warn("Generate new token: php artisan account:generate-token {$accountId}");
        }

        return 0;
    }
}

