<?php
declare(strict_types=1);

use App\DTOs\FileDiffDTO;

it('stores file and diff via constructor promotion', function (): void {
    $dto = new FileDiffDTO('src/Foo.php', '--- a/Foo.php\n+++ b/Foo.php');

    expect($dto->file)->toBe('src/Foo.php')
        ->and($dto->diff)->toBe('--- a/Foo.php\n+++ b/Foo.php');
});

it('is a readonly class', function (): void {
    $rc = new ReflectionClass(FileDiffDTO::class);
    expect($rc->isReadOnly())->toBeTrue();
});

it('disallows mutation', function (): void {
    $dto = new FileDiffDTO('a.php', 'diff');

    expect(fn() => ($dto->file = 'b.php'))->toThrow(Error::class);
});
