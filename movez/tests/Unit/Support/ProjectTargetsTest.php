<?php
declare(strict_types=1);

use App\Contracts\WriterInterface;
use App\DTOs\SessionDTO;
use App\Support\ProjectTargets;
use Carbon\Carbon;
use Illuminate\Support\Collection;

function targetSession(string $id, string $projectPath): SessionDTO
{
    return new SessionDTO(
        id: $id, title: $id, sourceTool: 'cursor', sourceMachineSha: 'a',
        createdAt: Carbon::now(), lastActiveAt: Carbon::now(), turns: collect(), projectPath: $projectPath,
    );
}

/** A writer that records which sessions were written under which project path */
function recordingWriter(): WriterInterface
{
    return new class implements WriterInterface {
        /** @var array<string, string[]> */
        public array $calls = [];

        public function write(Collection $sessions, string $projectPath): void
        {
            $this->calls[$projectPath] = [...($this->calls[$projectPath] ?? []), ...$sessions->pluck('id')->all()];
        }

        public function remapPaths(string $from, string $to, Collection $sessions): Collection
        {
            return $sessions;
        }

        public function toolName(): string
        {
            return 'test';
        }
    };
}

it('writes each session under its own project when that folder exists here', function (): void {
    withTempDir(function (string $dir): void {
        mkdir($dir . '/alpha');
        mkdir($dir . '/beta');
        $writer = recordingWriter();

        $result = ProjectTargets::write($writer, collect([
            targetSession('a1', $dir . '/alpha'),
            targetSession('b1', $dir . '/beta'),
            targetSession('a2', $dir . '/alpha'),
            targetSession('gone', 'D:/Old/Machine/app'),
            targetSession('none', ''),
        ]), null, $dir . '/fallback');

        expect($writer->calls)->toBe([
            $dir . '/alpha'    => ['a1', 'a2'],
            $dir . '/beta'     => ['b1'],
            $dir . '/fallback' => ['gone', 'none'],
        ])->and($result['fallback'])->toBe(2);
    });
});

it('puts everything under an explicit --project', function (): void {
    withTempDir(function (string $dir): void {
        mkdir($dir . '/alpha');
        $writer = recordingWriter();

        $result = ProjectTargets::write($writer, collect([
            targetSession('a1', $dir . '/alpha'),
            targetSession('x', ''),
        ]), '/chosen');

        expect($writer->calls)->toBe(['/chosen' => ['a1', 'x']])
            ->and($result['fallback'])->toBe(0);
    });
});
