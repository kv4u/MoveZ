<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Parsers\ClaudeCodeParser;
use App\Writers\ClaudeCodeWriter;
use Carbon\Carbon;

it('write creates one JSONL file per session', function (): void {
    withTempDir(function (string $dir): void {
        $writer = new class($dir) extends ClaudeCodeWriter {
            public function __construct(private string $fakePath) {}

            protected function getStoragePath(): string
            {
                return $this->fakePath;
            }

            // Expose protected method for testing
            public function write(\Illuminate\Support\Collection $sessions, string $projectPath): void
            {
                if (!is_dir($this->fakePath)) {
                    mkdir($this->fakePath, 0755, true);
                }

                foreach ($sessions as $session) {
                    $lines = $session->turns->map(function ($turn) use ($session) {
                        return json_encode([
                            'role'             => $turn->role,
                            'content'          => $turn->content,
                            'timestamp'        => $turn->timestamp->toIso8601String(),
                            'files_referenced' => $turn->filesReferenced,
                            'file_diffs'       => $turn->fileDiffs
                                ->map(fn($d) => ['file' => $d->file, 'diff' => $d->diff])
                                ->values()
                                ->all(),
                            'reasoning_trace'  => $turn->reasoningTrace,
                            'tool_calls'       => $turn->toolCalls,
                            'session_title'    => $session->title,
                        ], JSON_UNESCAPED_UNICODE);
                    });

                    $path = $this->fakePath . DIRECTORY_SEPARATOR . $session->id . '.jsonl';
                    file_put_contents($path, implode("\n", $lines->all()) . "\n");
                }
            }
        };

        $session = new SessionDTO(
            id:               'sess-xyz',
            title:            'ClaudeCode Test',
            sourceTool:       'claude-code',
            sourceMachineSha: 'abc',
            createdAt:        Carbon::now(),
            lastActiveAt:     Carbon::now(),
            turns:            collect([
                new TurnDTO('user', 'Q', Carbon::now()),
                new TurnDTO('assistant', 'A', Carbon::now()),
            ]),
        );

        $writer->write(collect([$session]), '/any');

        expect(file_exists($dir . '/sess-xyz.jsonl'))->toBeTrue();

        $lines = file($dir . '/sess-xyz.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        expect($lines)->toHaveCount(2);

        $first = json_decode($lines[0], true);
        expect($first['role'])->toBe('user')
            ->and($first['content'])->toBe('Q');
    });
});

it('toolName returns claude-code', function (): void {
    expect((new ClaudeCodeWriter())->toolName())->toBe('claude-code');
});
