<?php
declare(strict_types=1);

namespace App\Commands;

use App\Services\ToolDetector;
use LaravelZero\Framework\Commands\Command;

class ListCommand extends Command
{
    protected $signature = 'list-sessions
                            {--tool= : Filter by specific tool}
                            {--project= : Project path}
                            {--json : Output as JSON}';

    protected $description = 'List all AI coding sessions on this machine';

    public function handle(ToolDetector $detector): int
    {
        $filterTool  = $this->option('tool');
        $projectPath = $this->option('project') ?? getcwd();
        $asJson      = (bool) $this->option('json');

        $tools = $filterTool ? [$filterTool] : $detector->detect();

        if (empty($tools)) {
            if ($asJson) {
                $this->line('[]');
                return self::SUCCESS;
            }
            $this->warn('No AI coding tools detected on this machine.');
            return self::SUCCESS;
        }

        $allSessions = collect();

        foreach ($tools as $toolName) {
            try {
                $parser   = $detector->getParser($toolName);
                $sessions = $parser->parseMetadata((string) $projectPath);
                $allSessions = $allSessions->merge($sessions);
            } catch (\Throwable $e) {
                // In JSON mode write warnings to stderr so they don't corrupt JSON stdout
                if ($asJson) {
                    fwrite(STDERR, "Warning: Could not parse {$toolName}: " . $e->getMessage() . PHP_EOL);
                } else {
                    $this->warn("Could not parse {$toolName}: " . $e->getMessage());
                }
            }
        }

        if ($asJson) {
            $this->line(json_encode(
                $allSessions->map(fn($s) => $s->toArray())->values()->all(),
                JSON_PRETTY_PRINT
            ));
            return self::SUCCESS;
        }

        $rows = $allSessions->map(fn($s) => [
            $s->id,
            $s->title,
            $s->sourceTool,
            $s->lastActiveAt->toDateTimeString(),
            $s->turns->count(),
        ])->all();

        $this->table(
            ['ID', 'Title', 'Tool', 'Last Active', 'Turns'],
            $rows
        );

        return self::SUCCESS;
    }
}
