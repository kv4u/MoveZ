<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\ParserInterface;
use App\Contracts\WriterInterface;
use App\Parsers\ClineParser;
use App\Parsers\ClaudeCodeParser;
use App\Parsers\CodexParser;
use App\Parsers\ContinueParser;
use App\Parsers\CopilotCliParser;
use App\Parsers\CursorParser;
use App\Parsers\WindsurfParser;
use App\Support\PlatformPaths;
use App\Writers\ClaudeCodeWriter;
use App\Writers\CodexWriter;
use App\Writers\CopilotCliWriter;
use App\Writers\CursorWriter;
use App\Writers\WindsurfWriter;
use InvalidArgumentException;

final class ToolDetector
{
    private const PARSER_MAP = [
        'cursor'      => CursorParser::class,
        'windsurf'    => WindsurfParser::class,
        'claude-code' => ClaudeCodeParser::class,
        'codex'       => CodexParser::class,
        'copilot-cli' => CopilotCliParser::class,
        'cline'       => ClineParser::class,
        'continue'    => ContinueParser::class,
    ];

    private const WRITER_MAP = [
        'cursor'      => CursorWriter::class,
        'windsurf'    => WindsurfWriter::class,
        'claude-code' => ClaudeCodeWriter::class,
        'codex'       => CodexWriter::class,
        'copilot-cli' => CopilotCliWriter::class,
    ];

    /**
     * Return names of all AI tools whose storage directories exist on this machine.
     *
     * @return string[]
     */
    public function detect(): array
    {
        $detected = [];
        $os       = PlatformPaths::configKey();
        $tools    = config('movez.tools', []);

        foreach ($tools as $name => $cfg) {
            $storagePath = $cfg['storage'][$os] ?? null;
            if ($storagePath === null) {
                continue;
            }

            // Handle glob-style paths (e.g. Cline's wildcard extension path)
            if (str_contains($storagePath, '*')) {
                $matches = PlatformPaths::globExpand($storagePath);
                if (!empty($matches)) {
                    $detected[] = $name;
                }
            } elseif (is_dir(PlatformPaths::expand($storagePath))) {
                $detected[] = $name;
            }
        }

        return $detected;
    }

    public function getParser(string $toolName): ParserInterface
    {
        $class = self::PARSER_MAP[$toolName] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("No parser registered for tool: \"{$toolName}\"");
        }

        return new $class();
    }

    public function getWriter(string $toolName): WriterInterface
    {
        $class = self::WRITER_MAP[$toolName] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("No writer registered for tool: \"{$toolName}\"");
        }

        return new $class();
    }

    /** @return string[] */
    public function supportedTools(): array
    {
        return array_keys(self::PARSER_MAP);
    }

    /** @return string[] */
    public function writableTools(): array
    {
        return array_keys(self::WRITER_MAP);
    }
}
