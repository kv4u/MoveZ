<?php
declare(strict_types=1);

namespace App\Commands;

use App\DTOs\SessionDTO;
use App\Services\Encryptor;
use App\Services\Packager;
use App\Services\PathMapper;
use App\Services\ToolDetector;
use LaravelZero\Framework\Commands\Command;

class ImportCommand extends Command
{
    protected $signature = 'import
                            {--input= : Path to the bundle file (.cbz or .json)}
                            {--tool= : Target tool (cursor|windsurf|claude-code|codex|copilot-cli)}
                            {--project= : Target project path}
                            {--encrypted : Input file is AES-256-GCM encrypted}
                            {--from-path= : Source project path (for path remapping)}
                            {--to-path= : Target project path (for path remapping)}';

    protected $description = 'Import AI coding sessions from a bundle file';

    public function handle(ToolDetector $detector, Packager $packager, Encryptor $encryptor, PathMapper $mapper): int
    {
        $inputPath   = $this->option('input');
        $toolName    = $this->option('tool');
        $projectPath = $this->option('project') ?? getcwd();
        $isEncrypted = (bool) $this->option('encrypted');
        $fromPath    = $this->option('from-path');
        $toPath      = $this->option('to-path');

        if (!$inputPath || !file_exists($inputPath)) {
            $this->error('Input file not found: ' . ($inputPath ?? '(not specified)'));
            return self::FAILURE;
        }

        if (!$toolName) {
            $this->error('--tool is required');
            return self::FAILURE;
        }

        // .cbz/.zip archives — use Packager::unpack()
        $ext = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        if (in_array($ext, ['cbz', 'zip'], true)) {
            try {
                $sessions = $packager->unpack($inputPath);
            } catch (\RuntimeException $e) {
                $this->error('Failed to unpack bundle: ' . $e->getMessage());
                return self::FAILURE;
            }
        } else {
            // Plain JSON export (possibly encrypted)
            $raw = (string) file_get_contents($inputPath);

            if ($isEncrypted) {
                try {
                    $raw = $encryptor->decrypt($raw);
                } catch (\RuntimeException $e) {
                    $this->error('Decryption failed: ' . $e->getMessage());
                    return self::FAILURE;
                }
            }

            $data = json_decode($raw, true);
            if (!is_array($data)) {
                $this->error('Invalid bundle JSON');
                return self::FAILURE;
            }

            $sessions = collect($data)->map(fn(array $s) => SessionDTO::fromArray($s));
        }

        if ($fromPath && $toPath) {
            $sessions = $mapper->remap($fromPath, $toPath, $sessions);
            $this->info("Remapped paths: {$fromPath} → {$toPath}");
        }

        try {
            $writer = $detector->getWriter($toolName);
            $writer->write($sessions, (string) $projectPath);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Imported {$sessions->count()} session(s) to {$toolName}");
        return self::SUCCESS;
    }
}
