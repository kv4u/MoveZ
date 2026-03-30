<?php
declare(strict_types=1);

namespace App\Commands\Sync;

use App\Services\Encryptor;
use App\Services\SyncClient;
use App\Services\ToolDetector;
use LaravelZero\Framework\Commands\Command;

class SyncPullCommand extends Command
{
    protected $signature = 'sync:pull
                            {--token= : API token (or read from ~/.movez/token)}
                            {--server= : Sync server base URL}
                            {--tool= : Target tool to write sessions into}
                            {--project= : Target project path}';

    protected $description = 'Pull sessions from the MoveZ sync server';

    public function handle(ToolDetector $detector, Encryptor $encryptor): int
    {
        $token       = $this->resolveToken();
        $baseUrl     = $this->option('server') ?? env('CB_SERVER_URL', '');
        $toolName    = $this->option('tool');
        $projectPath = $this->option('project') ?? getcwd();

        if (!$token) {
            $this->error('No token found. Use --token or store in ~/.movez/token');
            return self::FAILURE;
        }

        if (!$baseUrl) {
            $this->error('No server URL. Use --server or set CB_SERVER_URL env var');
            return self::FAILURE;
        }

        $client   = new SyncClient($baseUrl, $encryptor);
        $sessions = $client->pull($token);

        if ($toolName) {
            $writer = $detector->getWriter($toolName);
            $writer->write($sessions, (string) $projectPath);
            $this->info("Pulled and imported {$sessions->count()} session(s) into {$toolName}");
        } else {
            // Just output as JSON
            $this->line(json_encode(
                $sessions->map(fn($s) => $s->toArray())->all(),
                JSON_PRETTY_PRINT
            ));
        }

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
