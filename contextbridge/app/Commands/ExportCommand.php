<?php
declare(strict_types=1);

namespace App\Commands;

use App\Services\Encryptor;
use App\Services\ToolDetector;
use LaravelZero\Framework\Commands\Command;

class ExportCommand extends Command
{
    protected $signature = 'export
                            {--tool=auto : Tool to export from (cursor|windsurf|claude-code|codex|copilot-cli|cline|continue|auto)}
                            {--output= : Output file path}
                            {--project= : Project path to export from}
                            {--encrypt : Encrypt the output with AES-256-GCM}';

    protected $description = 'Export AI coding sessions to a portable bundle';

    public function handle(ToolDetector $detector, Encryptor $encryptor): int
    {
        $toolName   = $this->option('tool');
        $outputPath = $this->option('output') ?? ('movez-export-' . date('Ymd-His') . '.json');
        $projectPath = $this->option('project') ?? getcwd();
        $encrypt    = (bool) $this->option('encrypt');

        if ($toolName === 'auto') {
            $detected = $detector->detect();
            if (empty($detected)) {
                $this->error('No AI coding tools detected on this machine.');
                return self::FAILURE;
            }
            $toolName = $detected[0];
            $this->info("Auto-detected tool: {$toolName}");
        }

        try {
            $parser   = $detector->getParser($toolName);
            $sessions = $parser->parse((string) $projectPath);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($sessions->isEmpty()) {
            $this->warn('No sessions found for the given tool and project.');
            return self::SUCCESS;
        }

        $bundle = json_encode(
            $sessions->map(fn($s) => $s->toArray())->values()->all(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        if ($encrypt) {
            $bundle    = $encryptor->encrypt((string) $bundle);
            $outputPath = str_replace('.json', '.enc.json', $outputPath);
        }

        file_put_contents($outputPath, $bundle);

        $this->info("Exported {$sessions->count()} session(s) to: {$outputPath}");
        return self::SUCCESS;
    }
}
