<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Parsers\ClaudeCodeParser;
use App\Writers\ClaudeCodeWriter;
use Carbon\Carbon;

function claudeWriterFor(string $storage, ?string $registry = null): ClaudeCodeWriter
{
    return new class($storage, $registry) extends ClaudeCodeWriter {
        public function __construct(private string $fakePath, private ?string $fakeRegistry) {}

        protected function getStoragePath(): string
        {
            return $this->fakePath;
        }

        protected function getDesktopRegistryDir(): ?string
        {
            return $this->fakeRegistry;
        }
    };
}

function claudeSession(string $id = 'sess-xyz', string $project = ''): SessionDTO
{
    return new SessionDTO(
        id:               $id,
        title:            'ClaudeCode Test',
        sourceTool:       'cursor',
        sourceMachineSha: 'abc',
        createdAt:        Carbon::parse('2026-01-15T10:00:00Z'),
        lastActiveAt:     Carbon::parse('2026-01-15T10:05:00Z'),
        turns:            collect([
            new TurnDTO('user', 'Q', Carbon::parse('2026-01-15T10:00:00Z')),
            new TurnDTO('assistant', 'A', Carbon::parse('2026-01-15T10:01:00Z')),
        ]),
        project:          $project,
    );
}

it('write creates one Claude Code JSONL transcript per session', function (): void {
    withTempDir(function (string $dir): void {
        claudeWriterFor($dir)->write(collect([claudeSession()]), '/home/dev/proj');

        // POSIX paths are encoded by replacing separators with dashes
        $file = $dir . '/-home-dev-proj/sess-xyz.jsonl';
        expect(file_exists($file))->toBeTrue();

        $entries  = array_map(fn($l) => json_decode($l, true), file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $messages = array_values(array_filter($entries, fn($e) => in_array($e['type'], ['user', 'assistant'], true)));

        expect($messages)->toHaveCount(2)
            ->and($messages[0]['message']['content'][0]['text'])->toBe('Q')
            ->and($messages[0]['cwd'])->toBe('/home/dev/proj')
            ->and($messages[0]['entrypoint'])->toBe('movez-import:cursor')
            ->and($messages[1]['entrypoint'])->toBe('movez-import:cursor');
    });
});

it('normalises Windows paths to the desktop app format', function (): void {
    withTempDir(function (string $dir): void {
        claudeWriterFor($dir)->write(collect([claudeSession()]), 'c:/Work/Proj');

        $file  = $dir . '/C--Work-Proj/sess-xyz.jsonl';
        expect(file_exists($file))->toBeTrue();

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $user  = json_decode($lines[3], true);
        expect($user['cwd'])->toBe('C:\\Work\\Proj');
    });
});

it('output can be parsed back by the Claude Code parser', function (): void {
    withTempDir(function (string $dir): void {
        claudeWriterFor($dir)->write(collect([claudeSession()]), '/home/dev/proj');

        $parser = new class($dir) extends ClaudeCodeParser {
            public function __construct(private string $fakePath) {}

            public function getStoragePath(string $projectPath): string
            {
                return $this->fakePath;
            }
        };

        $parsed = $parser->parse('/any')->first();

        expect($parsed->id)->toBe('sess-xyz')
            ->and($parsed->project)->toBe('proj')
            ->and($parsed->turns)->toHaveCount(2);
    });
});

it('does not duplicate desktop registry entries on re-import', function (): void {
    withTempDir(function (string $dir): void {
        $registry = $dir . '/registry';
        mkdir($registry);
        $writer = claudeWriterFor($dir . '/projects', $registry);

        $writer->write(collect([claudeSession()]), '/home/dev/proj');
        $writer->write(collect([claudeSession()]), '/home/dev/proj');

        $entries = glob($registry . '/local_*.json');
        expect($entries)->toHaveCount(1);

        $meta = json_decode((string) file_get_contents($entries[0]), true);
        expect($meta['cliSessionId'])->toBe('sess-xyz');
    });
});

it('rejects session ids that would escape the storage directory', function (): void {
    withTempDir(function (string $dir): void {
        expect(fn() => claudeWriterFor($dir)->write(collect([claudeSession('../../evil')]), '/home/dev/proj'))
            ->toThrow(InvalidArgumentException::class);
    });
});

it('toolName returns claude-code', function (): void {
    expect((new ClaudeCodeWriter())->toolName())->toBe('claude-code');
});
