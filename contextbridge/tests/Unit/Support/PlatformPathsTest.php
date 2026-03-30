<?php
declare(strict_types=1);

use App\Support\PlatformPaths;

it('expand resolves tilde to HOME on non-Windows', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Tilde expansion is not used on Windows');
    }

    $home   = getenv('HOME') ?: '/root';
    $result = PlatformPaths::expand('~/.config/tool');

    expect($result)->toBe($home . '/.config/tool');
});

it('expand resolves %APPDATA% on Windows', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('APPDATA expansion is Windows-only');
    }

    $appData = getenv('APPDATA') ?: 'C:/Users/test/AppData/Roaming';
    putenv("APPDATA={$appData}");

    $result = PlatformPaths::expand('%APPDATA%/Tool');

    expect($result)->toBe("{$appData}/Tool");
});

it('expand resolves %USERPROFILE% on Windows', function (): void {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('USERPROFILE expansion is Windows-only');
    }

    $profile = getenv('USERPROFILE') ?: 'C:/Users/test';
    putenv("USERPROFILE={$profile}");

    $result = PlatformPaths::expand('%USERPROFILE%/.vscode');

    expect($result)->toBe("{$profile}/.vscode");
});

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
