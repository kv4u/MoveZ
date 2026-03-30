<?php
declare(strict_types=1);

use App\DTOs\FileDiffDTO;
use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Services\PathMapper;
use Carbon\Carbon;

function makeSession(array $filesReferenced = [], array $diffs = []): SessionDTO
{
    $fileDiffs = collect(array_map(
        fn(array $d) => new FileDiffDTO($d['file'], $d['diff']),
        $diffs
    ));

    $turn = new TurnDTO(
        role:            'user',
        content:         'test',
        timestamp:       Carbon::now(),
        filesReferenced: $filesReferenced,
        fileDiffs:       $fileDiffs,
    );

    return new SessionDTO(
        id:               'test-session',
        title:            'Test',
        sourceTool:       'cursor',
        sourceMachineSha: 'abc123',
        createdAt:        Carbon::now(),
        lastActiveAt:     Carbon::now(),
        turns:            collect([$turn]),
    );
}

it('remaps filesReferenced paths', function (): void {
    $mapper  = new PathMapper();
    $session = makeSession(filesReferenced: ['/Users/alice/project/src/Foo.php']);

    $result = $mapper->remap('/Users/alice/project', '/home/bob/project', collect([$session]));

    expect($result->first()->turns->first()->filesReferenced)
        ->toBe(['/home/bob/project/src/Foo.php']);
});

it('remaps file diff paths', function (): void {
    $mapper  = new PathMapper();
    $session = makeSession(diffs: [['file' => '/Users/alice/project/app/Bar.php', 'diff' => '+line']]);

    $result = $mapper->remap('/Users/alice/project', '/home/bob/project', collect([$session]));

    expect($result->first()->turns->first()->fileDiffs->first()->file)
        ->toBe('/home/bob/project/app/Bar.php');
});

it('leaves paths that do not match the source prefix unchanged', function (): void {
    $mapper  = new PathMapper();
    $session = makeSession(filesReferenced: ['/opt/other/file.php']);

    $result = $mapper->remap('/Users/alice/project', '/home/bob/project', collect([$session]));

    expect($result->first()->turns->first()->filesReferenced)
        ->toBe(['/opt/other/file.php']);
});

it('returns new collection without mutating originals', function (): void {
    $mapper  = new PathMapper();
    $session = makeSession(filesReferenced: ['/Users/alice/project/src/Foo.php']);
    $original = collect([$session]);

    $remapped = $mapper->remap('/Users/alice/project', '/home/bob/project', $original);

    // Original unchanged
    expect($original->first()->turns->first()->filesReferenced)
        ->toBe(['/Users/alice/project/src/Foo.php'])
        ->and($remapped->first()->turns->first()->filesReferenced)
        ->toBe(['/home/bob/project/src/Foo.php']);
});
