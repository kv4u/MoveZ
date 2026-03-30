<?php
declare(strict_types=1);

use App\Services\Encryptor;

it('encrypt returns a non-empty base64 string', function (): void {
    withTempDir(function (string $dir): void {
        $enc = new Encryptor($dir . '/key');
        $result = $enc->encrypt('hello world');

        expect($result)->toBeString()->not->toBeEmpty()
            ->and(base64_decode($result, true))->not->toBeFalse();
    });
});

it('decrypt is the inverse of encrypt', function (): void {
    withTempDir(function (string $dir): void {
        $enc = new Encryptor($dir . '/key');
        $ct  = $enc->encrypt('secret data');

        expect($enc->decrypt($ct))->toBe('secret data');
    });
});

it('getOrCreateKey creates the key file with mode 0600', function (): void {
    withTempDir(function (string $dir): void {
        $keyPath = $dir . '/sub/key';
        $enc     = new Encryptor($keyPath);
        $enc->getOrCreateKey();

        expect(file_exists($keyPath))->toBeTrue();

        if (PHP_OS_FAMILY !== 'Windows') {
            $perms = substr(sprintf('%o', fileperms($keyPath)), -4);
            expect($perms)->toBe('0600');
        }
    });
});

it('getOrCreateKey is idempotent', function (): void {
    withTempDir(function (string $dir): void {
        $enc  = new Encryptor($dir . '/key');
        $key1 = $enc->getOrCreateKey();
        $key2 = $enc->getOrCreateKey();

        expect($key1)->toBe($key2);
    });
});

it('decrypt throws RuntimeException with truncated ciphertext', function (): void {
    withTempDir(function (string $dir): void {
        $enc = new Encryptor($dir . '/key');
        expect(fn() => $enc->decrypt(base64_encode('tooshort')))->toThrow(RuntimeException::class);
    });
});

it('decrypt throws RuntimeException with wrong key', function (): void {
    withTempDir(function (string $dir): void {
        $enc1 = new Encryptor($dir . '/key1');
        $enc2 = new Encryptor($dir . '/key2');

        $ct = $enc1->encrypt('sensitive');
        expect(fn() => $enc2->decrypt($ct))->toThrow(RuntimeException::class);
    });
});
