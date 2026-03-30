<?php
declare(strict_types=1);

namespace App\Services;

use App\DTOs\SessionDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class SyncClient
{
    public function __construct(
        private readonly string    $baseUrl,
        private readonly Encryptor $encryptor,
    ) {}

    /**
     * Push sessions to the sync server (encrypted).
     *
     * @param Collection<int, SessionDTO> $sessions
     */
    public function push(Collection $sessions, string $token): void
    {
        $payload   = json_encode($sessions->map(fn(SessionDTO $s) => $s->toArray())->all());
        $encrypted = $this->encryptor->encrypt((string) $payload);

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/api/sync/push", ['data' => $encrypted]);

        $this->assertSuccess($response, [401 => 'Unauthorized', 500 => 'Server error']);
    }

    /**
     * Pull sessions from the sync server (decrypts automatically).
     *
     * @return Collection<int, SessionDTO>
     */
    public function pull(string $token): Collection
    {
        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/api/sync/pull");

        $this->assertSuccess($response, [401 => 'Unauthorized', 500 => 'Server error']);

        $decrypted = $this->encryptor->decrypt($response->json('data'));
        $decoded   = json_decode($decrypted, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Sync server returned invalid payload');
        }

        return collect($decoded)->map(fn(array $s) => SessionDTO::fromArray($s));
    }

    private function assertSuccess(\Illuminate\Http\Client\Response $response, array $errorMap): void
    {
        $status = $response->status();

        foreach ($errorMap as $code => $message) {
            if ($status === $code) {
                throw new RuntimeException($message);
            }
        }

        if (!$response->successful()) {
            throw new RuntimeException("Sync server error: HTTP {$status}");
        }
    }
}
