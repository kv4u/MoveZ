<?php
declare(strict_types=1);

namespace App\Writers;

use App\Contracts\WriterInterface;
use App\DTOs\SessionDTO;
use App\Support\PlatformPaths;
use App\Support\SafePath;
use Illuminate\Support\Collection;

class ClaudeCodeWriter extends AbstractWriter implements WriterInterface
{
    public function toolName(): string
    {
        return 'claude-code';
    }

    /** @param Collection<int, SessionDTO> $sessions */
    public function write(Collection $sessions, string $projectPath): void
    {
        $storagePath = $this->getStoragePath();

        // Normalise path: backslashes + uppercase drive letter (matches Claude desktop app format)
        $projectPath = $this->normalisePath($projectPath);

        foreach ($sessions as $session) {
            $sessionId = SafePath::id($session->id);

            // If the session belongs to a different project, resolve its own directory.
            // e.g. projectPath = c:\work\personal\flutter\movez, session->project = RatWatch
            // → write to c--work-personal-flutter-ratwatch/ so it appears in that project.
            $sessionProjectPath = $projectPath;
            if ($session->project && strcasecmp(basename($projectPath), $session->project) !== 0) {
                $sessionProjectPath = dirname($projectPath) . DIRECTORY_SEPARATOR . SafePath::segment($session->project);
                $sessionProjectPath = $this->normalisePath($sessionProjectPath);
            }

            $sessionDir = $storagePath . DIRECTORY_SEPARATOR . $this->encodeProjectPath($sessionProjectPath);
            if (!is_dir($sessionDir)) {
                mkdir($sessionDir, 0755, true);
            }

            $lines   = [];
            $prevUuid = null;

            // Claude Code prefixes every session file with queue-operation entries
            $lines[] = json_encode([
                'type'      => 'queue-operation',
                'operation' => 'enqueue',
                'timestamp' => $session->createdAt->toIso8601ZuluString(),
                'sessionId' => $session->id,
            ], JSON_UNESCAPED_UNICODE);

            $lines[] = json_encode([
                'type'      => 'queue-operation',
                'operation' => 'dequeue',
                'timestamp' => $session->createdAt->toIso8601ZuluString(),
                'sessionId' => $session->id,
            ], JSON_UNESCAPED_UNICODE);

            // Add ai-title entry so Claude Code displays the session title
            if ($session->title) {
                $lines[] = json_encode([
                    'type'      => 'ai-title',
                    'sessionId' => $session->id,
                    'aiTitle'   => $session->title,
                ], JSON_UNESCAPED_UNICODE);
            }

            foreach ($session->turns as $turn) {
                $uuid = $this->uuid4();

                if ($turn->role === 'user') {
                    $lines[] = json_encode([
                        'parentUuid'     => $prevUuid,
                        'isSidechain'    => false,
                        'promptId'       => $this->uuid4(),
                        'type'           => 'user',
                        'message'        => [
                            'role'    => 'user',
                            'content' => [['type' => 'text', 'text' => $turn->content]],
                        ],
                        'uuid'           => $uuid,
                        'timestamp'      => $turn->timestamp->toIso8601ZuluString(),
                        'permissionMode' => 'default',
                        'userType'       => 'external',
                        'entrypoint'     => 'movez-import:' . $session->sourceTool,
                        'cwd'            => $sessionProjectPath,
                        'sessionId'      => $session->id,
                        'version'        => $this->getClaudeCodeVersion(),
                        'gitBranch'      => null,
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    $msgId = 'msg_' . substr(str_replace('-', '', $this->uuid4()), 0, 24);
                    $lines[] = json_encode([
                        'parentUuid'  => $prevUuid,
                        'isSidechain' => false,
                        'message'     => [
                            'id'              => $msgId,
                            'type'            => 'message',
                            'role'            => 'assistant',
                            'model'           => 'claude-sonnet',
                            'content'         => [['type' => 'text', 'text' => $turn->content]],
                            'stop_reason'     => 'end_turn',
                            'stop_sequence'   => null,
                            'usage'           => ['input_tokens' => 0, 'output_tokens' => 0],
                        ],
                        'requestId'   => 'req_' . substr(str_replace('-', '', $this->uuid4()), 0, 24),
                        'type'        => 'assistant',
                        'uuid'        => $uuid,
                        'timestamp'   => $turn->timestamp->toIso8601ZuluString(),
                        'userType'    => 'external',
                        'entrypoint'  => 'movez-import:' . $session->sourceTool,
                        'cwd'         => $sessionProjectPath,
                        'sessionId'   => $session->id,
                        'version'     => $this->getClaudeCodeVersion(),
                    ], JSON_UNESCAPED_UNICODE);
                }

                $prevUuid = $uuid;
            }

            $filePath = $sessionDir . DIRECTORY_SEPARATOR . $sessionId . '.jsonl';
            file_put_contents($filePath, implode("\n", $lines) . "\n");

            // Register with Claude desktop app so it appears in the app's history
            $this->createDesktopRegistryEntry($session, $sessionProjectPath);
        }
    }

    /**
     * Normalise a Windows path to match Claude desktop app format:
     *   - Use backslashes: C:/Work/... → C:\Work\...
     *   - Uppercase drive letter: c:\Work\... → C:\Work\...
     * POSIX paths (macOS/Linux) are returned unchanged.
     */
    private function normalisePath(string $path): string
    {
        if (!preg_match('#^[A-Za-z]:[\\\\/]#', $path)) {
            return $path;
        }

        $path = str_replace('/', '\\', $path);
        return preg_replace_callback('/^([a-z])(:\\\\)/', fn($m) => strtoupper($m[1]) . $m[2], $path) ?? $path;
    }

    /**
     * Encode a project path the way Claude desktop app does:
     *   C:\Work\Personal\Flutter\MoveZ → C--Work-Personal-Flutter-MoveZ
     */
    private function encodeProjectPath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path = preg_replace('/^([A-Za-z]):[\\\\\/]/', strtoupper('$1') . '--', $path) ?? $path;
        $path = str_replace([DIRECTORY_SEPARATOR, '/', ' '], '-', $path);
        return $path;
    }

    /**
     * Register the session with the Claude desktop app's session registry so it
     * appears in the app's history sidebar.
     * Registry lives at: %APPDATA%/Claude/claude-code-sessions/<machine-id>/<install-id>/
     */
    private function createDesktopRegistryEntry(SessionDTO $session, string $sessionProjectPath): void
    {
        $registryDir = $this->getDesktopRegistryDir();
        if (!$registryDir) {
            return;
        }

        // Re-importing the same session must update its entry, not add a duplicate
        $localId     = $this->findRegistryEntry($registryDir, $session->id) ?? ('local_' . $this->uuid4());
        $createdMs   = $session->createdAt->getTimestamp() * 1000;
        $lastTurn    = $session->turns->last();
        $lastMs      = $lastTurn ? ($lastTurn->timestamp->getTimestamp() * 1000) : $createdMs;

        $meta = [
            'sessionId'              => $localId,
            'cliSessionId'           => $session->id,
            'cwd'                    => $sessionProjectPath,
            'originCwd'              => $sessionProjectPath,
            'createdAt'              => $createdMs,
            'lastActivityAt'         => $lastMs,
            'model'                  => (string) config('movez.tools.claude-code.default_model', 'claude-opus-4-6'),
            'isArchived'             => false,
            'title'                  => $session->title ?: 'Imported session',
            'permissionMode'         => 'acceptEdits',
            'remoteMcpServersConfig' => [],
        ];

        file_put_contents(
            $registryDir . DIRECTORY_SEPARATOR . $localId . '.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /** Return the existing local_<uuid> id registered for a CLI session id, if any. */
    private function findRegistryEntry(string $registryDir, string $cliSessionId): ?string
    {
        foreach (glob($registryDir . DIRECTORY_SEPARATOR . 'local_*.json') ?: [] as $file) {
            $meta = json_decode((string) file_get_contents($file), true);
            if (is_array($meta) && ($meta['cliSessionId'] ?? null) === $cliSessionId) {
                return pathinfo($file, PATHINFO_FILENAME);
            }
        }

        return null;
    }

    protected function getDesktopRegistryDir(): ?string
    {
        $base = $this->configPath('desktop_registry');
        if ($base === '' || !is_dir($base)) {
            return null;
        }
        $machineDirs = glob($base . '/*', GLOB_ONLYDIR);
        if (empty($machineDirs)) {
            return null;
        }
        $installDirs = glob($machineDirs[0] . '/*', GLOB_ONLYDIR);
        if (empty($installDirs)) {
            return null;
        }
        return $installDirs[0];
    }

    private function getClaudeCodeVersion(): string
    {
        // Detect the newest installed Claude Code version from the desktop app's directory
        $versionDir = $this->configPath('desktop_versions');
        $versions   = [];
        if ($versionDir !== '' && is_dir($versionDir)) {
            foreach (scandir($versionDir) ?: [] as $entry) {
                if (preg_match('/^\d+\.\d+\.\d+$/', $entry) && is_dir($versionDir . '/' . $entry)) {
                    $versions[] = $entry;
                }
            }
        }

        if (!empty($versions)) {
            usort($versions, 'version_compare');
            return end($versions);
        }

        return (string) config('movez.tools.claude-code.default_version', '2.1.78');
    }

    protected function getStoragePath(): string
    {
        return $this->configPath('storage');
    }

    private function configPath(string $key): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config("movez.tools.claude-code.{$key}.{$os}", '');
        return PlatformPaths::expand((string) $cfg);
    }

    private function uuid4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
