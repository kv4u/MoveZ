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
                            {--input= : Path to bundle JSON file}
                            {--output= : Path to output .cbz archive}
                            {--encrypt : Encrypt bundle.json inside the archive}
                            {--tool=unknown : Source tool name for manifest}';

    protected $description = 'Package a bundle JSON into a portable .cbz archive';

    public function handle(Packager $packager, Encryptor $encryptor): int
    {
        $inputPath  = $this->option('input');
        $outputPath = $this->option('output') ?? ('movez-' . date('Ymd-His') . '.cbz');
        $toolName   = $this->option('tool') ?? 'unknown';

        if (!$inputPath || !file_exists($inputPath)) {
            $this->error('Input file not found: ' . ($inputPath ?? '(not specified)'));
            return self::FAILURE;
        }

        $raw  = (string) file_get_contents($inputPath);
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $this->error('Input is not valid JSON');
            return self::FAILURE;
        }

        $sessions = collect($data)->map(fn(array $s) => SessionDTO::fromArray($s));

        $packager->pack($sessions, $outputPath, null, $toolName);

        $this->info("Packaged {$sessions->count()} session(s) into: {$outputPath}");
        return self::SUCCESS;
    }
}
