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
 * Reads Continue (continue.dev) chat sessions.
 *
 * Layout (core/util/paths.ts in continuedev/continue):
 *   ~/.continue/sessions/<sessionId>.json  — { sessionId, title, workspaceDirectory, history[] }
 *   ~/.continue/sessions/sessions.json     — [{ sessionId, title, dateCreated, workspaceDirectory }]
 * Each history item is { message: { role, content }, contextItems, ... } where content
 * is a string or an array of { type: "text", text } parts.
 */
class ContinueParser implements ParserInterface
{
    public function toolName(): string
    {
        return 'continue';
    }

    public function getStoragePath(string $projectPath): string
    {
        // Continue honours CONTINUE_GLOBAL_DIR; mirror that so we read the same folder
        $globalDir = getenv('CONTINUE_GLOBAL_DIR');
        if (is_string($globalDir) && $globalDir !== '') {
            return rtrim($globalDir, '/\\') . DIRECTORY_SEPARATOR . 'sessions';
        }

        $os = PlatformPaths::configKey();
        return PlatformPaths::expand((string) config("movez.tools.continue.storage.{$os}", ''));
    }

    public function detect(string $projectPath): bool
    {
        return $this->sessionFiles($this->getStoragePath($projectPath)) !== [];
    }

    /** @return Collection<int, SessionDTO> */
    public function parseMetadata(string $projectPath): Collection
    {
        return $this->parse($projectPath);
    }

    /** @return Collection<int, SessionDTO> */
    public function parse(string $projectPath): Collection
    {
        $dir      = $this->getStoragePath($projectPath);
        $index    = $this->readIndex($dir);
        $sessions = collect();

        foreach ($this->sessionFiles($dir) as $file) {
            $session = $this->parseFile($file, $index);
            if ($session !== null) {
                $sessions->push($session);
            }
        }

        return $sessions;
    }

    /** @param array<string, array<string, mixed>> $index */
    private function parseFile(string $file, array $index): ?SessionDTO
    {
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !is_array($data['history'] ?? null)) {
            return null;
        }

        $id    = is_string($data['sessionId'] ?? null) ? $data['sessionId'] : pathinfo($file, PATHINFO_FILENAME);
        $mtime = Carbon::createFromTimestamp((int) filemtime($file));
        $meta  = $index[$id] ?? [];

        // Messages carry no timestamps; the index has the creation date
        $created = $this->parseDate($meta['dateCreated'] ?? null) ?? $mtime;

        $turns = collect($data['history'])
            ->map(fn($item) => is_array($item) ? ($item['message'] ?? null) : null)
            ->filter(fn($msg) => is_array($msg) && in_array($msg['role'] ?? null, ['user', 'assistant'], true))
            ->map(fn(array $msg) => new TurnDTO(
                role:      $msg['role'],
                content:   trim(ContentFlattener::flatten($msg['content'] ?? '')),
                timestamp: $created,
            ))
            ->filter(fn(TurnDTO $t) => $t->content !== '')
            ->values();

        if ($turns->isEmpty()) {
            return null;
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' || $title === 'New Session') {
            $title = mb_substr($turns->first()->content, 0, 80);
        }

        return new SessionDTO(
            id:               $id,
            title:            $title,
            sourceTool:       $this->toolName(),
            sourceMachineSha: substr(hash('sha256', (string) gethostname()), 0, 16),
            createdAt:        $created,
            lastActiveAt:     $mtime->greaterThan($created) ? $mtime : $created,
            turns:            $turns,
            project:          $this->projectName((string) ($data['workspaceDirectory'] ?? $meta['workspaceDirectory'] ?? '')),
        );
    }

    /** workspaceDirectory is a path or a file:// URI (e.g. file:///c%3A/Work/app). */
    private function projectName(string $workspace): string
    {
        if ($workspace === '') {
            return '';
        }

        $path = rawurldecode((string) preg_replace('#^file://#', '', $workspace));

        return basename(str_replace('\\', '/', rtrim($path, '/\\')));
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (is_numeric($value)) {
            // Continue stores Date.now() — milliseconds since the epoch
            return Carbon::createFromTimestampMs((int) $value);
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

    /** @return array<string, array<string, mixed>> sessionId => metadata */
    private function readIndex(string $dir): array
    {
        $list = json_decode((string) @file_get_contents($dir . DIRECTORY_SEPARATOR . 'sessions.json'), true);
        if (!is_array($list)) {
            return [];
        }

        $index = [];
        foreach ($list as $entry) {
            if (is_array($entry) && is_string($entry['sessionId'] ?? null)) {
                $index[$entry['sessionId']] = $entry;
            }
        }

        return $index;
    }

    /** @return string[] */
    private function sessionFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        return array_values(array_filter(
            glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [],
            fn(string $f) => basename($f) !== 'sessions.json',
        ));
    }
}
