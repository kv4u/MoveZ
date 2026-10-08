<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Services\Encryptor;
use App\Services\SyncClient;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/** @param array<int, array<string, mixed>> $history */
function syncClientWith(Encryptor $enc, array $responses, array &$history = []): SyncClient
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new SyncClient('https://sync.test/', $enc, new Client(['handler' => $stack, 'http_errors' => false]));
}

function syncSession(): SessionDTO
{
    return new SessionDTO(
        id: 's1', title: 'Synced', sourceTool: 'cursor', sourceMachineSha: 'abc',
        createdAt: Carbon::parse('2026-01-15'), lastActiveAt: Carbon::parse('2026-01-15'),
        turns: collect([new TurnDTO('user', 'hello', Carbon::parse('2026-01-15'))]),
    );
}

it('push sends an encrypted sessions blob with a bearer token', function (): void {
    withTempDir(function (string $dir): void {
        $enc     = new Encryptor($dir . '/key');
        $history = [];
        $client  = syncClientWith($enc, [new Response(200, [], '{"status":"ok"}')], $history);

        $client->push(collect([syncSession()]), 'tok123');

        $request = $history[0]['request'];
        $body    = json_decode((string) $request->getBody(), true);

        expect((string) $request->getUri())->toBe('https://sync.test/api/sync/push')
            ->and($request->getHeaderLine('Authorization'))->toBe('Bearer tok123')
            ->and($body['count'])->toBe(1)
            ->and($body['sessions'])->not->toContain('hello')
            ->and(json_decode($enc->decrypt($body['sessions']), true)[0]['id'])->toBe('s1');
    });
});

it('pull decrypts the sessions returned by the server', function (): void {
    withTempDir(function (string $dir): void {
        $enc  = new Encryptor($dir . '/key');
        $blob = $enc->encrypt((string) json_encode([syncSession()->toArray()]));

        $sessions = syncClientWith($enc, [new Response(200, [], (string) json_encode(['sessions' => $blob, 'count' => 1]))])
            ->pull('tok');

        expect($sessions)->toHaveCount(1)
            ->and($sessions->first()->title)->toBe('Synced');
    });
});

it('pull returns an empty collection when nothing has been pushed', function (): void {
    withTempDir(function (string $dir): void {
        $sessions = syncClientWith(new Encryptor($dir . '/key'), [new Response(200, [], '{"sessions":null,"count":0}')])
            ->pull('tok');

        expect($sessions)->toBeEmpty();
    });
});

it('throws a readable error on 401', function (): void {
    withTempDir(function (string $dir): void {
        $client = syncClientWith(new Encryptor($dir . '/key'), [new Response(401)]);

        expect(fn() => $client->pull('bad'))->toThrow(RuntimeException::class, 'Unauthorized');
    });
});
