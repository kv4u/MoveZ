<?php
declare(strict_types=1);

namespace App\Commands;

use App\DTOs\SessionDTO;
use App\Services\Encryptor;
use App\Services\Packager;
use LaravelZero\Framework\Commands\Command;

class PackageCommand extends Command
{
    protected $signature = 'package
                            {--input= : Path to a session list JSON file (from export --output=*.json)}
                            {--output= : Path to output .cbz archive}
                            {--encrypt : Encrypt bundle.json inside the archive}
                            {--tool=unknown : Source tool name for manifest}';

    protected $description = 'Package a session list JSON into a portable .cbz archive';

    public function handle(Packager $packager, Encryptor $encryptor): int
    {
        $inputPath  = $this->option('input');
        $outputPath = (string) ($this->option('output') ?: ('movez-' . date('Ymd-His') . '.cbz'));
        $toolName   = (string) ($this->option('tool') ?? 'unknown');
        $encrypt    = (bool) $this->option('encrypt');

        if (!$inputPath || !file_exists($inputPath)) {
            $this->error('Input file not found: ' . ($inputPath ?? '(not specified)'));
            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($inputPath), true);

        if (!is_array($data)) {
            $this->error('Input is not valid JSON');
            return self::FAILURE;
        }

        try {
            $sessions = collect($data)->map(fn(array $s) => SessionDTO::fromArray($s));
            $packager->pack($sessions, $outputPath, null, $toolName, $encrypt ? $encryptor : null);
        } catch (\Throwable $e) {
            $this->error('Packaging failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Packaged {$sessions->count()} session(s) into: {$outputPath}" . ($encrypt ? ' (encrypted)' : ''));
        return self::SUCCESS;
    }
}
