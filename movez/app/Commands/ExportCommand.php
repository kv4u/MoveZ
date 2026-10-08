<?php
declare(strict_types=1);

namespace App\Commands;

use App\Services\Encryptor;
use App\Services\Packager;
use App\Services\ToolDetector;
use App\Support\ProjectFilter;
use LaravelZero\Framework\Commands\Command;

class ExportCommand extends Command
{
    protected $signature = 'export
                            {--tool=auto : Tool to export from (cursor|claude-code|codex|copilot-cli|cline|continue|auto)}
                            {--output= : Output file path (.cbz bundle, or .json for a plain session list)}
                            {--project= : Only export sessions belonging to this project path}
                            {--encrypt : Encrypt the output with AES-256-GCM}';

    protected $description = 'Export AI coding sessions to a portable bundle';

    public function handle(ToolDetector $detector, Encryptor $encryptor, Packager $packager): int
    {
        $toolName   = (string) $this->option('tool');
        $outputPath = (string) ($this->option('output') ?: ('movez-export-' . date('Ymd-His') . '.cbz'));
        $project    = $this->option('project');
        $encrypt    = (bool) $this->option('encrypt');

        if ($toolName === 'auto') {
            $detected = $detector->detect();
            if (empty($detected)) {
                $this->error('No supported AI coding tools detected on this machine.');
                return self::FAILURE;
            }
            $toolName = $detected[0];
            $this->info("Auto-detected tool: {$toolName}");
        }

        try {
            $sessions = $detector->getParser($toolName)->parse((string) ($project ?? getcwd()));
            $sessions = ProjectFilter::apply($sessions, $project);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($sessions->isEmpty()) {
            $this->warn('No sessions found for the given tool and project.');
            return self::SUCCESS;
        }

        try {
            if (strtolower(pathinfo($outputPath, PATHINFO_EXTENSION)) === 'json') {
                $json = (string) json_encode(
                    $sessions->map(fn($s) => $s->toArray())->values()->all(),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                );

                if ($encrypt) {
                    $json       = $encryptor->encrypt($json);
                    $outputPath = (string) preg_replace('/\.json$/i', '.enc.json', $outputPath);
                }

                file_put_contents($outputPath, $json);
            } else {
                $packager->pack($sessions, $outputPath, null, $toolName, $encrypt ? $encryptor : null);
            }
        } catch (\Throwable $e) {
            $this->error('Export failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Exported {$sessions->count()} session(s) to: {$outputPath}");
        if ($encrypt) {
            $this->line("Encrypted with the key at {$encryptor->keyPath()} — copy it to any machine that needs to import this bundle.");
        }

        return self::SUCCESS;
    }
}
