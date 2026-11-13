<?php
declare(strict_types=1);

namespace App\Commands;

use App\Services\Encryptor;
use App\Services\Packager;
use App\Services\ProjectConfigFiles;
use App\Services\ToolDetector;
use App\Support\ProjectFilter;
use LaravelZero\Framework\Commands\Command;

class ExportCommand extends Command
{
    protected $signature = 'export
                            {--tool=auto : Tool to export from (cursor|claude-code|codex|copilot-cli|cline|continue|auto); comma-separate several to bundle them together}
                            {--id=* : Only export the session with this ID (repeat for several)}
                            {--output= : Output file path (.cbz bundle, or .json for a plain session list)}
                            {--project= : Only export sessions belonging to this project path}
                            {--encrypt : Encrypt the output with AES-256-GCM}
                            {--with-config : Also bundle CLAUDE.md, AGENTS.md, .cursorrules and .mcp.json from the project (.cbz only)}';

    protected $description = 'Export AI coding sessions to a portable bundle';

    public function handle(ToolDetector $detector, Encryptor $encryptor, Packager $packager, ProjectConfigFiles $configFiles): int
    {
        $toolName   = (string) $this->option('tool');
        $ids        = array_values(array_filter(array_map('strval', (array) $this->option('id')), fn(string $id): bool => $id !== ''));
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

        $tools = array_values(array_unique(array_filter(array_map('trim', explode(',', $toolName)))));

        try {
            $sessions = collect();
            foreach ($tools as $tool) {
                $sessions = $sessions->concat($detector->getParser($tool)->parse((string) ($project ?? getcwd())));
            }
            $sessions = ProjectFilter::apply($sessions->values(), $project);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($ids !== []) {
            $sessions = $sessions->filter(fn($s) => in_array($s->id, $ids, true))->values();

            $missing = array_diff($ids, $sessions->pluck('id')->all());
            if ($missing !== []) {
                $this->warn('Session(s) not found: ' . implode(', ', $missing));
            }
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
                $config = $this->option('with-config')
                    ? $configFiles->collect((string) ($project ?? getcwd()))
                    : null;

                if ($this->option('with-config')) {
                    $bundled = [];
                    foreach (ProjectConfigFiles::FILES as $field => $file) {
                        if ($config?->{$field} !== null) {
                            $bundled[] = $file;
                        }
                    }

                    $this->line($bundled === []
                        ? 'No project config files found to bundle.'
                        : 'Bundling project config: ' . implode(', ', $bundled));
                }

                $packager->pack($sessions, $outputPath, $config, implode(',', $tools), $encrypt ? $encryptor : null);
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
