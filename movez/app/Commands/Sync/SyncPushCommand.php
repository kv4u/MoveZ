<?php
declare(strict_types=1);

namespace App\Commands\Sync;

use App\Services\Encryptor;
use App\Services\SyncClient;
use App\Services\ToolDetector;
use App\Support\ProjectFilter;
use LaravelZero\Framework\Commands\Command;

class SyncPushCommand extends Command
{
    use ResolvesSyncCredentials;

    protected $signature = 'sync:push
                            {--token= : API token (or MOVEZ_TOKEN env var, or ~/.movez/token)}
                            {--server= : Sync server base URL (or MOVEZ_SERVER_URL env var)}
                            {--tool=auto : Tool to push sessions from}
                            {--project= : Only push sessions belonging to this project path}';

    protected $description = 'Push encrypted sessions to the MoveZ sync server';

    public function handle(ToolDetector $detector, Encryptor $encryptor): int
    {
        $token    = $this->resolveToken();
        $baseUrl  = $this->resolveServer();
        $toolName = (string) $this->option('tool');
        $project  = $this->option('project');

        if ($this->missingCredentials($token, $baseUrl)) {
            return self::FAILURE;
        }

        if ($toolName === 'auto') {
            $detected = $detector->detect();
            if (empty($detected)) {
                $this->error('No supported AI coding tools detected');
                return self::FAILURE;
            }
            $toolName = $detected[0];
        }

        try {
            $sessions = $detector->getParser($toolName)->parse((string) ($project ?? getcwd()));
            $sessions = ProjectFilter::apply($sessions, $project);

            (new SyncClient((string) $baseUrl, $encryptor))->push($sessions, (string) $token);
        } catch (\Throwable $e) {
            $this->error('Push failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Pushed {$sessions->count()} session(s) from {$toolName} to {$baseUrl}");
        $this->line("Pulling on another machine needs the same key: run `movez key:export` here and `movez key:import` there (fingerprint {$encryptor->fingerprint()}).");
        return self::SUCCESS;
    }
}
