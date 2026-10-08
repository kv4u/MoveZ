<?php
declare(strict_types=1);

namespace App\Parsers;

use App\Contracts\ParserInterface;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Support\ContentFlattener;
use App\Support\PlatformPaths;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CopilotCliParser implements ParserInterface
{
    public function toolName(): string
    {
        return 'copilot-cli';
    }

    public function getStoragePath(string $projectPath): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config("movez.tools.copilot-cli.storage.{$os}", '');
        return PlatformPaths::expand((string) $cfg);
    }

    public function detect(string $projectPath): bool
    {
        $storagePath = $this->getStoragePath($projectPath);
        return is_dir($storagePath) && !empty(glob($storagePath . '/*.json'));
    }

    /** @return Collection<int, SessionDTO> */
    public function parseMetadata(string $projectPath): Collection
    {
        return $this->parse($projectPath);
    }

    public function parse(string $projectPath): Collection
    {
        $storagePath = $this->getStoragePath($projectPath);

        if (!is_dir($storagePath)) {
            return collect();
        }

        $files    = glob($storagePath . '/*.json') ?: [];
        $sessions = collect();

        foreach ($files as $file) {
            $raw  = file_get_contents($file);
            $data = json_decode((string) $raw, true);

            if (!is_array($data)) {
                continue; // skip unreadable files rather than aborting the whole parse
            }

            // Copilot-cli stores an array of sessions per file
            $records = isset($data[0]) ? $data : [$data];

            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $turns = collect($record['messages'] ?? [])
                    ->filter(fn($msg) => is_array($msg))
                    ->map(fn(array $msg) => new TurnDTO(
                        role:      ($msg['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                        content:   ContentFlattener::flatten($msg['content'] ?? ''),
                        timestamp: Carbon::parse($msg['timestamp'] ?? 'now'),
                    ))
                    ->values();

                $sessions->push(new SessionDTO(
                    id:               (string) ($record['id'] ?? pathinfo($file, PATHINFO_FILENAME)),
                    title:            (string) ($record['title'] ?? basename($file)),
                    sourceTool:       $this->toolName(),
                    sourceMachineSha: substr(hash('sha256', (string) gethostname()), 0, 16),
                    createdAt:        Carbon::parse($record['created_at'] ?? 'now'),
                    lastActiveAt:     Carbon::parse($record['updated_at'] ?? 'now'),
                    turns:            $turns,
                    project:          (string) ($record['project'] ?? ''),
                ));
            }
        }

        return $sessions;
    }
}
