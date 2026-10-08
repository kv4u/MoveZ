<?php
declare(strict_types=1);

namespace App\Commands;

use App\DTOs\SessionDTO;
use App\Parsers\ClaudeCodeParser;
use App\Services\ToolDetector;
use LaravelZero\Framework\Commands\Command;

class ShowCommand extends Command
{
    protected $signature = 'show
                            {--tool= : Tool the session belongs to}
                            {--id= : Session id (from list-sessions)}
                            {--project= : Project path (speeds up the lookup for some tools)}
                            {--json : Output the full session as JSON}';

    protected $description = 'Show a single session with all of its turns';

    public function handle(ToolDetector $detector): int
    {
        $toolName = (string) $this->option('tool');
        $id       = (string) $this->option('id');
        $project  = (string) ($this->option('project') ?? getcwd());

        if ($toolName === '' || $id === '') {
            $this->error('Both --tool and --id are required');
            return self::FAILURE;
        }

        try {
            $parser  = $detector->getParser($toolName);
            $session = $parser instanceof ClaudeCodeParser
                ? $parser->findById($project, $id)
                : $parser->parse($project)->first(fn(SessionDTO $s) => $s->id === $id);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($session === null) {
            $this->error("Session {$id} not found in {$toolName}");
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($session->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->info($session->title);
        $this->line("{$session->sourceTool} · {$session->project} · {$session->turnCount()} turn(s)");
        foreach ($session->turns as $turn) {
            $this->line('');
            $this->line("<fg=cyan>[{$turn->role}]</> " . $turn->content);
        }

        return self::SUCCESS;
    }
}
