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

/**
 * Reads GitHub Copilot CLI sessions.
 *
 * Layout: ~/.copilot/session-state/<uuid>/
 *   events.jsonl   — one event per line: { type, data, id, timestamp, parentId }
 *   workspace.yaml — summary, created_at, cwd, repository
 *
 * Conversation turns are `user.message` / `assistant.message` events with the
 * text in data.content. The format is undocumented upstream, so parsing is
 * deliberately tolerant: unknown events are ignored and model.* side-channel
 * events (utility-model calls) are never treated as conversation.
 */
class CopilotCliParser extends JsonlParser implements ParserInterface
{
    public function toolName(): string
    {
        return 'copilot-cli';
    }

    public function getStoragePath(string $projectPath): string
    {
        $os = PlatformPaths::configKey();
        return PlatformPaths::expand((string) config("movez.tools.copilot-cli.storage.{$os}", ''));
    }

    public function detect(string $projectPath): bool
    {
        return $this->eventFiles($this->getStoragePath($projectPath)) !== [];
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

        foreach ($this->eventFiles($this->getStoragePath($projectPath)) as $file) {
            $session = $this->parseSession($file);
            if ($session !== null) {
                $sessions->push($session);
            }
        }

        return $sessions;
    }

    private function parseSession(string $eventsFile): ?SessionDTO
    {
        $dir       = dirname($eventsFile);
        $workspace = $this->readWorkspace($dir . DIRECTORY_SEPARATOR . 'workspace.yaml');
        $fallback  = Carbon::createFromTimestamp((int) filemtime($eventsFile));

        $sessionId = basename($dir);
        $cwd       = $workspace['cwd'] ?? null;
        $turns     = collect();

        foreach ($this->readJsonlFile($eventsFile) as $event) {
            $type = $event['type'] ?? null;
            $data = is_array($event['data'] ?? null) ? $event['data'] : [];

            if ($type === 'session.start') {
                $sessionId = is_string($data['sessionId'] ?? null) ? $data['sessionId'] : $sessionId;
                $cwd     ??= is_string($data['context']['cwd'] ?? null) ? $data['context']['cwd'] : null;
                continue;
            }

            $role = match ($type) {
                'user.message'      => 'user',
                'assistant.message' => 'assistant',
                default             => null,
            };

            if ($role === null) {
                continue;
            }

            // Assistant messages that only request tools have empty content
            $content = trim(ContentFlattener::flatten($data['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $turns->push(new TurnDTO(
                role:      $role,
                content:   $content,
                timestamp: $this->parseTimestamp($event['timestamp'] ?? null) ?? $fallback,
            ));
        }

        if ($turns->isEmpty()) {
            return null;
        }

        $title = trim((string) ($workspace['summary'] ?? ''));
        if ($title === '') {
            $title = $turns->first(fn(TurnDTO $t) => $t->role === 'user')?->content ?? $sessionId;
        }

        $created = $this->parseTimestamp($workspace['created_at'] ?? null) ?? $turns->first()->timestamp;

        return new SessionDTO(
            id:               $sessionId,
            title:            mb_substr($title, 0, 80),
            sourceTool:       $this->toolName(),
            sourceMachineSha: $this->machineSha(),
            createdAt:        $created,
            lastActiveAt:     $turns->last()->timestamp,
            turns:            $turns,
            project:          is_string($cwd) && $cwd !== '' ? basename(str_replace('\\', '/', rtrim($cwd, '/\\'))) : '',
        );
    }

    /**
     * Minimal reader for the flat `key: value` lines of workspace.yaml
     * (no YAML dependency in the CLI).
     *
     * @return array<string, string>
     */
    private function readWorkspace(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (!preg_match('/^([A-Za-z_]+):\s*(.*)$/', $line, $m) || trim($m[2]) === '') {
                continue;
            }

            $value = trim($m[2]);
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                // Double-quoted YAML scalars use JSON-compatible escapes (\" and \\)
                $decoded = json_decode($value);
                $value   = is_string($decoded) ? $decoded : trim($value, '"');
            } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = str_replace("''", "'", substr($value, 1, -1));
            }

            $values[$m[1]] = $value;
        }

        return $values;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return Carbon::createFromTimestampMs((int) $value);   // epoch milliseconds
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /** @return string[] */
    private function eventFiles(string $root): array
    {
        return is_dir($root) ? (glob($root . '/*/events.jsonl') ?: []) : [];
    }
}
