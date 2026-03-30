<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Writers\CursorWriter;
use Carbon\Carbon;

function makeWriterSession(string $id = 's1'): SessionDTO
{
    return new SessionDTO(
        id:               $id,
        title:            'Writer Test',
        sourceTool:       'cursor',
        sourceMachineSha: 'abc',
        createdAt:        Carbon::parse('2026-01-15'),
        lastActiveAt:     Carbon::parse('2026-01-15'),
        turns:            collect([
            new TurnDTO('user',      'Question', Carbon::parse('2026-01-15')),
            new TurnDTO('assistant', 'Answer',   Carbon::parse('2026-01-15')),
        ]),
    );
}

it('write creates a JSONL transcript file with user and assistant turns', function (): void {
    withTempDir(function (string $dir): void {
        $sessionId = 's1';
        $writer = new class($dir) extends CursorWriter {
            public function __construct(private string $fakePath) {}

            protected function getStoragePath(): string
            {
                return $this->fakePath;
            }

            // Stub out global DB write — no real SQLite needed in unit tests
            protected function writeToGlobalDb(\App\DTOs\SessionDTO $session): void {}
        };

        $writer->write(collect([makeWriterSession($sessionId)]), '/any');

        // Path: {storagePath}/{encoded}/agent-transcripts/{id}/{id}.jsonl
        // For projectPath '/any' the encode is: 'any' (no drive letter on Linux-style path)
        $glob = glob($dir . '/*/agent-transcripts/' . $sessionId . '/' . $sessionId . '.jsonl');
        expect($glob)->not->toBeEmpty();

        $lines = array_filter(explode("\n", trim(file_get_contents($glob[0]))));
        expect($lines)->toHaveCount(2);

        $userLine = json_decode(array_values($lines)[0], true);
        expect($userLine['role'])->toBe('user')
            ->and($userLine['message']['content'][0]['type'])->toBe('text')
            ->and($userLine['message']['content'][0]['text'])->toContain('Question');

        $assistantLine = json_decode(array_values($lines)[1], true);
        expect($assistantLine['role'])->toBe('assistant')
            ->and($assistantLine['message']['content'][0]['text'])->toBe('Answer');
    });
});

it('toolName returns cursor', function (): void {
    expect((new CursorWriter())->toolName())->toBe('cursor');
});

it('remapPaths delegates to PathMapper and returns new collection', function (): void {
    withTempDir(function (string $dir): void {
        $writer  = new class($dir) extends CursorWriter {
            public function __construct(private string $fakePath) {}

            protected function getStoragePath(): string
            {
                return $this->fakePath;
            }
        };

        $session = new SessionDTO(
            id:               'r1',
            title:            'Remap Test',
            sourceTool:       'cursor',
            sourceMachineSha: 'abc',
            createdAt:        Carbon::now(),
            lastActiveAt:     Carbon::now(),
            turns:            collect([
                new TurnDTO(
                    role:            'user',
                    content:         'Q',
                    timestamp:       Carbon::now(),
                    filesReferenced: ['/Users/alice/proj/src/Foo.php'],
                ),
            ]),
        );

        $remapped = $writer->remapPaths('/Users/alice/proj', '/home/bob/proj', collect([$session]));

        expect($remapped->first()->turns->first()->filesReferenced)
            ->toBe(['/home/bob/proj/src/Foo.php']);
    });
});
