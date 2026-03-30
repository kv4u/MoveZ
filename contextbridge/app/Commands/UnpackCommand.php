<?php
declare(strict_types=1);

namespace App\Commands;

use App\Services\Packager;
use LaravelZero\Framework\Commands\Command;

class UnpackCommand extends Command
{
    protected $signature = 'unpack
                            {--input= : Path to .cbz archive}
                            {--output= : Output directory for extracted sessions}';

    protected $description = 'Unpack a .cbz archive into individual session JSON files';

    public function handle(Packager $packager): int
    {
        $inputPath  = $this->option('input');
        $outputPath = $this->option('output') ?? './movez-sessions';

        if (!$inputPath || !file_exists($inputPath)) {
            $this->error('Input archive not found: ' . ($inputPath ?? '(not specified)'));
            return self::FAILURE;
        }

        try {
            $sessions = $packager->unpack($inputPath);
        } catch (\RuntimeException $e) {
            $this->error('Unpack failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        foreach ($sessions as $session) {
            $file = $outputPath . DIRECTORY_SEPARATOR . $session->id . '.json';
            file_put_contents($file, json_encode($session->toArray(), JSON_PRETTY_PRINT));
        }

        $this->info("Unpacked {$sessions->count()} session(s) into: {$outputPath}");
        return self::SUCCESS;
    }
}
