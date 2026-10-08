<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\Parsers\ClaudeCodeParser;

it('parse returns empty collection when storage dir does not exist', function (): void {
    $parser = new class('/nonexistent/path') extends ClaudeCodeParser {
        public function __construct(private string $fakePath) {}

        public function getStoragePath(string $projectPath): string
        {
            return $this->fakePath;
        }
    };

    expect($parser->parse('/any'))->toHaveCount(0);
});

it('parse returns one session per JSONL file', function (): void {
    withTempDir(function (string $dir): void {
        $lines = [
            json_encode(['type' => 'user',      'message' => ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Hi']]], 'timestamp' => '2026-01-15T10:00:00Z']),
            json_encode(['type' => 'assistant', 'message' => ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hello']]], 'timestamp' => '2026-01-15T10:01:00Z']),
        ];
        file_put_contents($dir . '/session-abc.jsonl', implode("\n", $lines));

        $parser = new class($dir) extends ClaudeCodeParser {
            public function __construct(private string $fakePath) {}

            public function getStoragePath(string $projectPath): string
            {
                return $this->fakePath;
            }
        };

        $sessions = $parser->parse('/any');

        expect($sessions)->toHaveCount(1)
            ->and($sessions->first())->toBeInstanceOf(SessionDTO::class)
            ->and($sessions->first()->id)->toBe('session-abc')
            ->and($sessions->first()->title)->toBe('Hi')
            ->and($sessions->first()->turns)->toHaveCount(2);
    });
});

it('toolName returns claude-code', function (): void {
    expect((new ClaudeCodeParser())->toolName())->toBe('claude-code');
});

it('maps reasoning_trace as null for standard turns', function (): void {
    withTempDir(function (string $dir): void {
        $line = json_encode([
            'type'      => 'assistant',
            'message'   => ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Response']]],
            'timestamp' => '2026-01-15T10:00:00Z',
        ]);
        file_put_contents($dir . '/sess.jsonl', $line);

        $parser = new class($dir) extends ClaudeCodeParser {
            public function __construct(private string $fakePath) {}

            public function getStoragePath(string $projectPath): string
            {
                return $this->fakePath;
            }
        };

        $sessions = $parser->parse('/any');

        expect($sessions->first()->turns->first()->reasoningTrace)
            ->toBeNull();
    });
});
