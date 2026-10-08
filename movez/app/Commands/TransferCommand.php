<?php
declare(strict_types=1);

namespace App\Commands;

use App\Services\PathMapper;
use App\Services\ToolDetector;
use App\Support\ProjectFilter;
use LaravelZero\Framework\Commands\Command;

class TransferCommand extends Command
{
    protected $signature = 'transfer
                            {--from= : Source tool}
                            {--to= : Target tool}
                            {--project= : Project path (filters source sessions and is the target project)}
                            {--from-path= : Source project path for remapping}
                            {--to-path= : Target project path for remapping}';

    protected $description = 'Transfer sessions from one AI tool to another in one step';

    public function handle(ToolDetector $detector, PathMapper $mapper): int
    {
        $fromTool    = $this->option('from');
        $toTool      = $this->option('to');
        $project     = $this->option('project');
        $projectPath = (string) ($project ?? getcwd());
        $fromPath    = $this->option('from-path');
        $toPath      = $this->option('to-path');

        if (!$fromTool || !$toTool) {
            $this->error('Both --from and --to are required');
            return self::FAILURE;
        }

        try {
            $parser = $detector->getParser($fromTool);
            $writer = $detector->getWriter($toTool);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        try {
            $sessions = ProjectFilter::apply($parser->parse($projectPath), $project);

            if ($sessions->isEmpty()) {
                $this->warn("No sessions found in {$fromTool}");
                return self::SUCCESS;
            }

            if ($fromPath && $toPath) {
                $sessions = $mapper->remap($fromPath, $toPath, $sessions);
            }

            $writer->write($sessions, $projectPath);
        } catch (\Throwable $e) {
            $this->error('Transfer failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Transferred {$sessions->count()} session(s) from {$fromTool} to {$toTool}");
        return self::SUCCESS;
    }
}
