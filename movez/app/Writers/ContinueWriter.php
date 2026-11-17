<?php
declare(strict_types=1);

namespace App\Writers;

use App\Contracts\WriterInterface;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Parsers\ContinueParser;
use App\Support\SafePath;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Writes sessions the way Continue's own History.save() does
 * (core/util/history.ts in continuedev/continue):
 *   sessions/<sessionId>.json  — { sessionId, title, workspaceDirectory, history[] }
 *   sessions/sessions.json     — [{ sessionId, title, dateCreated, workspaceDirectory, messageCount }]
 * Re-importing a session replaces it.
 */
class ContinueWriter extends AbstractWriter implements WriterInterface
{
    public function toolName(): string
    {
        return 'continue';
    }

    /** @param Collection<int, SessionDTO> $sessions */
    public function write(Collection $sessions, string $projectPath): void
    {
        $dir = $this->getStoragePath();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $index     = $this->readIndex($dir);
        $workspace = $this->workspaceUri($projectPath);

        foreach ($sessions as $session) {
            $id = SafePath::id($session->id);

            $this->writeJson($dir . DIRECTORY_SEPARATOR . $id . '.json', [
                'sessionId'          => $id,
                'title'              => $session->title,
                'workspaceDirectory' => $workspace,
                'history'            => $session->turns->map(fn(TurnDTO $t) => [
                    'message'      => ['role' => $t->role, 'content' => $t->content],
                    'contextItems' => [],
                ])->values()->all(),
            ]);

            $index   = array_values(array_filter($index, fn($s) => ($s['sessionId'] ?? null) !== $id));
            $index[] = [
                'sessionId'          => $id,
                'title'              => $session->title,
                'dateCreated'        => (string) $session->createdAt->getTimestampMs(),
                'workspaceDirectory' => $workspace,
                'messageCount'       => $session->turns->filter(fn(TurnDTO $t) => $t->role === 'assistant')->count(),
            ];
        }

        $this->writeJson($dir . DIRECTORY_SEPARATOR . 'sessions.json', $index);
    }

    protected function getStoragePath(): string
    {
        return (new ContinueParser())->getStoragePath('');
    }

    /**
     * Continue in VS Code records the workspace as a file URI (vscode.Uri.toString()),
     * e.g. file:///c%3A/Work/app, and filters its history by it.
     */
    private function workspaceUri(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === '') {
            return '';
        }

        if (preg_match('#^([A-Za-z]):(/.*)?$#', $path, $m)) {
            $path = '/' . strtolower($m[1]) . '%3A' . implode('/', array_map('rawurlencode', explode('/', $m[2] ?? '')));
        } else {
            $path = implode('/', array_map('rawurlencode', explode('/', $path)));
        }

        return 'file://' . $path;
    }

    /** @return array<int, array<string, mixed>> */
    private function readIndex(string $dir): array
    {
        $file = $dir . DIRECTORY_SEPARATOR . 'sessions.json';
        if (!is_file($file)) {
            return [];
        }

        $raw = trim((string) file_get_contents($file));
        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !array_is_list($data)) {
            // Never replace a session list we can't read: that would hide Continue's history
            throw new RuntimeException("Continue's session list at {$file} isn't valid JSON; nothing was imported.");
        }

        return $data;
    }

    /** @param array<mixed> $data */
    private function writeJson(string $file, array $data): void
    {
        // Write to a temp file and rename, so a crash never leaves half a file
        $tmp = $file . '.movez-' . bin2hex(random_bytes(4));
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException("Could not write {$file}");
        }
    }
}
