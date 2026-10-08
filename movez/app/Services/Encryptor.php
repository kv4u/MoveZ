<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class Encryptor
{
    public function __construct(private readonly string $keyPath) {}

    public function keyPath(): string
    {
        return $this->keyPath;
    }

    public function hasKey(): bool
    {
        return file_exists($this->keyPath);
    }

    public function getOrCreateKey(): string
    {
        if (!$this->hasKey()) {
            $dir = dirname($this->keyPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }

            // Restrict permissions before the secret is written
            touch($this->keyPath);
            chmod($this->keyPath, 0600);
            file_put_contents($this->keyPath, bin2hex(random_bytes(32)));   // 256-bit
        }

        return $this->readKey();
    }

    public function encrypt(string $plaintext): string
    {
        $key = $this->getOrCreateKey();
        $iv  = random_bytes(12);                    // 96-bit for GCM
        $tag = '';
        $ct  = openssl_encrypt(
            $plaintext, 'aes-256-gcm', $key,
            OPENSSL_RAW_DATA, $iv, $tag, '', 16
        );

        if ($ct === false) {
            throw new RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $tag . $ct);     // iv(12) + tag(16) + ciphertext
    }

    public function decrypt(string $encoded): string
    {
        // Never generate a key here: a fresh key can't decrypt anything and
        // would hide the real problem (key not copied from the source machine).
        if (!$this->hasKey()) {
            throw new RuntimeException(
                "No encryption key found at {$this->keyPath}. Copy ~/.movez/key from the machine that encrypted the data."
            );
        }

        $key = $this->readKey();
        $raw = base64_decode(trim($encoded), true);

        if ($raw === false || strlen($raw) < 29) {
            throw new RuntimeException('Invalid ciphertext');
        }

        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);

        $pt  = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($pt === false) {
            throw new RuntimeException('Decryption failed — wrong key or corrupted data');
        }

        return $pt;
    }

    private function readKey(): string
    {
        $hex = trim((string) file_get_contents($this->keyPath));
        $key = strlen($hex) === 64 && ctype_xdigit($hex) ? hex2bin($hex) : false;

        if ($key === false) {
            throw new RuntimeException("Encryption key at {$this->keyPath} is corrupted (expected 64 hex characters)");
        }

        return $key;
    }
}
