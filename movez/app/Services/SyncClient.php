<?php
declare(strict_types=1);

namespace App\Services;

use App\DTOs\SessionDTO;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class SyncClient
{
    private readonly ClientInterface $http;

    public function __construct(
        private readonly string    $baseUrl,
        private readonly Encryptor $encryptor,
        ?ClientInterface           $http = null,
    ) {
        $this->http = $http ?? new Client(['timeout' => 60, 'http_errors' => false]);
    }

    /**
     * Push sessions to the sync server (encrypted client-side).
     *
     * @param Collection<int, SessionDTO> $sessions
     */
    public function push(Collection $sessions, string $token): void
    {
        $payload   = json_encode($sessions->map(fn(SessionDTO $s) => $s->toArray())->values()->all());
        $encrypted = $this->encryptor->encrypt((string) $payload);

        $response = $this->request('POST', '/api/sync/push', $token, [
            'json' => ['sessions' => $encrypted, 'count' => $sessions->count()],
        ]);

        $this->assertSuccess($response);
    }

    /**
     * Pull sessions from the sync server (decrypts automatically).
     * Returns an empty collection when nothing has been pushed yet.
     *
     * @return Collection<int, SessionDTO>
     */
    public function pull(string $token): Collection
    {
        $response = $this->request('GET', '/api/sync/pull', $token);
        $this->assertSuccess($response);

        $body = json_decode((string) $response->getBody(), true);
        if (!is_array($body)) {
            throw new RuntimeException('Sync server returned invalid JSON');
        }

        $blob = $body['sessions'] ?? null;
        if ($blob === null || $blob === '') {
            return collect();
        }

        if (!is_string($blob)) {
            throw new RuntimeException('Sync server returned an invalid payload');
        }

        $decoded = json_decode($this->encryptor->decrypt($blob), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Decrypted sync payload is not valid JSON');
        }

        return collect($decoded)->map(fn(array $s) => SessionDTO::fromArray($s));
    }

    /** @param array<string, mixed> $options */
    private function request(string $method, string $path, string $token, array $options = []): ResponseInterface
    {
        $options['headers'] = [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ];

        try {
            return $this->http->request($method, rtrim($this->baseUrl, '/') . $path, $options);
        } catch (GuzzleException $e) {
            throw new RuntimeException("Cannot reach sync server: {$e->getMessage()}", 0, $e);
        }
    }

    private function assertSuccess(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();

        match (true) {
            $status === 401              => throw new RuntimeException('Unauthorized — check your API token'),
            $status === 422              => throw new RuntimeException('Sync server rejected the payload (HTTP 422)'),
            $status < 200 || $status >= 300 => throw new RuntimeException("Sync server error: HTTP {$status}"),
            default                      => null,
        };
    }
}
