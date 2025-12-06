<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use App\Services\EncryptionService;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'demo@tradingjournal.local')->first();
        
        if (!$user) {
            $this->command->error('Demo user not found. Run UserSeeder first.');
            return;
        }

        $accounts = [
            [
                'name' => 'IC Markets Demo',
                'broker_name' => 'IC Markets',
                'server' => 'ICMarkets-Demo',
                'login' => '12345678',
                'account_type' => 'demo',
                'platform' => 'mt4',
                'currency' => 'USD',
                'leverage' => '1:500',
                'initial_balance' => 10000.00,
            ],
            [
                'name' => 'XM Live Account',
                'broker_name' => 'XM',
                'server' => 'XMGlobal-Real3',
                'login' => '87654321',
                'account_type' => 'live',
                'platform' => 'mt5',
                'currency' => 'USD',
                'leverage' => '1:200',
                'initial_balance' => 5000.00,
            ],
            [
                'name' => 'FBS Cent Account',
                'broker_name' => 'FBS',
                'server' => 'FBS-Real',
                'login' => '55667788',
                'account_type' => 'live',
                'platform' => 'mt4',
                'currency' => 'USD',
                'leverage' => '1:1000',
                'initial_balance' => 1000.00,
            ],
        ];

        foreach ($accounts as $accountData) {
            $account = new Account([
                'user_id' => $user->id,
                'name' => $accountData['name'],
                'broker_name' => $accountData['broker_name'],
                'server' => $accountData['server'],
                'login' => $accountData['login'],
                'login_masked' => Account::maskLogin($accountData['login']),
                'account_type' => $accountData['account_type'],
                'platform' => $accountData['platform'],
                'currency' => $accountData['currency'],
                'leverage' => $accountData['leverage'],
                'initial_balance' => $accountData['initial_balance'],
                'status' => 'active',
                'sync_interval' => 5,
                'is_auto_sync' => true,
                'last_sync_at' => now()->subMinutes(rand(1, 30)),
            ]);

            // Set encrypted password (demo password)
            $account->setInvestorPassword('investor_' . $accountData['login']);
            
            // Generate API token
            $account->api_token = hash('sha256', 'demo_token_' . $accountData['login']);
            
            $account->save();
        }

        $this->command->info('Accounts seeded successfully!');
    }
}

