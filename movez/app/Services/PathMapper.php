<?php
declare(strict_types=1);

namespace App\Services;

use App\DTOs\FileDiffDTO;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use Illuminate\Support\Collection;

final class PathMapper
{
    /**
     * Remap all file paths within sessions from one base path to another.
     * Returns a new Collection — original sessions are never mutated.
     *
     * @param  Collection<int, SessionDTO> $sessions
     * @return Collection<int, SessionDTO>
     */
    public function remap(string $from, string $to, Collection $sessions): Collection
    {
        return $sessions->map(fn(SessionDTO $session) => new SessionDTO(
            id:               $session->id,
            title:            $session->title,
            sourceTool:       $session->sourceTool,
            sourceMachineSha: $session->sourceMachineSha,
            createdAt:        $session->createdAt,
            lastActiveAt:     $session->lastActiveAt,
            project:          $session->project,
            turns:            $session->turns->map(
                fn(TurnDTO $turn) => new TurnDTO(
                    role:            $turn->role,
                    content:         $turn->content,
                    timestamp:       $turn->timestamp,
                    filesReferenced: array_map(
                        fn(string $p) => $this->replacePath($from, $to, $p),
                        $turn->filesReferenced,
                    ),
                    fileDiffs: $turn->fileDiffs->map(
                        fn(FileDiffDTO $d) => new FileDiffDTO(
                            file: $this->replacePath($from, $to, $d->file),
                            diff: $d->diff,
                        )
                    ),
                    reasoningTrace: $turn->reasoningTrace,
                    toolCalls:      $turn->toolCalls,
                )
            ),
        ));
    }

    private function replacePath(string $from, string $to, string $path): string
    {
        // Normalise separators before comparing
        $normPath = str_replace('\\', '/', $path);
        $normFrom = str_replace('\\', '/', $from);
        $normTo   = str_replace('\\', '/', $to);

        if (str_starts_with($normPath, $normFrom)) {
            return $normTo . substr($normPath, strlen($normFrom));
        }

        return $path;
    }
}
