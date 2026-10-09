<?php
declare(strict_types=1);

namespace App\Services;

use App\DTOs\ProjectConfigDTO;
use App\DTOs\SessionDTO;
use App\Support\BundleSchema;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class Packager
{
    /**
     * Pack a collection of sessions into a portable .cbz archive.
     * When an Encryptor is given, bundle.json is stored AES-256-GCM encrypted.
     *
     * @param Collection<int, SessionDTO> $sessions
     */
    public function pack(
        Collection        $sessions,
        string            $outputPath,
        ?ProjectConfigDTO $config     = null,
        string            $sourceTool = 'unknown',
        ?Encryptor        $encryptor  = null,
    ): void {
        $bundleData = [
            'version'     => BundleSchema::VERSION,
            'source_tool' => $sourceTool,
            'exported_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'sessions'    => $sessions->map(fn(SessionDTO $s) => $s->toArray())->values()->all(),
        ];

        BundleSchema::validate($bundleData);

        $bundleJson = (string) json_encode($bundleData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encryptor !== null) {
            $bundleJson = $encryptor->encrypt($bundleJson);
        }

        $manifest = BundleSchema::makeManifest($sourceTool, $this->machineSha(), $sessions->count(), $encryptor !== null);

        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create archive: {$outputPath}");
        }

        $zip->addFromString('bundle.json',   $bundleJson);
        $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($config !== null) {
            // Config files (CLAUDE.md etc.) are as private as the sessions — encrypt them too
            $configJson = (string) json_encode($config->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $zip->addFromString('config.json', $encryptor !== null ? $encryptor->encrypt($configJson) : $configJson);
        }

        $zip->close();
    }

    /**
     * Unpack a .cbz archive and return the sessions it contains.
     *
     * @return Collection<int, SessionDTO>
     * @throws RuntimeException on a missing, corrupted, invalid or undecryptable archive
     */
    public function unpack(string $cbzPath, ?Encryptor $encryptor = null): Collection
    {
        if (!file_exists($cbzPath)) {
            throw new RuntimeException("Archive not found: {$cbzPath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($cbzPath) !== true) {
            throw new RuntimeException("Cannot open archive: {$cbzPath}");
        }

        $bundleJson   = $zip->getFromName('bundle.json');
        $manifestJson = $zip->getFromName('manifest.json');
        $zip->close();

        if ($bundleJson === false) {
            throw new RuntimeException('Archive is missing bundle.json');
        }

        $manifest  = is_string($manifestJson) ? json_decode($manifestJson, true) : null;
        $encrypted = is_array($manifest) && ($manifest['encrypted'] ?? false) === true;

        if ($encrypted) {
            if ($encryptor === null) {
                throw new RuntimeException('Archive is encrypted — an encryption key is required');
            }
            $bundleJson = $encryptor->decrypt($bundleJson);
        }

        $data = json_decode($bundleJson, true);

        if (!is_array($data)) {
            throw new RuntimeException('bundle.json contains invalid JSON');
        }

        try {
            BundleSchema::validate($data);

            return collect($data['sessions'])->map(fn(array $s) => SessionDTO::fromArray($s));
        } catch (InvalidArgumentException|\TypeError|\ErrorException $e) {
            throw new RuntimeException('Invalid bundle: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Read the optional project config (CLAUDE.md, rules, MCP config) from a .cbz archive.
     *
     * @throws RuntimeException when the archive can't be opened or decrypted
     */
    public function unpackConfig(string $cbzPath, ?Encryptor $encryptor = null): ?ProjectConfigDTO
    {
        $zip = new ZipArchive();
        if ($zip->open($cbzPath) !== true) {
            throw new RuntimeException("Cannot open archive: {$cbzPath}");
        }

        $configJson   = $zip->getFromName('config.json');
        $manifestJson = $zip->getFromName('manifest.json');
        $zip->close();

        if ($configJson === false) {
            return null;
        }

        $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;
        if (is_array($manifest) && ($manifest['encrypted'] ?? false) === true) {
            if ($encryptor === null) {
                throw new RuntimeException('Archive is encrypted — an encryption key is required');
            }
            $configJson = $encryptor->decrypt($configJson);
        }

        $data = json_decode($configJson, true);

        return is_array($data) ? ProjectConfigDTO::fromArray($data) : null;
    }

    private function machineSha(): string
    {
        return substr(hash('sha256', (string) gethostname()), 0, 16);
    }
}
