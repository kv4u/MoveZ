<?php
declare(strict_types=1);

namespace App\Parsers;

use App\Contracts\ParserInterface;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Support\PlatformPaths;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CodexParser extends JsonlParser implements ParserInterface
{
    public function toolName(): string
    {
        return 'codex';
    }

    public function getStoragePath(string $projectPath): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config("movez.tools.codex.storage.{$os}", '');
        return PlatformPaths::expand((string) $cfg);
    }

    public function detect(string $projectPath): bool
    {
        $storagePath = $this->getStoragePath($projectPath);
        return is_dir($storagePath) && !empty(glob($storagePath . '/*.jsonl'));
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

        $files    = glob($storagePath . '/*.jsonl') ?: [];
        $sessions = collect();

        foreach ($files as $file) {
            $lines = $this->readJsonlFile($file);
            if (empty($lines)) {
                continue;
            }

            $sessionId = pathinfo($file, PATHINFO_FILENAME);
            $turns     = collect();
            $createdAt = null;
            $lastAt    = null;

            foreach ($lines as $line) {
                if (!is_array($line) || !isset($line['role'])) {
                    continue;
                }

                $ts = Carbon::parse($line['timestamp'] ?? 'now');
                if ($createdAt === null) {
                    $createdAt = $ts;
                }
                $lastAt = $ts;

                $turns->push(new TurnDTO(
                    role:      $line['role'],
                    content:   $line['content'] ?? '',
                    timestamp: $ts,
                ));
            }

            $sessions->push(new SessionDTO(
                id:               $sessionId,
                title:            $lines[0]['title'] ?? basename($file),
                sourceTool:       $this->toolName(),
                sourceMachineSha: substr(hash('sha256', (string) gethostname()), 0, 16),
                createdAt:        $createdAt ?? Carbon::now(),
                lastActiveAt:     $lastAt ?? Carbon::now(),
                turns:            $turns,
            ));
        }

        return $sessions;
    }
}
