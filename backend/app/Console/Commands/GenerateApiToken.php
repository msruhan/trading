<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

class GenerateApiToken extends Command
{
    protected $signature = 'account:generate-token {account_id}';
    protected $description = 'Generate API token for an account';

    public function handle()
    {
        $accountId = $this->argument('account_id');
        $account = Account::find($accountId);

        if (!$account) {
            $this->error("Account ID {$accountId} not found!");
            return 1;
        }

        $token = $account->generateApiToken();

        $this->info("Account ID: {$account->id}");
        $this->info("Account Name: {$account->name}");
        $this->info("New API Token: {$token}");
        $this->warn("Save this token - it won't be shown again!");

        return 0;
    }
}

