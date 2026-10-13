<?php
declare(strict_types=1);

namespace App\Commands\Key;

use App\Services\Encryptor;
use LaravelZero\Framework\Commands\Command;

class KeyFingerprintCommand extends Command
{
    protected $signature = 'key:fingerprint';

    protected $description = 'Show a non-secret fingerprint of the encryption key, to compare machines';

    public function handle(Encryptor $encryptor): int
    {
        $fingerprint = $encryptor->fingerprint();

        if ($fingerprint === null) {
            $this->warn("No encryption key at {$encryptor->keyPath()} yet. Create one with an encrypted export, or run `movez key:import`.");
            return self::FAILURE;
        }

        $this->line($fingerprint);
        return self::SUCCESS;
    }
}
