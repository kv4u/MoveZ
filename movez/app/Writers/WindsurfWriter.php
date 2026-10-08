<?php
declare(strict_types=1);

namespace App\Writers;

use App\Contracts\WriterInterface;
use App\Parsers\WindsurfParser;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Writing to Windsurf is not supported yet (see WindsurfParser). Previously this
 * wrote Cursor-style transcripts into Windsurf's workspaceStorage, which Windsurf
 * never reads; failing loudly is better than silently polluting that directory.
 */
class WindsurfWriter extends AbstractWriter implements WriterInterface
{
    public function toolName(): string
    {
        return 'windsurf';
    }

    public function write(Collection $sessions, string $projectPath): void
    {
        throw new RuntimeException(WindsurfParser::UNSUPPORTED_MESSAGE);
    }
}
