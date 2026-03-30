<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class Encryptor
{
    public function __construct(private readonly string $keyPath) {}

    public function getOrCreateKey(): string
    {
        if (!file_exists($this->keyPath)) {
            $dir = dirname($this->keyPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            $key = bin2hex(random_bytes(32));   // 256-bit
            file_put_contents($this->keyPath, $key);
            chmod($this->keyPath, 0600);
        }

        return hex2bin(trim((string) file_get_contents($this->keyPath)));
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
        $key = $this->getOrCreateKey();
        $raw = base64_decode($encoded, true);

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
}
