<?php
declare(strict_types=1);

namespace App\Commands\Sync;

use App\Commands\Concerns\WritesJson;
use App\Services\Encryptor;
use App\Services\PathMapper;
use App\Services\SyncClient;
use App\Services\ToolDetector;
use App\Commands\Concerns\ReportsProjectFallback;
use App\Support\ProjectTargets;
use LaravelZero\Framework\Commands\Command;

class SyncPullCommand extends Command
{
    use ReportsProjectFallback;

    use ResolvesSyncCredentials;
    use WritesJson;

    protected $signature = 'sync:pull
                            {--token= : API token (or MOVEZ_TOKEN env var, or ~/.movez/token)}
                            {--server= : Sync server base URL (or MOVEZ_SERVER_URL env var)}
                            {--tool= : Target tool to write sessions into (omit to print JSON)}
                            {--project= : Target project path}
                            {--from-path= : Source project path (for path remapping)}
                            {--to-path= : Target project path (for path remapping)}';

    protected $description = 'Pull and decrypt sessions from the MoveZ sync server';

    public function handle(ToolDetector $detector, Encryptor $encryptor, PathMapper $mapper): int
    {
        $token       = $this->resolveToken();
        $baseUrl     = $this->resolveServer();
        $toolName    = $this->option('tool');
        $project     = $this->option('project');
        $fromPath    = $this->option('from-path');
        $toPath      = $this->option('to-path');

        if ($this->missingCredentials($token, $baseUrl)) {
            return self::FAILURE;
        }

        try {
            $sessions = (new SyncClient((string) $baseUrl, $encryptor))->pull((string) $token);

            if ($fromPath && $toPath) {
                $sessions = $mapper->remap($fromPath, $toPath, $sessions);
            }

            if (!$toolName) {
                $this->writeJson($sessions->map(fn($s) => $s->toArray())->values()->all());
                return self::SUCCESS;
            }

            if ($sessions->isEmpty()) {
                $this->warn('The sync server has no sessions for this account yet.');
                return self::SUCCESS;
            }

            $result = ProjectTargets::write($detector->getWriter($toolName), $sessions, $project);
        } catch (\Throwable $e) {
            $this->error('Pull failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Pulled and imported {$sessions->count()} session(s) into {$toolName}");
        $this->reportFallback($result);
        return self::SUCCESS;
    }
}
