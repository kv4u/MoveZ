<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('movez:user {email : Login email} {--name= : Display name}', function (string $email): int {
    if (User::where('email', $email)->exists()) {
        $this->error("A user with email {$email} already exists");
        return 1;
    }

    $password = $this->secret('Password (min 12 characters)');
    $check    = Validator::make(['email' => $email, 'password' => $password], [
        'email'    => ['required', 'email'],
        'password' => ['required', 'string', 'min:12'],
    ]);

    if ($check->fails()) {
        foreach ($check->errors()->all() as $message) {
            $this->error($message);
        }
        return 1;
    }

    User::create([
        'name'     => $this->option('name') ?: strstr($email, '@', true),
        'email'    => $email,
        'password' => $password,   // hashed by the model's cast
    ]);

    $this->info("Created {$email}. Sign in at " . config('app.url') . '/login');
    $this->line("Issue a sync token with: php artisan movez:token {$email}");

    return 0;
})->purpose('Create a dashboard user (there is no public registration)');

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
