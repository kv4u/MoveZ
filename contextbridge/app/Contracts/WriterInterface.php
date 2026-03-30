<?php
declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Support\Collection;
use App\DTOs\SessionDTO;

interface WriterInterface
{
    /** @param Collection<int, SessionDTO> $sessions */
    public function write(Collection $sessions, string $projectPath): void;

    /**
     * @param Collection<int, SessionDTO> $sessions
     * @return Collection<int, SessionDTO>
     */
    public function remapPaths(string $from, string $to, Collection $sessions): Collection;

    public function toolName(): string;
}
