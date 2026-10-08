<?php
declare(strict_types=1);

namespace App\Commands;

use App\Services\Encryptor;
use App\Services\ToolDetector;
use LaravelZero\Framework\Commands\Command;

class DoctorCommand extends Command
{
    protected $signature = 'doctor';

    protected $description = 'Check that MoveZ dependencies and config are healthy';

    public function handle(ToolDetector $detector, Encryptor $encryptor): int
    {
        $this->info('MoveZ Doctor');
        $this->line('');

        $checks = [
            'PHP >= 8.2 (found ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'OpenSSL extension loaded'                  => extension_loaded('openssl'),
            'PDO SQLite driver loaded'                  => extension_loaded('pdo_sqlite'),
            'ZipArchive class available'                => class_exists('ZipArchive'),
        ];

        $keyPath  = $encryptor->keyPath();
        $keyDir   = dirname($keyPath);
        $checks['Encryption key directory accessible'] = is_dir($keyDir) ? is_writable($keyDir) : is_writable(dirname($keyDir));

        $passed = true;
        foreach ($checks as $label => $ok) {
            $this->checkResult($ok, $label);
            $passed = $passed && $ok;
        }

        $this->line('');
        $this->line($encryptor->hasKey()
            ? " Encryption key: {$keyPath}"
            : " Encryption key: not created yet (it is generated on first encrypted export or sync:push)");

        // Detected tools
        $this->line('');
        $detected = $detector->detect(includeUnsupported: true);
        $supported = array_values(array_filter($detected, fn(string $t) => $detector->isSupported($t)));
        $unsupported = array_values(array_diff($detected, $supported));

        if (!empty($supported)) {
            $this->info('Detected AI tools: ' . implode(', ', $supported));
        } else {
            $this->warn('No supported AI tools detected on this machine');
        }

        foreach ($unsupported as $tool) {
            $this->warn("Detected {$tool}, but it is not supported yet");
        }

        $this->line('');
        if ($passed) {
            $this->info('All checks passed!');
        } else {
            $this->error('Some checks failed. See above for details.');
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function checkResult(bool $ok, string $label): void
    {
        $icon = $ok ? '<fg=green>✔</>' : '<fg=red>✘</>';
        $this->line(" {$icon}  {$label}");
    }
}
