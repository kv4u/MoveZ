<?php
declare(strict_types=1);

use App\Services\Encryptor;

function useKeyAt(string $path): Encryptor
{
    config(['movez.key_path' => $path]);
    app()->forgetInstance(Encryptor::class);

    return app(Encryptor::class);
}

it('moves a key between machines so the second one can decrypt', function (): void {
    withTempDir(function (string $dir): void {
        // Machine A encrypts something and exports its key
        $machineA   = useKeyAt($dir . '/a/key');
        $ciphertext = $machineA->encrypt('secret session');

        $this->artisan('key:export', ['--output' => $dir . '/transfer.key'])->assertExitCode(0);
        expect(file_get_contents($dir . '/transfer.key'))->toStartWith('movez-key:');

        // Machine B imports it and can decrypt
        useKeyAt($dir . '/b/key');
        $this->artisan('key:import', ['--file' => $dir . '/transfer.key'])
            ->expectsOutputToContain('Key installed')
            ->assertExitCode(0);

        $machineB = app(Encryptor::class);
        expect($machineB->decrypt($ciphertext))->toBe('secret session')
            ->and($machineB->fingerprint())->toBe($machineA->fingerprint());
    });
});

it('refuses to replace a different key without --force', function (): void {
    withTempDir(function (string $dir): void {
        $other = new Encryptor($dir . '/other');
        file_put_contents($dir . '/other.key', $other->exportKey());

        $mine = useKeyAt($dir . '/mine');
        $mine->getOrCreateKey();
        $original = $mine->fingerprint();

        $this->artisan('key:import', ['--file' => $dir . '/other.key'])
            ->expectsOutputToContain('--force')
            ->assertExitCode(1);
        expect(app(Encryptor::class)->fingerprint())->toBe($original);

        $this->artisan('key:import', ['--file' => $dir . '/other.key', '--force' => true])->assertExitCode(0);
        expect(app(Encryptor::class)->fingerprint())->toBe($other->fingerprint());
    });
});

it('re-importing the same key is a no-op success', function (): void {
    withTempDir(function (string $dir): void {
        $enc = useKeyAt($dir . '/key');
        file_put_contents($dir . '/same.key', 'movez-key:' . $enc->exportKey());

        $this->artisan('key:import', ['--file' => $dir . '/same.key'])->assertExitCode(0);
    });
});

it('rejects malformed keys', function (): void {
    withTempDir(function (string $dir): void {
        useKeyAt($dir . '/key');
        file_put_contents($dir . '/bad.key', 'not-a-key');

        $this->artisan('key:import', ['--file' => $dir . '/bad.key'])
            ->expectsOutputToContain('Invalid key')
            ->assertExitCode(1);

        expect(file_exists($dir . '/key'))->toBeFalse();
    });
});

it('key:fingerprint reports a missing key', function (): void {
    withTempDir(function (string $dir): void {
        useKeyAt($dir . '/none/key');

        $this->artisan('key:fingerprint')->assertExitCode(1);
    });
});

it('fingerprints are stable and do not reveal the key', function (): void {
    withTempDir(function (string $dir): void {
        $enc = new Encryptor($dir . '/key');
        $enc->getOrCreateKey();

        expect($enc->fingerprint())->toMatch('/^[0-9a-f]{4}(:[0-9a-f]{4}){3}$/')
            ->and($enc->fingerprint())->toBe($enc->fingerprint())
            ->and($enc->exportKey())->not->toContain(str_replace(':', '', (string) $enc->fingerprint()));
    });
});
