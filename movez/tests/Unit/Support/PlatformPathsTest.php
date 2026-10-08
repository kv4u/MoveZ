<?php
declare(strict_types=1);

use App\Support\PlatformPaths;

it('expand resolves tilde to HOME on non-Windows', function (): void {
    $home   = getenv('HOME') ?: '/root';
    $result = PlatformPaths::expand('~/.config/tool');

    expect($result)->toBe($home . '/.config/tool');
})->skip(PHP_OS_FAMILY === 'Windows', 'Tilde expansion is not used on Windows');

it('expand resolves %APPDATA% on Windows', function (): void {
    $appData = getenv('APPDATA') ?: 'C:/Users/test/AppData/Roaming';
    putenv("APPDATA={$appData}");

    $result = PlatformPaths::expand('%APPDATA%/Tool');

    expect($result)->toBe("{$appData}/Tool");
})->skip(PHP_OS_FAMILY !== 'Windows', 'APPDATA expansion is Windows-only');

it('expand resolves %USERPROFILE% on Windows', function (): void {
    $profile = getenv('USERPROFILE') ?: 'C:/Users/test';
    putenv("USERPROFILE={$profile}");

    $result = PlatformPaths::expand('%USERPROFILE%/.vscode');

    expect($result)->toBe("{$profile}/.vscode");
})->skip(PHP_OS_FAMILY !== 'Windows', 'USERPROFILE expansion is Windows-only');

it('globExpand returns an empty array for non-existent patterns', function (): void {
    $result = PlatformPaths::globExpand('/nonexistent_path_xyz123/*/data');
    expect($result)->toBe([]);
});

it('globExpand returns matching directories', function (): void {
    withTempDir(function (string $dir): void {
        mkdir($dir . '/match1', 0755, true);
        mkdir($dir . '/match2', 0755, true);

        $results = PlatformPaths::globExpand($dir . '/match*');
        expect($results)->toHaveCount(2);
    });
});

it('configKey returns a known OS string', function (): void {
    expect(PlatformPaths::configKey())->toBeIn(['Darwin', 'Linux', 'Windows']);
});
