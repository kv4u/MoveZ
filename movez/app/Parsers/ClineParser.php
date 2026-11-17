<?php
declare(strict_types=1);

namespace App\Parsers;

use App\Contracts\ParserInterface;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Support\ClineLocations;
use App\Support\ContentFlattener;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads Cline tasks from both of its layouts (see ClineLocations): SDK
 * sessions under ~/.cline/data/sessions, and legacy tasks under
 * ~/.cline/data and each editor's global storage folder.
 */
class ClineParser implements ParserInterface
{
    public function toolName(): string
    {
        return 'cline';
    }

    public function getStoragePath(string $projectPath): string
    {
        return ClineLocations::dataDir();
    }

    public function detect(string $projectPath): bool
    {
        return ClineLocations::exists();
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
        $seen     = [];

        $add = function (?SessionDTO $session) use ($sessions, &$seen): void {
            // A task can sit in more than one root; Cline shows the first copy
            if ($session !== null && !isset($seen[$session->id])) {
                $seen[$session->id] = true;
                $sessions->push($session);
            }
        };

        foreach ($this->sdkSessionDirs() as $dir) {
            $add($this->parseSdkSession($dir));
        }

        foreach (ClineLocations::legacyRoots() as $root) {
            $history = $this->readTaskHistory($root);
            foreach (glob($root . '/tasks/*/api_conversation_history.json') ?: [] as $file) {
                $add($this->parseLegacyTask($file, $history));
            }
        }

        return $sessions;
    }

    /** @return string[] */
    private function sdkSessionDirs(): array
    {
        $dirs = glob(ClineLocations::sessionDir() . '/*', GLOB_ONLYDIR) ?: [];

        // Sub-agent and team-task sessions belong to a parent session
        return array_values(array_filter(
            $dirs,
            fn(string $d) => !str_contains(basename($d), '__sub__') && !str_contains(basename($d), '__teamtask__'),
        ));
    }

    private function parseSdkSession(string $dir): ?SessionDTO
    {
        $id       = basename($dir);
        $manifest = $this->readJson($dir . DIRECTORY_SEPARATOR . $id . '.json');
        if (($manifest['source'] ?? null) === 'subagent') {
            return null;
        }

        $messagesFile = is_string($manifest['messages_path'] ?? null) && is_file($manifest['messages_path'])
            ? $manifest['messages_path']
            : $dir . DIRECTORY_SEPARATOR . $id . '.messages.json';
        $messages = $this->readJson($messagesFile)['messages'] ?? null;
        if (!is_array($messages)) {
            return null;
        }

        $started = $this->parseDate($manifest['started_at'] ?? null) ?? Carbon::createFromTimestamp((int) filemtime($messagesFile));
        $turns   = $this->turns($messages, $started);
        if ($turns->isEmpty()) {
            return null;
        }

        $title = $manifest['metadata']['title'] ?? $manifest['prompt'] ?? null;

        return new SessionDTO(
            id:               $id,
            title:            $this->titleFrom(is_string($title) && trim($title) !== '' ? $title : $this->firstUserText($turns)),
            sourceTool:       $this->toolName(),
            sourceMachineSha: $this->machineSha(),
            createdAt:        $started,
            lastActiveAt:     $this->parseDate($manifest['ended_at'] ?? null) ?? Carbon::createFromTimestamp((int) filemtime($messagesFile)),
            turns:            $turns,
            project:          $this->projectName((string) ($manifest['workspace_root'] ?? $manifest['cwd'] ?? '')),
            projectPath:      (string) ($manifest['workspace_root'] ?? $manifest['cwd'] ?? ''),
        );
    }

    /** @param array<string, array<string, mixed>> $history taskHistory.json entries by id */
    private function parseLegacyTask(string $file, array $history): ?SessionDTO
    {
        $data = $this->readJson($file);
        if ($data === []) {
            return null;
        }

        $taskId   = basename(dirname($file));
        $messages = array_is_list($data) ? $data : ($data['conversation'] ?? $data['messages'] ?? []);
        $item     = $history[$taskId] ?? [];
        $fallback = is_numeric($taskId)
            ? Carbon::createFromTimestampMs((int) $taskId)
            : Carbon::createFromTimestamp((int) filemtime($file));

        $turns = $this->turns(is_array($messages) ? $messages : [], $fallback);
        if ($turns->isEmpty()) {
            return null;
        }

        $task = is_string($item['task'] ?? null) && trim($item['task']) !== '' ? $item['task'] : $this->firstUserText($turns);

        return new SessionDTO(
            id:               $taskId,
            title:            $this->titleFrom($task),
            sourceTool:       $this->toolName(),
            sourceMachineSha: $this->machineSha(),
            createdAt:        $turns->first()->timestamp,
            lastActiveAt:     isset($item['ts']) && is_numeric($item['ts'])
                ? Carbon::createFromTimestampMs((int) $item['ts'])
                : Carbon::createFromTimestamp((int) filemtime($file)),
            turns:            $turns,
            project:          $this->projectName((string) ($item['cwdOnTaskInitialization'] ?? '')),
            projectPath:      (string) ($item['cwdOnTaskInitialization'] ?? ''),
        );
    }

    /**
     * @param array<int, mixed> $messages Anthropic-style { role, content } messages
     * @return Collection<int, TurnDTO>
     */
    private function turns(array $messages, Carbon $fallback): Collection
    {
        return collect($messages)
            ->filter(fn($msg) => is_array($msg) && in_array($msg['role'] ?? null, ['user', 'assistant'], true))
            ->map(fn(array $msg) => new TurnDTO(
                role:      $msg['role'],
                content:   $this->cleanContent(ContentFlattener::flatten($msg['content'] ?? ''), $msg['role']),
                timestamp: isset($msg['ts']) && is_numeric($msg['ts']) ? Carbon::createFromTimestampMs((int) $msg['ts']) : $fallback,
            ))
            ->filter(fn(TurnDTO $t) => $t->content !== '')
            ->values();
    }

    /**
     * Cline wraps the first request in <task> tags and appends an
     * <environment_details> block (open files, time, cwd listing) to user
     * messages; keep only what the person actually wrote.
     */
    private function cleanContent(string $content, string $role): string
    {
        if ($role === 'user') {
            $content = (string) preg_replace('/<environment_details>.*?<\/environment_details>/s', '', $content);
            if (preg_match('/^\s*<task>\s*(.*?)\s*<\/task>/s', $content, $m)) {
                $content = $m[1] . substr($content, strlen($m[0]));
            }
        }

        return trim($content);
    }

    /** @return array<string, array<string, mixed>> */
    private function readTaskHistory(string $root): array
    {
        $items = $this->readJson($root . '/state/taskHistory.json');
        $byId  = [];
        foreach ($items as $item) {
            if (is_array($item) && is_string($item['id'] ?? null)) {
                $byId[$item['id']] = $item;
            }
        }

        return $byId;
    }

    /** @param Collection<int, TurnDTO> $turns */
    private function firstUserText(Collection $turns): string
    {
        return $turns->first(fn(TurnDTO $t) => $t->role === 'user')?->content ?? '';
    }

    private function titleFrom(string $content): string
    {
        // Cline wraps the user's task in <task>...</task>
        if (preg_match('/<task>\s*(.*?)\s*<\/task>/s', $content, $m)) {
            $content = $m[1];
        }

        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $content)), 0, 80) ?: 'Untitled';
    }

    private function projectName(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', trim($path)), '/');

        return $path === '' ? '' : basename($path);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<mixed> */
    private function readJson(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    private function machineSha(): string
    {
        return substr(hash('sha256', (string) gethostname()), 0, 16);
    }
}
