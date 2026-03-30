<?php
declare(strict_types=1);

namespace App\Writers;

use App\DTOs\SessionDTO;
use App\Services\PathMapper;
use Illuminate\Support\Collection;

abstract class AbstractWriter
{
    /**
     * @param Collection<int, SessionDTO> $sessions
     * @return Collection<int, SessionDTO>
     */
    public function remapPaths(string $from, string $to, Collection $sessions): Collection
    {
        return (new PathMapper())->remap($from, $to, $sessions);
    }
}
