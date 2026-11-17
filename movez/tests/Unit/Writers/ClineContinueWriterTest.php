<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Parsers\ClineParser;
use App\Parsers\ContinueParser;
use App\Support\PlatformPaths;
use App\Writers\ClineWriter;
use App\Writers\ContinueWriter;
use Carbon\Carbon;

function sampleSession(string $id = 'sess-a', string $title = 'Add rate limiting'): SessionDTO
{
    return new SessionDTO(
        id:               $id,
        title:            $title,
        sourceTool:       'claude-code',
        sourceMachineSha: 'abc',
        createdAt:        Carbon::parse('2026-01-15T10:00:00Z'),
        lastActiveAt:     Carbon::parse('2026-01-15T10:05:00Z'),
        turns:            collect([
            new TurnDTO('user', 'Add rate limiting to the API', Carbon::parse('2026-01-15T10:00:00Z')),
            new TurnDTO('assistant', 'Added a sliding-window limiter', Carbon::parse('2026-01-15T10:01:00Z')),
            new TurnDTO('user', 'Exempt /health', Carbon::parse('2026-01-15T10:01:00Z')),
        ]),
    );
}

function clineWriterConfig(string $dir): void
{
    $os = PlatformPaths::configKey();
    config([
        "movez.tools.cline.storage.{$os}"        => $dir . '/cline-data',
        "movez.tools.cline.editor_storage.{$os}" => $dir . '/editors/*/User/globalStorage/saoudrizwan.claude-dev',
    ]);
}

it('Cline writer produces tasks the Cline parser reads back', function (): void {
    withTempDir(function (string $dir): void {
        clineWriterConfig($dir);
        (new ClineWriter())->write(collect([sampleSession()]), '/home/dev/api');

        $task = $dir . '/cline-data/tasks/sess-a';
        $api  = json_decode((string) file_get_contents($task . '/api_conversation_history.json'), true);
        $ui   = json_decode((string) file_get_contents($task . '/ui_messages.json'), true);

        expect($api[0]['content'][0]['text'])->toBe("<task>\nAdd rate limiting to the API\n</task>")
            ->and(array_column($ui, 'say'))->toBe(['task', 'text', 'user_feedback'])
            // Two turns share a timestamp; the chat view needs distinct, increasing ones
            ->and($ui[2]['ts'])->toBeGreaterThan($ui[1]['ts']);

        $session = (new ClineParser())->parse('/any')->first();
        expect($session->id)->toBe('sess-a')
            ->and($session->title)->toBe('Add rate limiting to the API')
            ->and($session->project)->toBe('api')
            ->and($session->turns->pluck('content')->all())
            ->toBe(['Add rate limiting to the API', 'Added a sliding-window limiter', 'Exempt /health']);
    });
});

it('Cline writer adds to each editor\'s task list too, keeps other tasks, and replaces on re-import', function (): void {
    withTempDir(function (string $dir): void {
        $editor = $dir . '/editors/Code/User/globalStorage/saoudrizwan.claude-dev';
        mkdir($editor . '/state', 0755, true);
        file_put_contents($editor . '/state/taskHistory.json', json_encode([
            ['id' => '1700000000000', 'ts' => 1700000000000, 'task' => 'Existing task', 'tokensIn' => 5, 'tokensOut' => 6, 'totalCost' => 0.01],
        ]));

        clineWriterConfig($dir);
        $writer = new ClineWriter();
        $writer->write(collect([sampleSession()]), '/home/dev/api');
        $writer->write(collect([sampleSession(title: 'Renamed')]), '/home/dev/api');

        $history = json_decode((string) file_get_contents($editor . '/state/taskHistory.json'), true);
        expect(array_column($history, 'id'))->toBe(['1700000000000', 'sess-a'])
            ->and($history[0]['tokensIn'])->toBe(5)
            ->and(is_file($editor . '/tasks/sess-a/api_conversation_history.json'))->toBeTrue()
            ->and(json_decode((string) file_get_contents($dir . '/cline-data/state/taskHistory.json'), true))->toHaveCount(1);
    });
});

it('Cline writer refuses to overwrite a task list it cannot read', function (): void {
    withTempDir(function (string $dir): void {
        mkdir($dir . '/cline-data/state', 0755, true);
        file_put_contents($dir . '/cline-data/state/taskHistory.json', '{not json');

        clineWriterConfig($dir);
        expect(fn() => (new ClineWriter())->write(collect([sampleSession()]), '/x'))
            ->toThrow(RuntimeException::class, "isn't valid JSON");

        expect((string) file_get_contents($dir . '/cline-data/state/taskHistory.json'))->toBe('{not json')
            ->and(is_dir($dir . '/cline-data/tasks/sess-a'))->toBeFalse();
    });
});

it('Continue writer produces sessions the Continue parser reads back', function (): void {
    withTempDir(function (string $dir): void {
        config(['movez.tools.continue.storage.' . PlatformPaths::configKey() => $dir]);
        file_put_contents($dir . '/sessions.json', json_encode([
            ['sessionId' => 'old', 'title' => 'Earlier chat', 'dateCreated' => '1700000000000', 'workspaceDirectory' => ''],
        ]));

        $writer = new ContinueWriter();
        $writer->write(collect([sampleSession()]), 'C:\\Work\\My App');
        $writer->write(collect([sampleSession()]), 'C:\\Work\\My App');

        $index = json_decode((string) file_get_contents($dir . '/sessions.json'), true);
        $file  = json_decode((string) file_get_contents($dir . '/sess-a.json'), true);

        expect(array_column($index, 'sessionId'))->toBe(['old', 'sess-a'])
            ->and($index[1]['dateCreated'])->toBe('1768471200000')
            ->and($index[1]['messageCount'])->toBe(1)
            ->and($file['workspaceDirectory'])->toBe('file:///c%3A/Work/My%20App')
            ->and($file['history'][0])->toBe(['message' => ['role' => 'user', 'content' => 'Add rate limiting to the API'], 'contextItems' => []]);

        $session = (new ContinueParser())->parse('/any')->keyBy('id')['sess-a'];
        expect($session->title)->toBe('Add rate limiting')
            ->and($session->project)->toBe('My App')
            ->and($session->turns)->toHaveCount(3);
    });
});

it('Continue writer uses a plain file URI for POSIX paths', function (): void {
    withTempDir(function (string $dir): void {
        config(['movez.tools.continue.storage.' . PlatformPaths::configKey() => $dir]);
        (new ContinueWriter())->write(collect([sampleSession()]), '/home/dev/web app');

        $file = json_decode((string) file_get_contents($dir . '/sess-a.json'), true);
        expect($file['workspaceDirectory'])->toBe('file:///home/dev/web%20app');
    });
});
