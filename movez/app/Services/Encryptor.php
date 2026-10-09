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
                "No encryption key found at {$this->keyPath}. Run `movez key:export` on the machine that encrypted the data, then `movez key:import` here."
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

    /** The key as 64 hex characters, for moving it to another machine. */
    public function exportKey(): string
    {
        return bin2hex($this->getOrCreateKey());
    }

    /**
     * Short, non-secret identifier of the key. Two machines can compare
     * fingerprints to check they hold the same key without revealing it.
     */
    public function fingerprint(): ?string
    {
        if (!$this->hasKey()) {
            return null;
        }

        return implode(':', str_split(substr(hash('sha256', 'movez-key-fingerprint:' . $this->readKey()), 0, 16), 4));
    }

    /**
     * Install a key exported from another machine.
     *
     * @throws RuntimeException when the key is malformed, or a different key exists and $force is false
     */
    public function importKey(string $hex, bool $force = false): void
    {
        $hex = strtolower(trim($hex));
        if (str_starts_with($hex, 'movez-key:')) {
            $hex = substr($hex, strlen('movez-key:'));
        }

        if (strlen($hex) !== 64 || !ctype_xdigit($hex)) {
            throw new RuntimeException('Invalid key: expected 64 hex characters (as printed by `movez key:export`)');
        }

        if ($this->hasKey() && !$force && !hash_equals(bin2hex($this->readKey()), $hex)) {
            throw new RuntimeException(
                "A different key already exists at {$this->keyPath}. Bundles encrypted with it can't be read after replacing it. Use --force to replace it."
            );
        }

        $dir = dirname($this->keyPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        touch($this->keyPath);
        chmod($this->keyPath, 0600);
        file_put_contents($this->keyPath, $hex);
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
