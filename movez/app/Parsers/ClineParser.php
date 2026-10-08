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

class ClineParser implements ParserInterface
{
    public function toolName(): string
    {
        return 'cline';
    }

    public function getStoragePath(string $projectPath): string
    {
        $dirs = $this->taskRoots();
        return $dirs[0] ?? PlatformPaths::expand($this->storagePattern());
    }

    public function detect(string $projectPath): bool
    {
        return !empty($this->taskRoots());
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

        // Cline stores one directory per task:
        //   tasks/<taskId>/api_conversation_history.json
        foreach ($this->taskRoots() as $root) {
            foreach (glob($root . '/*/api_conversation_history.json') ?: [] as $file) {
                $session = $this->parseTask($file);
                if ($session !== null) {
                    $sessions->push($session);
                }
            }
        }

        return $sessions;
    }

    private function parseTask(string $file): ?SessionDTO
    {
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return null;
        }

        $taskId   = basename(dirname($file));
        $messages = isset($data[0]) ? $data : ($data['conversation'] ?? $data['messages'] ?? []);
        $fallback = is_numeric($taskId)
            ? Carbon::createFromTimestampMs((int) $taskId)
            : Carbon::createFromTimestamp((int) filemtime($file));

        $turns = collect($messages)
            ->filter(fn($msg) => is_array($msg))
            ->map(fn(array $msg) => new TurnDTO(
                role:      ($msg['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                content:   ContentFlattener::flatten($msg['content'] ?? ''),
                timestamp: isset($msg['ts']) ? Carbon::createFromTimestampMs((int) $msg['ts']) : $fallback,
            ))
            ->filter(fn(TurnDTO $t) => trim($t->content) !== '')
            ->values();

        if ($turns->isEmpty()) {
            return null;
        }

        $firstUser = $turns->first(fn(TurnDTO $t) => $t->role === 'user');
        $title     = $firstUser !== null ? $this->titleFrom($firstUser->content) : $taskId;

        return new SessionDTO(
            id:               $taskId,
            title:            $title,
            sourceTool:       $this->toolName(),
            sourceMachineSha: substr(hash('sha256', (string) gethostname()), 0, 16),
            createdAt:        $turns->first()->timestamp,
            lastActiveAt:     Carbon::createFromTimestamp((int) filemtime($file)),
            turns:            $turns,
        );
    }

    private function titleFrom(string $content): string
    {
        // Cline wraps the user's task in <task>...</task>
        if (preg_match('/<task>\s*(.*?)\s*<\/task>/s', $content, $m)) {
            $content = $m[1];
        }

        return mb_substr(trim($content), 0, 80) ?: 'Untitled';
    }

    /** @return string[] */
    private function taskRoots(): array
    {
        return PlatformPaths::globExpand($this->storagePattern());
    }

    private function storagePattern(): string
    {
        $os = PlatformPaths::configKey();
        return (string) config("movez.tools.cline.storage.{$os}", '');
    }
}
