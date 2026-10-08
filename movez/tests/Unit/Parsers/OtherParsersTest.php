<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Parsers\ClaudeCodeParser;
use App\Parsers\ClineParser;
use App\Parsers\CodexParser;
use App\Parsers\CopilotCliParser;
use App\Parsers\WindsurfParser;
use App\Support\PlatformPaths;
use App\Writers\CodexWriter;
use Carbon\Carbon;

function storageKey(string $tool): string
{
    return "movez.tools.{$tool}.storage." . PlatformPaths::configKey();
}

it('JSONL parsing skips malformed lines instead of aborting', function (): void {
    withTempDir(function (string $dir): void {
        file_put_contents($dir . '/live.jsonl', implode("\n", [
            json_encode(['type' => 'user', 'message' => ['role' => 'user', 'content' => 'first'], 'timestamp' => '2026-01-15T10:00:00Z']),
            '{"type":"assistant","message":{"role":"assist',   // half-written line of a live session
            json_encode(['type' => 'assistant', 'message' => ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'reply']]], 'timestamp' => '2026-01-15T10:01:00Z']),
        ]));

        config([storageKey('claude-code') => $dir]);
        $sessions = (new ClaudeCodeParser())->parse('/any');

        expect($sessions)->toHaveCount(1)
            ->and($sessions->first()->turns->pluck('content')->all())->toBe(['first', 'reply']);
    });
});

it('Codex parser reads current rollout files in dated subdirectories', function (): void {
    withTempDir(function (string $dir): void {
        $day = $dir . '/2026/01/15';
        mkdir($day, 0755, true);
        file_put_contents($day . '/rollout-2026-01-15T10-00-00-7f3e1c2a-0000-4000-8000-000000000001.jsonl', implode("\n", [
            json_encode(['timestamp' => '2026-01-15T10:00:00Z', 'type' => 'session_meta', 'payload' => ['id' => '7f3e1c2a-0000-4000-8000-000000000001', 'cwd' => '/home/dev/shop']]),
            json_encode(['timestamp' => '2026-01-15T10:00:01Z', 'type' => 'response_item', 'payload' => ['type' => 'message', 'role' => 'user', 'content' => [['type' => 'input_text', 'text' => '<environment_context>cwd</environment_context>']]]]),
            json_encode(['timestamp' => '2026-01-15T10:00:02Z', 'type' => 'response_item', 'payload' => ['type' => 'message', 'role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'Add a cart']]]]),
            json_encode(['timestamp' => '2026-01-15T10:00:03Z', 'type' => 'response_item', 'payload' => ['type' => 'reasoning', 'summary' => []]]),
            json_encode(['timestamp' => '2026-01-15T10:00:04Z', 'type' => 'response_item', 'payload' => ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => 'Done']]]]),
        ]));

        config([storageKey('codex') => $dir]);
        $session = (new CodexParser())->parse('/any')->first();

        expect($session)->toBeInstanceOf(SessionDTO::class)
            ->and($session->id)->toBe('7f3e1c2a-0000-4000-8000-000000000001')
            ->and($session->project)->toBe('shop')
            ->and($session->title)->toBe('Add a cart')
            ->and($session->turns->pluck('content')->all())->toBe(['Add a cart', 'Done']);
    });
});

it('Codex writer output round-trips through the Codex parser', function (): void {
    withTempDir(function (string $dir): void {
        config([storageKey('codex') => $dir]);

        $session = new SessionDTO(
            id: 'imported-1', title: 'From Cursor', sourceTool: 'cursor', sourceMachineSha: 'x',
            createdAt: Carbon::parse('2026-02-01T09:00:00Z'), lastActiveAt: Carbon::parse('2026-02-01T09:05:00Z'),
            turns: collect([
                new TurnDTO('user', 'Question', Carbon::parse('2026-02-01T09:00:00Z')),
                new TurnDTO('assistant', 'Answer', Carbon::parse('2026-02-01T09:01:00Z')),
            ]),
        );

        (new CodexWriter())->write(collect([$session]), '/home/dev/app');

        expect(glob($dir . '/2026/02/01/rollout-*-imported-1.jsonl'))->toHaveCount(1);

        $parsed = (new CodexParser())->parse('/any')->first();
        expect($parsed->id)->toBe('imported-1')
            ->and($parsed->title)->toBe('From Cursor')
            ->and($parsed->project)->toBe('app')
            ->and($parsed->turns)->toHaveCount(2);
    });
});

it('Cline parser reads per-task api_conversation_history.json files', function (): void {
    withTempDir(function (string $dir): void {
        $tasks = $dir . '/ext-1.0/data/tasks';
        mkdir($tasks . '/1736935200000', 0755, true);
        file_put_contents($tasks . '/1736935200000/api_conversation_history.json', json_encode([
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => "<task>\nFix the login bug\n</task>"]]],
            ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Looking at auth.ts']]],
        ]));

        config([storageKey('cline') => $dir . '/ext-*/data/tasks']);
        $session = (new ClineParser())->parse('/any')->first();

        expect($session->id)->toBe('1736935200000')
            ->and($session->title)->toBe('Fix the login bug')
            ->and($session->turns)->toHaveCount(2)
            ->and($session->turns->last()->content)->toBe('Looking at auth.ts');
    });
});

it('Copilot CLI parser flattens block content and skips unreadable files', function (): void {
    withTempDir(function (string $dir): void {
        file_put_contents($dir . '/broken.json', '{not json');
        file_put_contents($dir . '/good.json', json_encode([
            'id' => 'cp-1', 'title' => 'Copilot chat', 'created_at' => '2026-01-15T10:00:00Z',
            'messages' => [
                ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'hi']], 'timestamp' => '2026-01-15T10:00:00Z'],
                ['role' => 'assistant', 'content' => 'hello', 'timestamp' => '2026-01-15T10:00:01Z'],
            ],
        ]));

        config([storageKey('copilot-cli') => $dir]);
        $sessions = (new CopilotCliParser())->parse('/any');

        expect($sessions)->toHaveCount(1)
            ->and($sessions->first()->turns->pluck('content')->all())->toBe(['hi', 'hello']);
    });
});

it('Windsurf parser fails explicitly instead of returning Cursor sessions', function (): void {
    expect(fn() => (new WindsurfParser())->parse('/any'))
        ->toThrow(RuntimeException::class, 'not supported yet');
});
