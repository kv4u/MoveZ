<?php
declare(strict_types=1);

namespace App\Support;

use App\DTOs\SessionDTO;
use Illuminate\Support\Collection;

/**
 * Restricts a session collection to the ones belonging to a project path.
 *
 * Parsers record only a readable project name (usually the folder basename),
 * so matching is done on the normalised basename of the requested path.
 */
final class ProjectFilter
{
    /**
     * @param  Collection<int, SessionDTO> $sessions
     * @return Collection<int, SessionDTO>
     */
    public static function apply(Collection $sessions, ?string $projectPath): Collection
    {
        if ($projectPath === null || trim($projectPath) === '') {
            return $sessions;
        }

        $needle = self::normalise(basename(str_replace('\\', '/', rtrim($projectPath, '/\\'))));
        if ($needle === '') {
            return $sessions;
        }

        return $sessions->filter(function (SessionDTO $session) use ($needle): bool {
            $project = self::normalise($session->project);

            return $project !== '' && ($project === $needle || str_ends_with($project, '-' . $needle));
        })->values();
    }

    private static function normalise(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    }
}
