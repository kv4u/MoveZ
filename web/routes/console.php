<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('movez:token {email : Email of an existing user}', function (string $email): int {
    $user = User::where('email', $email)->first();

    if ($user === null) {
        $this->error("No user with email {$email}");
        return 1;
    }

    $token = $user->issueApiToken();

    $this->info("New API token for {$email} (shown once — the server only stores its hash):");
    $this->line($token);
    $this->line('');
    $this->line('Use it with:  MOVEZ_TOKEN=<token> movez sync:push --server=' . config('app.url'));

    return 0;
})->purpose('Issue a new sync API token for a user (replaces any existing token)');
