<?php
declare(strict_types=1);

use App\DTOs\SessionDTO;
use App\DTOs\TurnDTO;
use App\Services\Packager;
use App\Support\BundleSchema;
use Carbon\Carbon;

function makePackageSession(string $id = 'sess1'): SessionDTO
{
    return new SessionDTO(
        id:               $id,
        title:            'Test Session',
        sourceTool:       'cursor',
        sourceMachineSha: 'deadbeef',
        createdAt:        Carbon::parse('2026-01-15'),
        lastActiveAt:     Carbon::parse('2026-01-15'),
        turns:            collect([
            new TurnDTO(
                role:      'user',
                content:   'Hi',
                timestamp: Carbon::parse('2026-01-15'),
            ),
        ]),
    );
}

it('pack creates a .cbz file at the given path', function (): void {
    withTempDir(function (string $dir): void {
        $packager = new Packager();
        $sessions = collect([makePackageSession()]);
        $cbz      = $dir . '/archive.cbz';

        $packager->pack($sessions, $cbz, null, 'cursor');

        expect(file_exists($cbz))->toBeTrue();
    });
});

it('pack creates a valid ZIP containing bundle.json and manifest.json', function (): void {
    withTempDir(function (string $dir): void {
        $packager = new Packager();
        $cbz      = $dir . '/archive.cbz';

        $packager->pack(collect([makePackageSession()]), $cbz);

        $zip = new ZipArchive();
        $zip->open($cbz);

        expect($zip->locateName('bundle.json'))->not->toBeFalse()
            ->and($zip->locateName('manifest.json'))->not->toBeFalse();

        $zip->close();
    });
});

it('bundle.json passes BundleSchema::validate', function (): void {
    withTempDir(function (string $dir): void {
        $packager = new Packager();
        $cbz      = $dir . '/archive.cbz';

        $packager->pack(collect([makePackageSession()]), $cbz, null, 'cursor');

        $zip     = new ZipArchive();
        $zip->open($cbz);
        $json    = $zip->getFromName('bundle.json');
        $zip->close();

        $data = json_decode($json, true);
        expect(BundleSchema::validate($data))->toBeTrue();
    });
});

it('unpack returns a collection of SessionDTOs', function (): void {
    withTempDir(function (string $dir): void {
        $packager = new Packager();
        $cbz      = $dir . '/archive.cbz';

        $packager->pack(collect([makePackageSession('id-1'), makePackageSession('id-2')]), $cbz);

        $sessions = $packager->unpack($cbz);

        expect($sessions)->toHaveCount(2)
            ->and($sessions->first())->toBeInstanceOf(SessionDTO::class);
    });
});

it('unpack throws RuntimeException on corrupted archive', function (): void {
    withTempDir(function (string $dir): void {
        $badPath = $dir . '/bad.cbz';
        file_put_contents($badPath, 'this is not a zip');

        $packager = new Packager();
        expect(fn() => $packager->unpack($badPath))->toThrow(RuntimeException::class);
    });
});

it('encrypted bundles round-trip and record encryption in the manifest', function (): void {
    withTempDir(function (string $dir): void {
        $packager  = new Packager();
        $encryptor = new \App\Services\Encryptor($dir . '/key');
        $cbz       = $dir . '/secret.cbz';

        $packager->pack(collect([makePackageSession('enc-1')]), $cbz, null, 'cursor', $encryptor);

        $zip = new ZipArchive();
        $zip->open($cbz);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $raw      = (string) $zip->getFromName('bundle.json');
        $zip->close();

        expect($manifest['encrypted'])->toBeTrue()
            ->and($manifest['session_count'])->toBe(1)
            ->and($raw)->not->toContain('enc-1')
            ->and($packager->unpack($cbz, $encryptor)->first()->id)->toBe('enc-1')
            ->and(fn() => $packager->unpack($cbz))->toThrow(RuntimeException::class, 'encrypted');
    });
});

it('unpack reports schema problems as RuntimeException', function (): void {
    withTempDir(function (string $dir): void {
        $cbz = $dir . '/bad-schema.cbz';
        $zip = new ZipArchive();
        $zip->open($cbz, ZipArchive::CREATE);
        $zip->addFromString('bundle.json', '{"version":"1.0"}');
        $zip->close();

        expect(fn() => (new Packager())->unpack($cbz))->toThrow(RuntimeException::class, 'Invalid bundle');
    });
});
