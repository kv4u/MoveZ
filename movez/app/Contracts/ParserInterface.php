<?php
declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Support\Collection;
use App\DTOs\SessionDTO;

interface ParserInterface
{
    public function detect(string $projectPath): bool;

    public function getStoragePath(string $projectPath): string;

    /** @return Collection<int, SessionDTO> */
    public function parse(string $projectPath): Collection;

    /**
     * Fast metadata-only parse for listing — reads only the first few lines per
     * file to get title/timestamps. Returns SessionDTOs with empty turns.
     * Falls back to parse() by default.
     *
     * @return Collection<int, SessionDTO>
     */
    public function parseMetadata(string $projectPath): Collection;

    public function toolName(): string;
}
