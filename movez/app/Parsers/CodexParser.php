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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Reads Codex CLI sessions.
 *
 * Supported layouts:
 *   - Current:  sessions/YYYY/MM/DD/rollout-<ts>-<id>.jsonl with
 *               {"type":"session_meta"|"response_item","payload":{...}} records
 *   - Older:    records are the response items themselves ({"type":"message","role":...})
 *   - Legacy:   flat {"role","content","timestamp"} lines (MoveZ ≤ 1.0 writer output)
 */
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
        return !empty($this->sessionFiles($this->getStoragePath($projectPath)));
    }

    /** @return Collection<int, SessionDTO> */
    public function parseMetadata(string $projectPath): Collection
    {
        return $this->parse($projectPath);
    }

    /** @return Collection<int, SessionDTO> */
    public function parse(string $projectPath): Collection
    {
        $sessions = collect();

        foreach ($this->sessionFiles($this->getStoragePath($projectPath)) as $file) {
            $session = $this->parseFile($file);
            if ($session !== null) {
                $sessions->push($session);
            }
        }

        return $sessions;
    }

    private function parseFile(string $file): ?SessionDTO
    {
        $lines = $this->readJsonlFile($file);
        if (empty($lines)) {
            return null;
        }

        $sessionId = $this->idFromFilename($file);
        $title     = null;
        $cwd       = null;
        $turns     = collect();

        foreach ($lines as $line) {
            $ts      = Carbon::parse(is_string($line['timestamp'] ?? null) ? $line['timestamp'] : '@' . filemtime($file));
            $type    = $line['type'] ?? null;
            $payload = is_array($line['payload'] ?? null) ? $line['payload'] : $line;

            if ($type === 'session_meta') {
                $sessionId = is_string($payload['id'] ?? null) ? $payload['id'] : $sessionId;
                $cwd       = is_string($payload['cwd'] ?? null) ? $payload['cwd'] : $cwd;
                $title     = is_string($payload['title'] ?? null) ? $payload['title'] : $title;
                continue;
            }

            // Old header line: {"id": ..., "timestamp": ..., "instructions": ...}
            if ($type === null && !isset($line['role']) && isset($line['id']) && is_string($line['id'])) {
                $sessionId = $line['id'];
                continue;
            }

            $role = $payload['role'] ?? null;
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            if (isset($payload['type']) && $payload['type'] !== 'message') {
                continue;
            }

            $content = trim(ContentFlattener::flatten($payload['content'] ?? ''));
            if ($content === '' || $this->isEnvironmentContext($content)) {
                continue;
            }

            if ($title === null && is_string($line['title'] ?? null)) {
                $title = $line['title'];
            }

            $turns->push(new TurnDTO(role: $role, content: $content, timestamp: $ts));
        }

        if ($turns->isEmpty()) {
            return null;
        }

        $firstUser = $turns->first(fn(TurnDTO $t) => $t->role === 'user');

        return new SessionDTO(
            id:               $sessionId,
            title:            mb_substr(trim($title ?? $firstUser?->content ?? basename($file)), 0, 80),
            sourceTool:       $this->toolName(),
            sourceMachineSha: $this->machineSha(),
            createdAt:        $turns->first()->timestamp,
            lastActiveAt:     $turns->last()->timestamp,
            turns:            $turns,
            project:          $cwd !== null ? basename(str_replace('\\', '/', rtrim($cwd, '/\\'))) : '',
        );
    }

    /** Codex injects environment/instructions blocks as user messages — not real turns. */
    private function isEnvironmentContext(string $content): bool
    {
        return str_starts_with($content, '<environment_context>')
            || str_starts_with($content, '<user_instructions>');
    }

    private function idFromFilename(string $file): string
    {
        $name = pathinfo($file, PATHINFO_FILENAME);

        // rollout-2025-05-07T17-24-21-<uuid> → <uuid>
        if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i', $name, $m)) {
            return $m[1];
        }

        return $name;
    }

    /** @return string[] */
    private function sessionFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $it    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'jsonl') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
