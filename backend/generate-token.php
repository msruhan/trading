<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Str;

$account = App\Models\Account::first();

if (!$account) {
    echo "No account found. Please create an account first.\n";
    exit(1);
}

// Generate a new raw token
$rawToken = Str::random(64);

// Hash and save
$account->api_token = hash('sha256', $rawToken);
$account->save();

echo "========================================\n";
echo "Token Generated Successfully!\n";
echo "========================================\n";
echo "Account ID: " . $account->id . "\n";
echo "Account Name: " . $account->name . "\n";
echo "Login: " . $account->login . "\n";
echo "========================================\n";
echo "\n";
echo "API_TOKEN (copy this to your EA):\n";
echo $rawToken . "\n";
echo "\n";
echo "========================================\n";
echo "API_URL for local:\n";
echo "http://localhost:8000/api/v1/incoming/trades\n";
echo "========================================\n";

