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
                            {--tool= : Target tool (cursor|claude-code|codex|copilot-cli)}
                            {--project= : Target project path}
                            {--encrypted : Input .json file is AES-256-GCM encrypted (.cbz bundles are detected automatically)}
                            {--from-path= : Source project path (for path remapping)}
                            {--to-path= : Target project path (for path remapping)}';

    protected $description = 'Import AI coding sessions from a bundle file';

    public function handle(ToolDetector $detector, Packager $packager, Encryptor $encryptor, PathMapper $mapper): int
    {
        $inputPath   = $this->option('input');
        $toolName    = $this->option('tool');
        $projectPath = $this->option('project') ?? getcwd();
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

        try {
            $writer = $detector->getWriter($toolName);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        try {
            $ext = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
            $sessions = in_array($ext, ['cbz', 'zip'], true)
                ? $packager->unpack($inputPath, $encryptor)
                : $this->readJsonBundle($inputPath, $encryptor);
        } catch (\Throwable $e) {
            $this->error('Cannot read bundle: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($fromPath && $toPath) {
            $sessions = $mapper->remap($fromPath, $toPath, $sessions);
            $this->info("Remapped paths: {$fromPath} → {$toPath}");
        }

        try {
            $writer->write($sessions, (string) $projectPath);
        } catch (\Throwable $e) {
            $this->error('Import failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Imported {$sessions->count()} session(s) to {$toolName}");
        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, SessionDTO> */
    private function readJsonBundle(string $path, Encryptor $encryptor): \Illuminate\Support\Collection
    {
        $raw = (string) file_get_contents($path);

        if ($this->option('encrypted') || str_ends_with(strtolower($path), '.enc.json')) {
            $raw = $encryptor->decrypt($raw);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid bundle JSON');
        }

        // Accept both a plain session list and a full bundle object
        $list = array_key_exists('sessions', $data) && is_array($data['sessions']) ? $data['sessions'] : $data;

        return collect($list)->map(fn(array $s) => SessionDTO::fromArray($s));
    }
}
