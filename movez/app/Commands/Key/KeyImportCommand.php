<?php
declare(strict_types=1);

namespace App\Commands\Key;

use App\Services\Encryptor;
use LaravelZero\Framework\Commands\Command;

class KeyImportCommand extends Command
{
    protected $signature = 'key:import
                            {--file= : Read the key from this file}
                            {--force : Replace an existing, different key}';

    protected $description = 'Install an encryption key exported with key:export on another machine';

    public function handle(Encryptor $encryptor): int
    {
        // Never accept the key as an argument: arguments are visible in process lists
        $file = $this->option('file');
        if (is_string($file) && $file !== '') {
            if (!is_readable($file)) {
                $this->error("Cannot read {$file}");
                return self::FAILURE;
            }
            $key = (string) file_get_contents($file);
        } elseif (!stream_isatty(STDIN)) {
            $key = (string) stream_get_contents(STDIN);   // piped: movez key:export | movez key:import
        } else {
            $key = (string) $this->secret('Paste the key printed by `movez key:export`');
        }

        try {
            $encryptor->importKey($key, (bool) $this->option('force'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Key installed at {$encryptor->keyPath()}");
        $this->line("Fingerprint {$encryptor->fingerprint()} — it should match `movez key:fingerprint` on the other machine.");

        return self::SUCCESS;
    }
}
