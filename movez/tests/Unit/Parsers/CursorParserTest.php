<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\Parsers\CursorParser;

beforeEach(function (): void {
    // Override storage path config for tests
    config(['movez.tools.cursor.storage.Windows' => '']);
    config(['movez.tools.cursor.storage.Linux' => '']);
    config(['movez.tools.cursor.storage.Darwin' => '']);
});

it('parse returns empty collection when storage dir does not exist', function (): void {
    withTempDir(function (string $dir): void {
        $parser = new class($dir . '/nonexistent') extends CursorParser {
            public function __construct(private string $fakePath) {}

            public function getStoragePath(string $projectPath): string
            {
                return $this->fakePath;
            }
        };

        expect($parser->parse('/any'))->toHaveCount(0);
    });
});

it('parse returns sessions from JSONL agent-transcripts', function (): void {
    withTempDir(function (string $dir): void {
        makeCursorFixture($dir, [
            [
                'id'    => 'tab-001',
                'turns' => [
                    ['type' => 'human', 'text' => 'Hello'],
                    ['type' => 'ai',    'text' => 'World'],
                ],
            ],
        ]);

        $parser = new class($dir) extends CursorParser {
            public function __construct(private string $fakePath) {}

            public function getStoragePath(string $projectPath): string
            {
                return $this->fakePath;
            }
        };

        $sessions = $parser->parse('/any');

        expect($sessions)->toHaveCount(1)
            ->and($sessions->first())->toBeInstanceOf(SessionDTO::class)
            ->and($sessions->first()->title)->toBe('Hello')    // inferred from first user message
            ->and($sessions->first()->turns)->toHaveCount(2)
            ->and($sessions->first()->sourceTool)->toBe('cursor');
    });
});

it('toolName returns cursor', function (): void {
    expect((new CursorParser())->toolName())->toBe('cursor');
});
