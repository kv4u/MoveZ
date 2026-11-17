<?php
declare(strict_types=1);

namespace App\Support;

use App\Contracts\WriterInterface;
use App\DTOs\SessionDTO;
use Illuminate\Support\Collection;

/**
 * Decides which project each imported session is written under.
 *
 * With an explicit --project everything goes there. Otherwise each session
 * goes back to the project it came from (after any --from-path/--to-path
 * remap), as long as that folder exists on this machine; the rest fall back
 * to the current directory.
 */
final class ProjectTargets
{
    /**
     * @param Collection<int, SessionDTO> $sessions
     * @return array{written: array<string, int>, fallback: int} Sessions written per project path,
     *         and how many had no usable project of their own
     */
    public static function write(WriterInterface $writer, Collection $sessions, ?string $project, ?string $fallback = null): array
    {
        if ($project !== null && trim($project) !== '') {
            $writer->write($sessions, $project);

            return ['written' => [$project => $sessions->count()], 'fallback' => 0];
        }

        $fallback ??= (string) getcwd();
        $groups     = $sessions->groupBy(fn(SessionDTO $s) => self::usablePath($s) ?? $fallback);

        foreach ($groups as $path => $group) {
            $writer->write($group->values(), (string) $path);
        }

        return [
            'written'  => $groups->map(fn(Collection $g) => $g->count())->all(),
            'fallback' => $sessions->filter(fn(SessionDTO $s) => self::usablePath($s) === null)->count(),
        ];
    }

    private static function usablePath(SessionDTO $session): ?string
    {
        $path = $session->projectPath;

        return $path !== '' && is_dir($path) ? $path : null;
    }
}
