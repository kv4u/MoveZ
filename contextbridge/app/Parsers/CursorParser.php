<?php
declare(strict_types=1);

namespace App\Parsers;

use App\Contracts\ParserInterface;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Support\PlatformPaths;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CursorParser implements ParserInterface
{
    public function toolName(): string
    {
        return 'cursor';
    }

    public function getStoragePath(string $projectPath): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config("movez.tools.cursor.storage.{$os}", '');
        return PlatformPaths::expand((string) $cfg);
    }

    public function detect(string $projectPath): bool
    {
        $root = $this->getStoragePath($projectPath);
        if (!is_dir($root)) {
            return false;
        }
        // At least one agent-transcript JSONL must exist
        $files = glob($root . '/*/agent-transcripts/*/*.jsonl') ?: [];
        return !empty($files);
    }

    /** @return Collection<int, SessionDTO> */
    public function parseMetadata(string $projectPath): Collection
    {
        return $this->parse($projectPath);
    }

    public function parse(string $projectPath): Collection
    {
        $root = $this->getStoragePath($projectPath);
        if (!is_dir($root)) {
            return collect();
        }

        // ~/.cursor/projects/{encoded-project}/agent-transcripts/{uuid}/{uuid}.jsonl
        // Main transcripts: {project}/agent-transcripts/{uuid}/{uuid}.jsonl
        // Subagents:        {project}/agent-transcripts/{uuid}/subagents/{uuid}.jsonl  ← skipped below
        $files    = glob($root . '/*/agent-transcripts/*/*.jsonl') ?: [];
        $sessions = collect();

        foreach ($files as $file) {
            // Skip subagent files — their parent directory is named "subagents"
            $parentDir = basename(dirname($file));
            if ($parentDir === 'subagents') {
                continue;
            }

            try {
                $session = $this->parseTranscript($file, $root);
                if ($session !== null) {
                    $sessions->push($session);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $sessions;
    }

    private function parseTranscript(string $file, string $root): ?SessionDTO
    {
        $raw = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($raw)) {
            return null;
        }

        $turns     = collect();
        $createdAt = null;
        $lastAt    = null;

        foreach ($raw as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry) || !isset($entry['role'])) {
                continue;
            }

            $content = $this->extractContent($entry['message'] ?? []);
            if ($content === '') {
                continue;
            }

            $ts = Carbon::now(); // transcripts don't embed timestamps per line
            if ($createdAt === null) {
                $createdAt = $ts;
            }
            $lastAt = $ts;

            $turns->push(new TurnDTO(
                role:      $entry['role'] === 'assistant' ? 'assistant' : 'user',
                content:   $content,
                timestamp: $ts,
            ));
        }

        if ($turns->isEmpty()) {
            return null;
        }

        $sessionId   = pathinfo($file, PATHINFO_FILENAME);
        $encodedProj = $this->encodedProjectName($file, $root);
        $title       = $this->inferTitle($turns, $encodedProj);

        return new SessionDTO(
            id:               $sessionId,
            title:            $title,
            sourceTool:       $this->toolName(),
            sourceMachineSha: $this->machineSha(),
            createdAt:        $createdAt ?? Carbon::now(),
            lastActiveAt:     $lastAt ?? Carbon::now(),
            turns:            $turns,
            project:          $this->decodeProjectName($encodedProj),
        );
    }

    private function extractContent(array $message): string
    {
        $content = $message['content'] ?? '';

        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            return trim(implode(' ', array_map(
                fn($part) => is_array($part) ? ($part['text'] ?? '') : (string) $part,
                $content
            )));
        }

        return '';
    }

    /** Get the raw encoded directory name (e.g. d-Flutter-TriageBuddy) */
    private function encodedProjectName(string $file, string $root): string
    {
        $rel = str_replace('\\', '/', substr($file, strlen(rtrim($root, '/\\')) + 1));
        return explode('/', $rel)[0] ?? 'unknown';
    }

    /**
     * Decode Cursor's project directory name to a readable project name.
     * e.g. "d-Flutter-TriageBuddy" → "TriageBuddy"
     *      "d-Deep-Learning-models-Article1" → "models-Article1"
     * Strategy: strip leading drive prefix (single char + dash), show last path segment.
     */
    private function decodeProjectName(string $encoded): string
    {
        // Strip drive letter prefix: "d-" at start
        $stripped = preg_replace('/^[a-z]-/', '', $encoded) ?? $encoded;
        // The encoded path uses "-" as separator, but folder names may also contain "-"
        // Best guess: take everything after the first "-" as the readable name
        $parts = explode('-', $stripped);
        // Drop the first segment (top-level folder like "Flutter", "Users", etc.)
        // and join the rest — this gives "TriageBuddy", "FingerMatch", etc.
        if (count($parts) > 1) {
            return implode('-', array_slice($parts, 1));
        }
        return $stripped;
    }

    /** Use the first user message (truncated) as the session title. */
    private function inferTitle(Collection $turns, string $fallback): string
    {
        $first = $turns->first(fn(TurnDTO $t) => $t->role === 'user');
        if ($first === null) {
            return $fallback;
        }
        // Strip XML-style tags Cursor sometimes wraps around user queries
        $text = preg_replace('/<[^>]+>/', '', $first->content) ?? $first->content;
        $text = trim($text);
        return mb_strlen($text) > 80 ? mb_substr($text, 0, 77) . '…' : $text;
    }

    private function machineSha(): string
    {
        return substr(hash('sha256', (string) gethostname()), 0, 16);
    }
}
