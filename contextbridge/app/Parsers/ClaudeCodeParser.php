<?php
declare(strict_types=1);

namespace App\Parsers;

use App\Contracts\ParserInterface;
use App\DTOs\FileDiffDTO;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Support\PlatformPaths;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClaudeCodeParser extends JsonlParser implements ParserInterface
{
    public function toolName(): string
    {
        return 'claude-code';
    }

    public function getStoragePath(string $projectPath): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config("movez.tools.claude-code.storage.{$os}", '');
        return PlatformPaths::expand((string) $cfg);
    }

    public function detect(string $projectPath): bool
    {
        $storagePath = $this->getStoragePath($projectPath);
        if (!is_dir($storagePath)) {
            return false;
        }
        // Claude Code stores sessions in subdirs: projects/{encoded_path}/*.jsonl
        $files = glob($storagePath . '/*/*.jsonl') ?: glob($storagePath . '/*.jsonl') ?: [];
        return !empty($files);
    }

    /**
     * Fast metadata-only listing — reads only the first ~30 lines per file
     * for title/timestamps, scans cheaply for turn count. Much faster than
     * full parse() for large sessions (60 MB+).
     *
     * @return Collection<int, SessionDTO>
     */
    public function parseMetadata(string $projectPath): Collection
    {
        $storagePath = $this->getStoragePath($projectPath);
        if (!is_dir($storagePath)) {
            return collect();
        }

        $files    = array_merge(
            glob($storagePath . '/*.jsonl') ?: [],
            glob($storagePath . '/*/*.jsonl') ?: [],
        );
        $sessions = collect();

        foreach ($files as $file) {
            $fh = @fopen($file, 'r');
            if (!$fh) {
                continue;
            }

            $title      = null;
            $createdAt  = null;
            $turnCount  = 0;
            $headerDone = false;
            $sourceTool = $this->toolName(); // default: claude-code

            while (($line = fgets($fh)) !== false) {
                // Fast turn count — no JSON decode needed
                if (str_contains($line, '"type":"user"') || str_contains($line, '"type":"assistant"')) {
                    $turnCount++;
                }

                if (!$headerDone) {
                    // Detect sessions imported by MoveZ — entrypoint is
                    // "movez-import:<source_tool>" (new) or "movez-import" (legacy cursor)
                    if (str_contains($line, 'movez-import')) {
                        $d = json_decode($line, true);
                        if (isset($d['entrypoint'])) {
                            $parts      = explode(':', $d['entrypoint'], 2);
                            $sourceTool = isset($parts[1]) ? $parts[1] : 'cursor'; // legacy = cursor
                        }
                    }

                    // Grab ai-title without full decode
                    if (str_contains($line, '"ai-title"') || str_contains($line, '"aiTitle"')) {
                        $d     = json_decode($line, true);
                        $title = $d['aiTitle'] ?? null;
                    }

                    // Grab createdAt + fallback title from first user message
                    if ($createdAt === null && str_contains($line, '"type":"user"')) {
                        $d         = json_decode($line, true);
                        $createdAt = Carbon::parse($d['timestamp'] ?? 'now');
                        if ($title === null) {
                            $content = $d['message']['content'] ?? '';
                            if (is_array($content)) {
                                $text = '';
                                foreach ($content as $block) {
                                    if (isset($block['text'])) {
                                        $text = $block['text'];
                                        break;
                                    }
                                }
                                $title = $text;
                            } else {
                                $title = (string) $content;
                            }
                        }
                        if ($title !== null && $sourceTool !== $this->toolName()) {
                            $headerDone = true; // have everything we need
                        }
                    }
                }
            }
            fclose($fh);

            $sessionId  = pathinfo($file, PATHINFO_FILENAME);
            $encodedDir = basename(dirname($file));
            $project    = $this->decodeProjectName($encodedDir);
            $lastAt     = Carbon::createFromTimestamp(filemtime($file));

            $sessions->push(new SessionDTO(
                id:               $sessionId,
                title:            mb_substr(trim((string) $title), 0, 80) ?: basename($file),
                sourceTool:       $sourceTool,
                sourceMachineSha: $this->machineSha(),
                createdAt:        $createdAt ?? $lastAt,
                lastActiveAt:     $lastAt,
                turns:            collect(),
                project:          $project,
            ));
        }

        return $sessions;
    }

    /** @return Collection<int, SessionDTO> */
    public function parse(string $projectPath): Collection
    {
        $storagePath = $this->getStoragePath($projectPath);

        if (!is_dir($storagePath)) {
            return collect();
        }

        // Scan both flat (our writer output) and subdirectory (real Claude Code) layout
        $files = array_merge(
            glob($storagePath . '/*.jsonl') ?: [],
            glob($storagePath . '/*/*.jsonl') ?: [],
        );
        $sessions = collect();

        foreach ($files as $file) {
            $lines = $this->readJsonlFile($file);
            if (empty($lines)) {
                continue;
            }

            $sessionId = pathinfo($file, PATHINFO_FILENAME);
            $turns     = collect();
            $createdAt = null;
            $lastAt    = null;

            $firstUserContent = null;

            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }

                // Real Claude Code format: type=user|assistant with nested message object
                $type = $line['type'] ?? null;
                if (!in_array($type, ['user', 'assistant'], true)) {
                    continue;
                }

                $msg     = $line['message'] ?? [];
                $role    = $msg['role'] ?? $type;
                $rawContent = $msg['content'] ?? '';

                // Assistant content is an array of blocks; flatten to text
                if (is_array($rawContent)) {
                    $parts = [];
                    foreach ($rawContent as $block) {
                        if (isset($block['text'])) {
                            $parts[] = $block['text'];
                        } elseif (isset($block['thinking'])) {
                            // skip thinking blocks
                        }
                    }
                    $rawContent = implode("\n", $parts);
                }

                $ts = Carbon::parse($line['timestamp'] ?? 'now');

                if ($createdAt === null) {
                    $createdAt = $ts;
                }
                $lastAt = $ts;

                if ($firstUserContent === null && $role === 'user') {
                    $firstUserContent = $rawContent;
                }

                $turns->push(new TurnDTO(
                    role:            $role,
                    content:         (string) $rawContent,
                    timestamp:       $ts,
                    filesReferenced: [],
                    fileDiffs:       collect(),
                    reasoningTrace:  null,
                    toolCalls:       [],
                ));
            }

            // Derive project name from parent directory (e.g. "D--Flutter-Aurora" → "Aurora")
            $encodedDir = basename(dirname($file));
            $project    = $this->decodeProjectName($encodedDir);

            // Use first user message as title (truncated to 80 chars)
            $title = $firstUserContent !== null
                ? mb_substr(trim($firstUserContent), 0, 80)
                : basename($file);

            $sessions->push(new SessionDTO(
                id:               $sessionId,
                title:            $title,
                sourceTool:       $this->toolName(),
                sourceMachineSha: $this->machineSha(),
                createdAt:        $createdAt ?? Carbon::now(),
                lastActiveAt:     $lastAt ?? Carbon::now(),
                turns:            $turns,
                project:          $project,
            ));
        }

        return $sessions;
    }

    private function decodeProjectName(string $encoded): string
    {
        // Claude Code encodes paths as "D--Flutter-Aurora" (drive + double-dash + path segments)
        $stripped = preg_replace('/^[A-Za-z]--/', '', $encoded) ?? $encoded;
        $parts    = explode('-', $stripped);
        return end($parts) ?: $encoded;
    }

    private function machineSha(): string
    {
        return substr(hash('sha256', (string) gethostname()), 0, 16);
    }
}
