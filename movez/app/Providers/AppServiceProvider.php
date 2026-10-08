<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Encryptor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void {}

    public function register(): void
    {
        $this->app->singleton(Encryptor::class, function () {
            return new Encryptor((string) config('movez.key_path'));
        });
    }
}
