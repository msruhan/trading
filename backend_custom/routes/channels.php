<?php

use App\Models\Account;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// User private channel
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Account private channel
Broadcast::channel('account.{accountId}', function ($user, $accountId) {
    $account = Account::find($accountId);
    return $account && (int) $user->id === (int) $account->user_id;
});

