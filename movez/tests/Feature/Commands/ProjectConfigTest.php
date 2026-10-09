<?php
declare(strict_types=1);

use App\Support\PlatformPaths;

function seedSessionFor(string $storage, string $cwd): void
{
    mkdir($storage . '/proj', 0755, true);
    file_put_contents($storage . '/proj/s-1.jsonl', implode("\n", [
        json_encode(['type' => 'user', 'cwd' => $cwd, 'message' => ['role' => 'user', 'content' => 'hello'], 'timestamp' => '2026-01-15T10:00:00Z']),
        json_encode(['type' => 'assistant', 'cwd' => $cwd, 'message' => ['role' => 'assistant', 'content' => 'hi'], 'timestamp' => '2026-01-15T10:01:00Z']),
    ]) . "\n");
}

it('carries project config files through an encrypted bundle', function (): void {
    withTempDir(function (string $dir): void {
        $os     = PlatformPaths::configKey();
        $source = $dir . '/source/app';
        $target = $dir . '/target/app';
        mkdir($source, 0755, true);
        mkdir($target, 0755, true);

        file_put_contents($source . '/CLAUDE.md', "# Rules\nUse strict types.");
        file_put_contents($source . '/.mcp.json', '{"mcpServers":{}}');
        file_put_contents($target . '/AGENTS.md', 'existing target file');   // must not be touched
        file_put_contents($source . '/AGENTS.md', 'source agents file');

        seedSessionFor($dir . '/claude', $source);
        config([
            'movez.key_path'                         => $dir . '/key',
            "movez.tools.claude-code.storage.{$os}"  => $dir . '/claude',
            "movez.tools.codex.storage.{$os}"        => $dir . '/codex',
        ]);
        app()->forgetInstance(\App\Services\Encryptor::class);

        $this->artisan('export', [
            '--tool' => 'claude-code', '--project' => $source, '--output' => $dir . '/b.cbz',
            '--encrypt' => true, '--with-config' => true,
        ])->expectsOutputToContain('Bundling project config: CLAUDE.md, .mcp.json, AGENTS.md')
          ->assertExitCode(0);

        $zip = new ZipArchive();
        $zip->open($dir . '/b.cbz');
        expect((string) $zip->getFromName('config.json'))->not->toContain('strict types');
        $zip->close();

        $this->artisan('import', [
            '--input' => $dir . '/b.cbz', '--tool' => 'codex', '--project' => $target, '--with-config' => true,
        ])->expectsOutputToContain('CLAUDE.md: written')
          ->expectsOutputToContain('AGENTS.md: kept existing')
          ->assertExitCode(0);

        expect(file_get_contents($target . '/CLAUDE.md'))->toBe("# Rules\nUse strict types.")
            ->and(file_get_contents($target . '/.mcp.json'))->toBe('{"mcpServers":{}}')
            ->and(file_get_contents($target . '/AGENTS.md'))->toBe('existing target file');

        $this->artisan('import', [
            '--input' => $dir . '/b.cbz', '--tool' => 'codex', '--project' => $target,
            '--with-config' => true, '--overwrite-config' => true,
        ])->assertExitCode(0);

        expect(file_get_contents($target . '/AGENTS.md'))->toBe('source agents file');
    });
});

it('does not bundle config files unless asked', function (): void {
    withTempDir(function (string $dir): void {
        $os      = PlatformPaths::configKey();
        $project = $dir . '/app';
        mkdir($project);
        file_put_contents($project . '/CLAUDE.md', 'private');
        seedSessionFor($dir . '/claude', $project);
        config(["movez.tools.claude-code.storage.{$os}" => $dir . '/claude']);

        $this->artisan('export', ['--tool' => 'claude-code', '--project' => $project, '--output' => $dir . '/b.cbz'])
            ->assertExitCode(0);

        $zip = new ZipArchive();
        $zip->open($dir . '/b.cbz');
        expect($zip->locateName('config.json'))->toBeFalse();
        $zip->close();
    });
});
