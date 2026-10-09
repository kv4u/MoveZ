<?php
declare(strict_types=1);

use App\Support\PlatformPaths;

function seedClaudeSession(string $storage, string $id, string $cwd, string $text): void
{
    $dir = $storage . '/' . md5($cwd);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($dir . "/{$id}.jsonl", implode("\n", [
        json_encode(['type' => 'user', 'cwd' => $cwd, 'message' => ['role' => 'user', 'content' => $text], 'timestamp' => '2026-01-15T10:00:00Z']),
        json_encode(['type' => 'assistant', 'cwd' => $cwd, 'message' => ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'ok']]], 'timestamp' => '2026-01-15T10:01:00Z']),
    ]) . "\n");
}

beforeEach(function (): void {
    $this->os = PlatformPaths::configKey();
});

it('export writes a .cbz bundle that import can read back', function (): void {
    withTempDir(function (string $dir): void {
        seedClaudeSession($dir . '/claude', 'sess-a', '/work/alpha', 'Alpha task');
        config([
            "movez.tools.claude-code.storage.{$this->os}" => $dir . '/claude',
            "movez.tools.codex.storage.{$this->os}"       => $dir . '/codex',
        ]);

        $this->artisan('export', ['--tool' => 'claude-code', '--output' => $dir . '/out.cbz'])
            ->expectsOutputToContain('Exported 1 session(s)')
            ->assertExitCode(0);

        $zip = new ZipArchive();
        expect($zip->open($dir . '/out.cbz'))->toBeTrue();
        expect($zip->locateName('bundle.json'))->not->toBeFalse()
            ->and($zip->locateName('manifest.json'))->not->toBeFalse();
        $zip->close();

        $this->artisan('import', ['--input' => $dir . '/out.cbz', '--tool' => 'codex', '--project' => '/work/alpha'])
            ->expectsOutputToContain('Imported 1 session(s) to codex')
            ->assertExitCode(0);

        expect(glob($dir . '/codex/*/*/*/rollout-*-sess-a.jsonl'))->toHaveCount(1);
    });
});

it('export --project only includes sessions for that project', function (): void {
    withTempDir(function (string $dir): void {
        seedClaudeSession($dir . '/claude', 'sess-a', '/work/alpha', 'Alpha task');
        seedClaudeSession($dir . '/claude', 'sess-b', '/work/beta', 'Beta task');
        config(["movez.tools.claude-code.storage.{$this->os}" => $dir . '/claude']);

        $this->artisan('export', ['--tool' => 'claude-code', '--project' => '/work/beta', '--output' => $dir . '/beta.json'])
            ->expectsOutputToContain('Exported 1 session(s)')
            ->assertExitCode(0);

        $data = json_decode((string) file_get_contents($dir . '/beta.json'), true);
        expect(array_column($data, 'id'))->toBe(['sess-b']);
    });
});

it('encrypted export round-trips with the same key', function (): void {
    withTempDir(function (string $dir): void {
        seedClaudeSession($dir . '/claude', 'sess-a', '/work/alpha', 'Secret task');
        config([
            'movez.key_path'                              => $dir . '/key',
            "movez.tools.claude-code.storage.{$this->os}" => $dir . '/claude',
            "movez.tools.copilot-cli.storage.{$this->os}" => $dir . '/copilot',
        ]);
        app()->forgetInstance(\App\Services\Encryptor::class);

        $this->artisan('export', ['--tool' => 'claude-code', '--output' => $dir . '/secret.cbz', '--encrypt' => true])
            ->assertExitCode(0);

        $zip = new ZipArchive();
        $zip->open($dir . '/secret.cbz');
        expect((string) $zip->getFromName('bundle.json'))->not->toContain('Secret task');
        $zip->close();

        $this->artisan('import', ['--input' => $dir . '/secret.cbz', '--tool' => 'copilot-cli'])
            ->assertExitCode(0);

        expect(glob($dir . '/copilot/*/events.jsonl'))->toHaveCount(1);
    });
});

it('import fails cleanly on an invalid bundle', function (): void {
    withTempDir(function (string $dir): void {
        $zip = new ZipArchive();
        $zip->open($dir . '/bad.cbz', ZipArchive::CREATE);
        $zip->addFromString('bundle.json', '{"version":"1.0"}');
        $zip->close();

        $this->artisan('import', ['--input' => $dir . '/bad.cbz', '--tool' => 'codex'])
            ->expectsOutputToContain('Cannot read bundle')
            ->assertExitCode(1);
    });
});

it('windsurf is reported as unsupported rather than silently empty', function (): void {
    $this->artisan('export', ['--tool' => 'windsurf', '--output' => sys_get_temp_dir() . '/never.cbz'])
        ->expectsOutputToContain('not supported yet')
        ->assertExitCode(1);
});

it('inspire boilerplate command is gone', function (): void {
    expect(array_keys(\Illuminate\Support\Facades\Artisan::all()))->not->toContain('inspire');
});

it('show returns a single session with its turns', function (): void {
    withTempDir(function (string $dir): void {
        seedClaudeSession($dir . '/claude', 'sess-a', '/work/alpha', 'Alpha task');
        seedClaudeSession($dir . '/claude', 'sess-b', '/work/beta', 'Beta task');
        config(["movez.tools.claude-code.storage.{$this->os}" => $dir . '/claude']);

        $this->artisan('show', ['--tool' => 'claude-code', '--id' => 'sess-b', '--json' => true])
            ->expectsOutputToContain('"title": "Beta task"')
            ->assertExitCode(0);

        $this->artisan('show', ['--tool' => 'claude-code', '--id' => 'missing'])
            ->assertExitCode(1);
    });
});
