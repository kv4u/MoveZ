<?php
declare(strict_types=1);

namespace App\Parsers;

use App\Contracts\ParserInterface;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Support\PlatformPaths;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PDO;

class ContinueParser extends SqliteParser implements ParserInterface
{
    public function toolName(): string
    {
        return 'continue';
    }

    public function getStoragePath(string $projectPath): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config("movez.tools.continue.storage.{$os}", '');
        return PlatformPaths::expand((string) $cfg);
    }

    public function detect(string $projectPath): bool
    {
        $dbPath = $this->getDbPath($projectPath);
        return file_exists($dbPath);
    }

    /** @return Collection<int, SessionDTO> */
    public function parseMetadata(string $projectPath): Collection
    {
        return $this->parse($projectPath);
    }

    public function parse(string $projectPath): Collection
    {
        $dbPath = $this->getDbPath($projectPath);

        if (!file_exists($dbPath)) {
            return collect();
        }

        $pdo  = $this->openDb($dbPath);
        $stmt = $pdo->query('SELECT * FROM sessions ORDER BY created_at ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return collect($rows)->map(function (array $row) use ($pdo) {
            $turnsStmt = $pdo->prepare(
                'SELECT * FROM messages WHERE session_id = ? ORDER BY created_at ASC'
            );
            $turnsStmt->execute([$row['id']]);
            $turnRows = $turnsStmt->fetchAll(PDO::FETCH_ASSOC);

            $turns = collect($turnRows)->map(
                fn(array $t) => new TurnDTO(
                    role:      $t['role'] ?? 'user',
                    content:   $t['content'] ?? '',
                    timestamp: Carbon::parse($t['created_at'] ?? 'now'),
                )
            );

            return new SessionDTO(
                id:               (string) $row['id'],
                title:            $row['title'] ?? 'Untitled',
                sourceTool:       $this->toolName(),
                sourceMachineSha: substr(hash('sha256', (string) gethostname()), 0, 16),
                createdAt:        Carbon::parse($row['created_at'] ?? 'now'),
                lastActiveAt:     Carbon::parse($row['updated_at'] ?? $row['created_at'] ?? 'now'),
                turns:            $turns,
            );
        });
    }

    private function getDbPath(string $projectPath): string
    {
        $storagePath = $this->getStoragePath($projectPath);
        $dbFile      = config('movez.tools.continue.db_file', 'sessions.db');
        return $storagePath . DIRECTORY_SEPARATOR . $dbFile;
    }
}
