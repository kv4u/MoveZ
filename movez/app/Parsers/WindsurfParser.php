<?php
declare(strict_types=1);

namespace App\Parsers;

use App\Contracts\ParserInterface;
use App\Support\PlatformPaths;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Windsurf (Cascade) stores conversations in an encrypted protobuf format
 * that MoveZ cannot read yet. Detection still works so `doctor` can report
 * the tool, but parsing fails with an explicit message instead of silently
 * returning nothing (or, as before, re-reading Cursor's sessions).
 */
class WindsurfParser implements ParserInterface
{
    public const UNSUPPORTED_MESSAGE =
        'Windsurf is not supported yet: Cascade conversations are stored in an encrypted format MoveZ cannot read.';

    public function toolName(): string
    {
        return 'windsurf';
    }

    public function getStoragePath(string $projectPath): string
    {
        $os  = PlatformPaths::configKey();
        $cfg = config("movez.tools.windsurf.storage.{$os}", '');
        return PlatformPaths::expand((string) $cfg);
    }

    public function detect(string $projectPath): bool
    {
        return is_dir($this->getStoragePath($projectPath));
    }

    public function parseMetadata(string $projectPath): Collection
    {
        return $this->parse($projectPath);
    }

    public function parse(string $projectPath): Collection
    {
        throw new RuntimeException(self::UNSUPPORTED_MESSAGE);
    }
}
