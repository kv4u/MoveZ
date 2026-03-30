<?php
declare(strict_types=1);

use App\DTOs\FileDiffDTO;
use App\DTOs\TurnDTO;
use Carbon\Carbon;

it('round-trips through fromArray and toArray', function (): void {
    $data = [
        'role'             => 'user',
        'content'          => 'Hello world',
        'timestamp'        => '2026-01-15T10:00:00+00:00',
        'files_referenced' => ['src/Foo.php'],
        'file_diffs'       => [['file' => 'src/Foo.php', 'diff' => '+line']],
        'reasoning_trace'  => 'I thought about this',
        'tool_calls'       => [['name' => 'read_file']],
    ];

    $dto    = TurnDTO::fromArray($data);
    $result = $dto->toArray();

    expect($result['role'])->toBe('user')
        ->and($result['content'])->toBe('Hello world')
        ->and($result['files_referenced'])->toBe(['src/Foo.php'])
        ->and($result['reasoning_trace'])->toBe('I thought about this')
        ->and($result['tool_calls'])->toBe([['name' => 'read_file']])
        ->and($result['file_diffs'])->toBe([['file' => 'src/Foo.php', 'diff' => '+line']]);
});

it('defaults reasoning_trace to null', function (): void {
    $dto = TurnDTO::fromArray([
        'role'      => 'assistant',
        'content'   => 'Response',
        'timestamp' => '2026-01-15T10:00:00+00:00',
    ]);

    expect($dto->reasoningTrace)->toBeNull();
});

it('defaults tool_calls to empty array', function (): void {
    $dto = TurnDTO::fromArray([
        'role'      => 'user',
        'content'   => 'Q',
        'timestamp' => '2026-01-15T10:00:00+00:00',
    ]);

    expect($dto->toolCalls)->toBe([]);
});

it('has Carbon timestamp', function (): void {
    $dto = TurnDTO::fromArray([
        'role'      => 'user',
        'content'   => 'Q',
        'timestamp' => '2026-01-15T10:00:00+00:00',
    ]);

    expect($dto->timestamp)->toBeInstanceOf(Carbon::class);
});

it('fileDiffs collection contains FileDiffDTO instances', function (): void {
    $dto = TurnDTO::fromArray([
        'role'       => 'assistant',
        'content'    => 'A',
        'timestamp'  => '2026-01-15T10:00:00+00:00',
        'file_diffs' => [['file' => 'app/Foo.php', 'diff' => '+x']],
    ]);

    expect($dto->fileDiffs)->toHaveCount(1)
        ->and($dto->fileDiffs->first())->toBeInstanceOf(FileDiffDTO::class);
});

it('is a readonly class', function (): void {
    $rc = new ReflectionClass(TurnDTO::class);
    expect($rc->isReadOnly())->toBeTrue();
});
