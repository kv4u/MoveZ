<?php
declare(strict_types=1);

namespace App\Commands\Sync;

/**
 * Token resolution order: --token, MOVEZ_TOKEN env var, ~/.movez/token file.
 * Server resolution order: --server, MOVEZ_SERVER_URL env var.
 * Prefer the env var or token file — command-line arguments are visible in process lists.
 */
trait ResolvesSyncCredentials
{
    private function resolveToken(): ?string
    {
        $token = $this->option('token') ?: config('movez.token');
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $tokenPath = config('movez.token_path');
        if (is_string($tokenPath) && file_exists($tokenPath)) {
            $fromFile = trim((string) file_get_contents($tokenPath));
            return $fromFile !== '' ? $fromFile : null;
        }

        return null;
    }

    private function resolveServer(): ?string
    {
        $server = $this->option('server') ?: config('movez.server_url');
        return is_string($server) && $server !== '' ? rtrim($server, '/') : null;
    }

    private function missingCredentials(?string $token, ?string $server): bool
    {
        if (!$token) {
            $this->error('No token found. Use --token, set MOVEZ_TOKEN, or store it in ~/.movez/token');
            return true;
        }

        if (!$server) {
            $this->error('No server URL. Use --server or set the MOVEZ_SERVER_URL env var');
            return true;
        }

        return false;
    }
}
