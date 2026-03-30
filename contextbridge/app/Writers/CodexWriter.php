<?php
declare(strict_types=1);

namespace App\Writers;

use App\Contracts\WriterInterface;
use App\DTOs\SessionDTO;
use App\Support\PlatformPaths;
use Illuminate\Support\Collection;

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
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        foreach ($sessions as $session) {
            $lines = $session->turns->map(fn($turn) => json_encode([
                'role'      => $turn->role,
                'content'   => $turn->content,
                'timestamp' => $turn->timestamp->toIso8601String(),
                'title'     => $session->title,
            ], JSON_UNESCAPED_UNICODE));

            $path = $storagePath . DIRECTORY_SEPARATOR . $session->id . '.jsonl';
            file_put_contents($path, implode("\n", $lines->all()) . "\n");
        }
    }

    private function getStoragePath(): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config('movez.tools.codex.storage.' . $os, '');
        return PlatformPaths::expand((string) $cfg);
    }
}
