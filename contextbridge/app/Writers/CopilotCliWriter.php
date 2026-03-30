<?php
declare(strict_types=1);

namespace App\Writers;

use App\Contracts\WriterInterface;
use App\DTOs\SessionDTO;
use App\Support\PlatformPaths;
use Illuminate\Support\Collection;

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
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        foreach ($sessions as $session) {
            $record = [
                'id'         => $session->id,
                'title'      => $session->title,
                'created_at' => $session->createdAt->toIso8601String(),
                'updated_at' => $session->lastActiveAt->toIso8601String(),
                'messages'   => $session->turns->map(fn($turn) => [
                    'role'      => $turn->role,
                    'content'   => $turn->content,
                    'timestamp' => $turn->timestamp->toIso8601String(),
                ])->values()->all(),
            ];

            $path = $storagePath . DIRECTORY_SEPARATOR . $session->id . '.json';
            file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    private function getStoragePath(): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config('movez.tools.copilot-cli.storage.' . $os, '');
        return PlatformPaths::expand((string) $cfg);
    }
}
