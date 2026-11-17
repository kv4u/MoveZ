<?php
declare(strict_types=1);

namespace App\Writers;

use App\Contracts\WriterInterface;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Support\ClineLocations;
use App\Support\SafePath;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Writes sessions as Cline tasks in its legacy layout (see ClineLocations).
 *
 * Cline's newer SDK store is indexed by an SQLite database and removes session
 * folders it has no record of, so imports use the legacy layout instead: every
 * Cline version lists it, current ones next to their own sessions. Tasks are
 * written to ~/.cline/data and to each editor's Cline storage folder that
 * exists; Cline merges the copies by id. Re-importing replaces a task.
 */
class ClineWriter extends AbstractWriter implements WriterInterface
{
    public function toolName(): string
    {
        return 'cline';
    }

    /** @param Collection<int, SessionDTO> $sessions */
    public function write(Collection $sessions, string $projectPath): void
    {
        // Read every task list first, so an unreadable one stops the import before anything is written
        $histories = [];
        foreach ($this->roots() as $root) {
            $histories[$root] = $this->readTaskHistory($root);
        }

        foreach ($histories as $root => $history) {
            foreach ($sessions as $session) {
                $id = SafePath::id($session->id);
                $this->writeTask($root . DIRECTORY_SEPARATOR . 'tasks' . DIRECTORY_SEPARATOR . $id, $session);

                $history = array_values(array_filter($history, fn($item) => ($item['id'] ?? null) !== $id));
                $history[] = $this->historyItem($id, $session, $projectPath);
            }

            $this->writeJson($root . DIRECTORY_SEPARATOR . 'state' . DIRECTORY_SEPARATOR . 'taskHistory.json', $history);
        }
    }

    /** @return string[] */
    protected function roots(): array
    {
        return array_values(array_unique(array_merge([ClineLocations::dataDir()], ClineLocations::editorDirs())));
    }

    private function writeTask(string $dir, SessionDTO $session): void
    {
        $api = [];
        $ui  = [];
        $ts  = 0;

        foreach ($session->turns->values() as $i => $turn) {
            /** @var TurnDTO $turn */
            $first = $i === 0 && $turn->role === 'user';
            // Cline opens every task with the request wrapped in <task> tags
            $text  = $first ? "<task>\n{$turn->content}\n</task>" : $turn->content;
            $api[] = ['role' => $turn->role, 'content' => [['type' => 'text', 'text' => $text]]];

            // The chat view keys messages by timestamp, so keep them strictly increasing
            $ts   = max($turn->timestamp->getTimestampMs(), $ts + 1);
            $ui[] = [
                'ts'   => $ts,
                'type' => 'say',
                'say'  => $first ? 'task' : ($turn->role === 'user' ? 'user_feedback' : 'text'),
                'text' => $turn->content,
            ];
        }

        $this->writeJson($dir . DIRECTORY_SEPARATOR . 'api_conversation_history.json', $api);
        $this->writeJson($dir . DIRECTORY_SEPARATOR . 'ui_messages.json', $ui);
    }

    /** @return array<string, mixed> A HistoryItem as Cline's history list expects it */
    private function historyItem(string $id, SessionDTO $session, string $projectPath): array
    {
        $firstUser = $session->turns->first(fn(TurnDTO $t) => $t->role === 'user');

        return array_filter([
            'id'                      => $id,
            'ts'                      => $session->lastActiveAt->getTimestampMs(),
            'task'                    => $firstUser?->content ?? $session->title,
            'tokensIn'                => 0,
            'tokensOut'               => 0,
            'totalCost'               => 0,
            'cwdOnTaskInitialization' => $projectPath !== '' ? $projectPath : null,
        ], fn($v) => $v !== null);
    }

    /** @return array<int, array<string, mixed>> */
    private function readTaskHistory(string $root): array
    {
        $file = $root . DIRECTORY_SEPARATOR . 'state' . DIRECTORY_SEPARATOR . 'taskHistory.json';
        if (!is_file($file)) {
            return [];
        }

        $raw = trim((string) file_get_contents($file));
        if ($raw === '' || $raw === '{}') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !array_is_list($data)) {
            // Never replace a task list we can't read: that would wipe Cline's history
            throw new RuntimeException("Cline's task list at {$file} isn't valid JSON; nothing was imported.");
        }

        return $data;
    }

    /** @param array<mixed> $data */
    private function writeJson(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Write to a temp file and rename, so a crash never leaves half a file
        $tmp = $file . '.movez-' . bin2hex(random_bytes(4));
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException("Could not write {$file}");
        }
    }
}
