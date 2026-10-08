<?php
declare(strict_types=1);

namespace App\Writers;

use App\Contracts\WriterInterface;
use App\DTOs\SessionDTO;
use App\Support\PlatformPaths;
use App\Support\SafePath;
use Illuminate\Support\Collection;

/**
 * Writes sessions in Codex CLI's rollout format:
 *   sessions/YYYY/MM/DD/rollout-<timestamp>-<id>.jsonl
 */
class CodexWriter extends AbstractWriter implements WriterInterface
{
    public function toolName(): string
    {
        return 'codex';
    }

    /** @param Collection<int, SessionDTO> $sessions */
    public function write(Collection $sessions, string $projectPath): void
    {
        $storagePath = $this->getStoragePath();

        foreach ($sessions as $session) {
            $id      = SafePath::id($session->id);
            $created = $session->createdAt->copy()->utc();
            $dir     = $storagePath . DIRECTORY_SEPARATOR . $created->format('Y')
                . DIRECTORY_SEPARATOR . $created->format('m')
                . DIRECTORY_SEPARATOR . $created->format('d');

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $lines   = [];
            $lines[] = json_encode([
                'timestamp' => $created->toIso8601ZuluString(),
                'type'      => 'session_meta',
                'payload'   => [
                    'id'         => $session->id,
                    'timestamp'  => $created->toIso8601ZuluString(),
                    'cwd'        => $projectPath,
                    'originator' => 'movez-import:' . $session->sourceTool,
                    'title'      => $session->title,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($session->turns as $turn) {
                $lines[] = json_encode([
                    'timestamp' => $turn->timestamp->copy()->utc()->toIso8601ZuluString(),
                    'type'      => 'response_item',
                    'payload'   => [
                        'type'    => 'message',
                        'role'    => $turn->role,
                        'content' => [[
                            'type' => $turn->role === 'user' ? 'input_text' : 'output_text',
                            'text' => $turn->content,
                        ]],
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $file = $dir . DIRECTORY_SEPARATOR . 'rollout-' . $created->format('Y-m-d\TH-i-s') . '-' . $id . '.jsonl';
            file_put_contents($file, implode("\n", $lines) . "\n");
        }
    }

    protected function getStoragePath(): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config('movez.tools.codex.storage.' . $os, '');
        return PlatformPaths::expand((string) $cfg);
    }
}
