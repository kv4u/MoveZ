<?php
declare(strict_types=1);

namespace App\Writers;

use App\Contracts\WriterInterface;
use App\DTOs\SessionDTO;
use App\Support\PlatformPaths;
use Illuminate\Support\Collection;

/**
 * Writes sessions in GitHub Copilot CLI's session-state layout:
 *   session-state/<uuid>/events.jsonl + workspace.yaml
 *
 * Copilot keys sessions by UUID, so non-UUID source ids are mapped to a
 * stable name-based UUID (re-importing the same session overwrites it).
 * Copilot also keeps a SQLite index of sessions; run `/chronicle reindex`
 * in Copilot CLI if imported sessions don't appear in its history.
 */
class CopilotCliWriter extends AbstractWriter implements WriterInterface
{
    public function toolName(): string
    {
        return 'copilot-cli';
    }

    /** @param Collection<int, SessionDTO> $sessions */
    public function write(Collection $sessions, string $projectPath): void
    {
        $storagePath = $this->getStoragePath();

        foreach ($sessions as $session) {
            $uuid = $this->sessionUuid($session->id);
            $dir  = $storagePath . DIRECTORY_SEPARATOR . $uuid;

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $created  = $session->createdAt->copy()->utc();
            $parentId = null;
            $lines    = [];

            $push = function (string $type, array $data, string $timestamp) use (&$lines, &$parentId): void {
                $id       = $this->uuid4();
                $lines[]  = json_encode([
                    'type'      => $type,
                    'data'      => $data,
                    'id'        => $id,
                    'timestamp' => $timestamp,
                    'parentId'  => $parentId,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $parentId = $id;
            };

            $push('session.start', [
                'sessionId' => $uuid,
                'producer'  => 'movez-import:' . $session->sourceTool,
                'context'   => ['cwd' => $projectPath],
            ], $created->toIso8601ZuluString('millisecond'));

            foreach ($session->turns as $turn) {
                $push(
                    $turn->role === 'user' ? 'user.message' : 'assistant.message',
                    ['content' => $turn->content],
                    $turn->timestamp->copy()->utc()->toIso8601ZuluString('millisecond'),
                );
            }

            file_put_contents($dir . DIRECTORY_SEPARATOR . 'events.jsonl', implode("\n", $lines) . "\n");
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'workspace.yaml', implode("\n", [
                'id: ' . $uuid,
                'cwd: ' . $this->yamlString($projectPath),
                'summary: ' . $this->yamlString($session->title),
                'created_at: ' . $created->toIso8601ZuluString('millisecond'),
                'updated_at: ' . $session->lastActiveAt->copy()->utc()->toIso8601ZuluString('millisecond'),
            ]) . "\n");
        }
    }

    protected function getStoragePath(): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config('movez.tools.copilot-cli.storage.' . $os, '');
        return PlatformPaths::expand((string) $cfg);
    }

    /** Keep UUID ids; derive a deterministic UUID (v5-style) for anything else. */
    private function sessionUuid(string $id): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            return strtolower($id);
        }

        $hash    = substr(sha1('movez:' . $id), 0, 32);
        $hash[12] = '5';
        $hash[16] = dechex((hexdec($hash[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s',
            substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    }

    private function yamlString(string $value): string
    {
        // Single line, double-quoted, so titles with ':' or '#' stay valid YAML
        $value = str_replace(["\r", "\n"], ' ', $value);
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function uuid4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
