<?php
declare(strict_types=1);

namespace App\Commands;

use App\Services\ToolDetector;
use LaravelZero\Framework\Commands\Command;

class DoctorCommand extends Command
{
    protected $signature = 'doctor';

    protected $description = 'Check that MoveZ dependencies and config are healthy';

    public function handle(ToolDetector $detector): int
    {
        $this->info('MoveZ Doctor');
        $this->line('');

        $passed = true;

        // PHP version
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $phpOk      = PHP_MAJOR_VERSION >= 8 && PHP_MINOR_VERSION >= 2;
        $this->checkResult($phpOk, "PHP >= 8.2 (found {$phpVersion})");
        $passed = $passed && $phpOk;

        // OpenSSL extension
        $sslOk = extension_loaded('openssl');
        $this->checkResult($sslOk, 'OpenSSL extension loaded');
        $passed = $passed && $sslOk;

        // PDO SQLite
        $sqliteOk = extension_loaded('pdo_sqlite');
        $this->checkResult($sqliteOk, 'PDO SQLite driver loaded');
        $passed = $passed && $sqliteOk;

        // ZipArchive
        $zipOk = class_exists('ZipArchive');
        $this->checkResult($zipOk, 'ZipArchive class available');
        $passed = $passed && $zipOk;

        // Key path readable/writable
        $keyPath    = config('movez.key_path');
        $keyDirOk   = is_dir(dirname((string) $keyPath)) || is_writable(dirname(dirname((string) $keyPath)));
        $this->checkResult($keyDirOk, 'Encryption key directory accessible');
        $passed = $passed && $keyDirOk;

        // Detected tools
        $this->line('');
        $detected = $detector->detect();
        if (!empty($detected)) {
            $this->info('Detected AI tools: ' . implode(', ', $detected));
        } else {
            $this->warn('No AI tools detected on this machine');
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
