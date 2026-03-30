<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Parsers\CursorParser;
use App\Writers\CursorWriter;
use Carbon\Carbon;

it('cursor writer output can be parsed back by cursor parser', function (): void {
    withTempDir(function (string $dir): void {
        $session = new SessionDTO(
            id:               'roundtrip-1',
            title:            'Round Trip',
            sourceTool:       'cursor',
            sourceMachineSha: 'abc123',
            createdAt:        Carbon::parse('2026-01-15'),
            lastActiveAt:     Carbon::parse('2026-01-15'),
            turns:            collect([
                new TurnDTO('user',      'Hello', Carbon::parse('2026-01-15')),
                new TurnDTO('assistant', 'World', Carbon::parse('2026-01-15')),
            ]),
        );

        $writer = new class($dir) extends CursorWriter {
            public function __construct(private string $fakePath) {}

            protected function getStoragePath(): string
            {
                return $this->fakePath;
            }
        };
        $writer->write(collect([$session]), $dir);

        $parser = new class($dir) extends CursorParser {
            public function __construct(private string $fakePath) {}

            public function getStoragePath(string $projectPath): string
            {
                return $this->fakePath;
            }
        };
        $parsed = $parser->parse($dir);

        // JSONL format does not preserve the session title; the parser infers
        // it from the first user message content.
        expect($parsed)->toHaveCount(1)
            ->and($parsed->first())->toBeInstanceOf(SessionDTO::class)
            ->and($parsed->first()->title)->toBe('Hello')
            ->and($parsed->first()->turns)->toHaveCount(2);
    });
});
