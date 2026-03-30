<?php
declare(strict_types=1);

namespace App\Commands\Sync;

use App\DTOs\SessionDTO;
use App\Services\Encryptor;
use App\Services\SyncClient;
use App\Services\ToolDetector;
use LaravelZero\Framework\Commands\Command;

class SyncPushCommand extends Command
{
    protected $signature = 'sync:push
                            {--token= : API token (or read from ~/.movez/token)}
                            {--server= : Sync server base URL}
                            {--tool=auto : Tool to push sessions from}
                            {--project= : Project path}';

    protected $description = 'Push sessions to the MoveZ sync server';

    public function handle(ToolDetector $detector, Encryptor $encryptor): int
    {
        $token   = $this->resolveToken();
        $baseUrl = $this->option('server') ?? env('CB_SERVER_URL', '');
        $toolName = $this->option('tool');
        $projectPath = $this->option('project') ?? getcwd();

        if (!$token) {
            $this->error('No token found. Use --token or store in ~/.movez/token');
            return self::FAILURE;
        }

        if (!$baseUrl) {
            $this->error('No server URL. Use --server or set CB_SERVER_URL env var');
            return self::FAILURE;
        }

        if ($toolName === 'auto') {
            $detected = $detector->detect();
            if (empty($detected)) {
                $this->error('No AI coding tools detected');
                return self::FAILURE;
            }
            $toolName = $detected[0];
        }

        $sessions = $detector->getParser($toolName)->parse((string) $projectPath);

        $client = new SyncClient($baseUrl, $encryptor);
        $client->push($sessions, $token);

        $this->info("Pushed {$sessions->count()} session(s) to {$baseUrl}");
        return self::SUCCESS;
    }

    private function resolveToken(): ?string
    {
        $token = $this->option('token');
        if ($token) {
            return $token;
        }

        $tokenPath = config('movez.token_path');
        if ($tokenPath && file_exists($tokenPath)) {
            return trim((string) file_get_contents($tokenPath));
        }

        return null;
    }
}
