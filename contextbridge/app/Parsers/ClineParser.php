<?php
declare(strict_types=1);

namespace App\Parsers;

use App\Contracts\ParserInterface;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Support\PlatformPaths;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class ClineParser implements ParserInterface
{
    public function toolName(): string
    {
        return 'cline';
    }

    public function getStoragePath(string $projectPath): string
    {
        $os      = PlatformPaths::configKey();
        $pattern = config("movez.tools.cline.storage.{$os}", '');
        $matches = PlatformPaths::globExpand((string) $pattern);
        return !empty($matches) ? $matches[0] : PlatformPaths::expand((string) $pattern);
    }

    public function detect(string $projectPath): bool
    {
        $os      = PlatformPaths::configKey();
        $pattern = config("movez.tools.cline.storage.{$os}", '');
        $matches = PlatformPaths::globExpand((string) $pattern);
        return !empty($matches);
    }

    /** @return Collection<int, SessionDTO> */
    public function parseMetadata(string $projectPath): Collection
    {
        return $this->parse($projectPath);
    }

    public function parse(string $projectPath): Collection
    {
        $os      = PlatformPaths::configKey();
        $pattern = config("movez.tools.cline.storage.{$os}", '');
        $dirs    = PlatformPaths::globExpand((string) $pattern);

        if (empty($dirs)) {
            return collect();
        }

        $sessions = collect();

        foreach ($dirs as $dir) {
            $files = glob($dir . '/*.json') ?: [];

            foreach ($files as $file) {
                $raw  = file_get_contents($file);
                $data = json_decode((string) $raw, true);

                if (!is_array($data)) {
                    continue;
                }

                $turns = collect($data['conversation'] ?? $data['messages'] ?? [])
                    ->map(fn(array $msg) => new TurnDTO(
                        role:      $msg['role'] ?? 'user',
                        content:   $msg['content'] ?? '',
                        timestamp: Carbon::parse($msg['timestamp'] ?? 'now'),
                    ));

                $sessions->push(new SessionDTO(
                    id:               $data['id'] ?? pathinfo($file, PATHINFO_FILENAME),
                    title:            $data['task'] ?? basename($file),
                    sourceTool:       $this->toolName(),
                    sourceMachineSha: substr(hash('sha256', (string) gethostname()), 0, 16),
                    createdAt:        Carbon::parse($data['ts'] ?? 'now'),
                    lastActiveAt:     Carbon::parse($data['ts'] ?? 'now'),
                    turns:            $turns,
                ));
            }
        }

        return $sessions;
    }
}
