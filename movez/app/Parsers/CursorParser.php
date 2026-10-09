<?php
declare(strict_types=1);

namespace App\Parsers;

use App\Contracts\ParserInterface;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Support\EncodedPathResolver;
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

    /**
     * Listing only needs titles and counts. Transcripts can be huge, so this
     * keeps no turn content in memory (the full parse exhausted PHP's default
     * 128 MB limit on real machines).
     *
     * @return Collection<int, SessionDTO>
     */
    public function parseMetadata(string $projectPath): Collection
    {
        return $this->parseAll($projectPath, metadataOnly: true);
    }

    public function parse(string $projectPath): Collection
    {
        return $this->parseAll($projectPath, metadataOnly: false);
    }

    /** @return Collection<int, SessionDTO> */
    private function parseAll(string $projectPath, bool $metadataOnly): Collection
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
                $session = $this->parseTranscript($file, $root, $metadataOnly);
                if ($session !== null) {
                    $sessions->push($session);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $sessions;
    }

    private function parseTranscript(string $file, string $root, bool $metadataOnly = false): ?SessionDTO
    {
        $fh = @fopen($file, 'r');
        if ($fh === false) {
            return null;
        }

        $turns     = collect();
        $turnCount = 0;
        $firstUser = null;
        // Transcripts don't embed per-line timestamps; use the file's mtime
        $mtime = Carbon::createFromTimestamp((int) filemtime($file));

        // Stream line by line: transcripts can be tens of MB
        while (($line = fgets($fh)) !== false) {
            $entry = json_decode($line, true);
            if (!is_array($entry) || !isset($entry['role'])) {
                continue;
            }

            $content = $this->extractContent(is_array($entry['message'] ?? null) ? $entry['message'] : []);
            if ($content === '') {
                continue;
            }

            $role = $entry['role'] === 'assistant' ? 'assistant' : 'user';
            $turnCount++;

            if ($firstUser === null && $role === 'user') {
                $firstUser = $content;
            }

            if (!$metadataOnly) {
                $turns->push(new TurnDTO(role: $role, content: $content, timestamp: $mtime));
            }
        }
        fclose($fh);

        if ($turnCount === 0) {
            return null;
        }

        $encodedProj = $this->encodedProjectName($file, $root);

        return new SessionDTO(
            id:               pathinfo($file, PATHINFO_FILENAME),
            title:            $this->inferTitle($firstUser, $encodedProj),
            sourceTool:       $this->toolName(),
            sourceMachineSha: $this->machineSha(),
            createdAt:        $mtime,
            lastActiveAt:     $mtime,
            turns:            $turns,
            project:          $this->decodeProjectName($encodedProj),
            turnCount:        $metadataOnly ? $turnCount : null,
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
        // Exact answer when the project folder exists on this machine
        $resolved = EncodedPathResolver::projectName($encoded);
        if ($resolved !== null) {
            return $resolved;
        }

        // Otherwise guess. Strip drive letter prefix: "d-" at start
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
    private function inferTitle(?string $firstUserMessage, string $fallback): string
    {
        if ($firstUserMessage === null) {
            return $fallback;
        }

        // Only the start matters for a title — avoid running regex/mb functions over huge pastes
        $text = mb_scrub(substr($firstUserMessage, 0, 4096), 'UTF-8');

        // Strip XML-style tags Cursor sometimes wraps around user queries
        $text = preg_replace('/<[^>]+>/', '', $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return $fallback;
        }

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 77) . '…' : $text;
    }

    private function machineSha(): string
    {
        return substr(hash('sha256', (string) gethostname()), 0, 16);
    }
}
