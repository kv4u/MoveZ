<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\Support\ContentFlattener;
use App\Support\ProjectFilter;
use App\Support\SafePath;
use Carbon\Carbon;

it('SafePath::id accepts normal ids', function (string $id): void {
    expect(SafePath::id($id))->toBe($id);
})->with(['abc-123', '7f3e1c2a-0000-4000-8000-000000000001', 'rollout_1.v2']);

it('SafePath::id rejects traversal and separators', function (string $id): void {
    expect(fn() => SafePath::id($id))->toThrow(InvalidArgumentException::class);
})->with(['', '.', '..', '../x', 'a/b', 'a\\b', 'C:evil', "x\0y"]);

it('SafePath::segment reduces a project name to its last segment', function (): void {
    expect(SafePath::segment('My Project'))->toBe('My Project')
        ->and(SafePath::segment('../../etc'))->toBe('etc')
        ->and(fn() => SafePath::segment('..'))->toThrow(InvalidArgumentException::class);
});

it('ContentFlattener handles strings, block arrays and junk', function (): void {
    expect(ContentFlattener::flatten('plain'))->toBe('plain')
        ->and(ContentFlattener::flatten([
            ['type' => 'text', 'text' => 'one'],
            ['type' => 'thinking', 'thinking' => 'hidden'],
            ['type' => 'tool_use', 'input' => []],
            'two',
        ]))->toBe("one\ntwo")
        ->and(ContentFlattener::flatten(null))->toBe('')
        ->and(ContentFlattener::flatten(42))->toBe('42');
});

it('ProjectFilter keeps only sessions for the given project', function (): void {
    $make = fn(string $id, string $project) => new SessionDTO(
        id: $id, title: $id, sourceTool: 'cursor', sourceMachineSha: 'x',
        createdAt: Carbon::now(), lastActiveAt: Carbon::now(), turns: collect(), project: $project,
    );

    $sessions = collect([
        $make('a', 'MoveZ'),
        $make('b', 'Personal-Flutter-MoveZ'),   // Cursor's decoded multi-segment name
        $make('c', 'OtherApp'),
        $make('d', ''),
    ]);

    expect(ProjectFilter::apply($sessions, 'C:\\Work\\MoveZ')->pluck('id')->all())->toBe(['a', 'b'])
        ->and(ProjectFilter::apply($sessions, null))->toHaveCount(4);

    // Relative paths resolve to the real folder name
    withTempDir(function (string $dir) use ($sessions): void {
        mkdir($dir . '/OtherApp');
        $cwd = getcwd();
        chdir($dir . '/OtherApp');
        try {
            expect(ProjectFilter::apply($sessions, '.')->pluck('id')->all())->toBe(['c']);
        } finally {
            chdir((string) $cwd);
        }
    });
});
