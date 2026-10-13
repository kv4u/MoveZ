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

it('Copilot CLI parser reads session-state events.jsonl', function (): void {
    withTempDir(function (string $dir): void {
        $session = $dir . '/0f8fad5b-d9cb-469f-a165-70867728950e';
        mkdir($session);

        $event = fn(string $type, array $data, string $ts) => json_encode([
            'type' => $type, 'data' => $data, 'id' => uniqid(), 'timestamp' => $ts, 'parentId' => null,
        ]);

        file_put_contents($session . '/events.jsonl', implode("\n", [
            $event('session.start', ['sessionId' => '0f8fad5b-d9cb-469f-a165-70867728950e', 'context' => ['cwd' => '/home/dev/api']], '2026-01-15T10:00:00.000Z'),
            $event('user.message', ['content' => 'Add pagination', 'transformedContent' => '<wrapped>Add pagination</wrapped>'], '2026-01-15T10:00:01.000Z'),
            $event('model.message', ['role' => 'assistant', 'content' => 'utility-model side call'], '2026-01-15T10:00:02.000Z'),
            $event('assistant.message', ['content' => '', 'toolRequests' => [['name' => 'view']]], '2026-01-15T10:00:03.000Z'),
            $event('tool.execution_complete', ['result' => ['content' => 'file contents']], '2026-01-15T10:00:04.000Z'),
            $event('assistant.message', ['content' => 'Added cursor pagination.'], '2026-01-15T10:00:05.000Z'),
            '{"type":"user.message","data":{"content":"half-writ',
        ]));
        file_put_contents($session . '/workspace.yaml', "summary: Paginate the API\ncreated_at: 2026-01-15T10:00:00Z\ncwd: /home/dev/api\n");

        config([storageKey('copilot-cli') => $dir]);
        $parsed = (new CopilotCliParser())->parse('/any');

        expect($parsed)->toHaveCount(1);

        $s = $parsed->first();
        expect($s->id)->toBe('0f8fad5b-d9cb-469f-a165-70867728950e')
            ->and($s->title)->toBe('Paginate the API')
            ->and($s->project)->toBe('api')
            ->and($s->turns->pluck('content')->all())->toBe(['Add pagination', 'Added cursor pagination.'])
            ->and($s->turns->pluck('role')->all())->toBe(['user', 'assistant']);
    });
});

it('Copilot CLI writer output round-trips through the parser', function (): void {
    withTempDir(function (string $dir): void {
        config([storageKey('copilot-cli') => $dir]);

        $session = new SessionDTO(
            id: 'cursor-abc', title: 'Fix: login "redirect" bug', sourceTool: 'cursor', sourceMachineSha: 'x',
            createdAt: Carbon::parse('2026-02-01T09:00:00Z'), lastActiveAt: Carbon::parse('2026-02-01T09:05:00Z'),
            turns: collect([
                new TurnDTO('user', 'Why does login loop?', Carbon::parse('2026-02-01T09:00:00Z')),
                new TurnDTO('assistant', 'The session cookie is not secure.', Carbon::parse('2026-02-01T09:01:00Z')),
            ]),
        );

        (new App\Writers\CopilotCliWriter())->write(collect([$session, $session]), '/home/dev/web');

        // Non-UUID ids map to one stable UUID directory, so re-imports overwrite
        $dirs = glob($dir . '/*', GLOB_ONLYDIR);
        expect($dirs)->toHaveCount(1)
            ->and(basename($dirs[0]))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');

        $parsed = (new CopilotCliParser())->parse('/any')->first();
        expect($parsed->title)->toBe('Fix: login "redirect" bug')
            ->and($parsed->project)->toBe('web')
            ->and($parsed->turns)->toHaveCount(2)
            ->and($parsed->turns->last()->content)->toBe('The session cookie is not secure.');
    });
});

it('Continue parser reads sessions/<id>.json with the sessions.json index', function (): void {
    withTempDir(function (string $dir): void {
        file_put_contents($dir . '/sessions.json', json_encode([
            ['sessionId' => 'c-1', 'title' => 'Refactor auth', 'dateCreated' => '1736935200000', 'workspaceDirectory' => 'file:///c%3A/Work/My%20App'],
        ]));
        file_put_contents($dir . '/c-1.json', json_encode([
            'sessionId'          => 'c-1',
            'title'              => 'Refactor auth',
            'workspaceDirectory' => 'file:///c%3A/Work/My%20App',
            'history'            => [
                ['message' => ['role' => 'system', 'content' => 'You are helpful'], 'contextItems' => []],
                ['message' => ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Split the auth module']]], 'contextItems' => []],
                ['message' => ['role' => 'thinking', 'content' => 'hidden reasoning'], 'contextItems' => []],
                ['message' => ['role' => 'assistant', 'content' => 'Here is a plan'], 'contextItems' => []],
                ['message' => ['role' => 'tool', 'content' => 'tool output', 'toolCallId' => 't1'], 'contextItems' => []],
            ],
        ]));
        // An untitled session falls back to its first message
        file_put_contents($dir . '/c-2.json', json_encode([
            'sessionId' => 'c-2', 'title' => 'New Session', 'workspaceDirectory' => '/home/dev/site',
            'history'   => [['message' => ['role' => 'user', 'content' => 'Fix the footer'], 'contextItems' => []]],
        ]));

        config([storageKey('continue') => $dir]);
        $sessions = (new App\Parsers\ContinueParser())->parse('/any')->keyBy('id');

        expect($sessions)->toHaveCount(2)
            ->and($sessions['c-1']->title)->toBe('Refactor auth')
            ->and($sessions['c-1']->project)->toBe('My App')
            ->and($sessions['c-1']->createdAt->getTimestampMs())->toBe(1736935200000)
            ->and($sessions['c-1']->turns->pluck('content')->all())->toBe(['Split the auth module', 'Here is a plan'])
            ->and($sessions['c-2']->title)->toBe('Fix the footer')
            ->and($sessions['c-2']->project)->toBe('site');
    });
});

it('Windsurf parser fails explicitly instead of returning Cursor sessions', function (): void {
    expect(fn() => (new WindsurfParser())->parse('/any'))
        ->toThrow(RuntimeException::class, 'not supported yet');
});
