<?php
declare(strict_types=1);

use App\Support\EncodedPathResolver;

/** Encode a real path the way Claude Code does (non-alphanumerics → "-"). */
function claudeEncode(string $path): string
{
    return (string) preg_replace('/[^A-Za-z0-9]/', '-', str_replace('\\', '/', $path));
}

/** Encode a real path the way Cursor does on Windows ("c-Work-...") or POSIX ("home-me-..."). */
function cursorEncode(string $path): string
{
    $path = str_replace('\\', '/', $path);
    if (preg_match('/^([A-Za-z]):\/(.*)$/', $path, $m)) {
        return strtolower($m[1]) . '-' . str_replace(['/', ' '], '-', $m[2]);
    }

    return str_replace(['/', ' '], '-', ltrim($path, '/'));
}

beforeEach(fn () => EncodedPathResolver::clearCache());

it('resolves hyphenated project folders that string guessing gets wrong', function (): void {
    withTempDir(function (string $dir): void {
        $project = $dir . '/client-work/my-app';
        mkdir($project, 0755, true);
        mkdir($dir . '/client-work/my', 0755, true);   // a decoy: "my" + "app" would also fit

        $real = realpath($project);

        expect(EncodedPathResolver::projectName(claudeEncode($real)))->toBe('my-app')
            ->and(EncodedPathResolver::projectName(cursorEncode($real)))->toBe('my-app');
    });
});

it('handles spaces and dots in folder names', function (): void {
    withTempDir(function (string $dir): void {
        $project = $dir . '/My Projects/site.v2';
        mkdir($project, 0755, true);

        expect(EncodedPathResolver::projectName(claudeEncode(realpath($project))))->toBe('site.v2');
    });
});

it('returns null when the project does not exist on this machine', function (): void {
    expect(EncodedPathResolver::resolve('-definitely-not-a-real-path-xyz123'))->toBeNull()
        ->and(EncodedPathResolver::projectName('Z--Old-Laptop-Project'))->toBeNull();
});
