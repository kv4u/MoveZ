<?php
declare(strict_types=1);

namespace App\Services;

use App\DTOs\ProjectConfigDTO;
use App\DTOs\SessionDTO;
use App\Support\BundleSchema;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

final class Packager
{
    /**
     * Pack a collection of sessions into a portable .cbz archive.
     *
     * @param Collection<int, SessionDTO> $sessions
     */
    public function pack(
        Collection       $sessions,
        string           $outputPath,
        ?ProjectConfigDTO $config     = null,
        string           $sourceTool = 'unknown',
    ): void {
        $machineSha = $this->machineSha();

        $bundleData = [
            'version'     => '1.0',
            'source_tool' => $sourceTool,
            'exported_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ISO8601),
            'sessions'    => $sessions->map(fn(SessionDTO $s) => $s->toArray())->values()->all(),
        ];

        BundleSchema::validate($bundleData);

        $manifest = BundleSchema::makeManifest($sourceTool, $machineSha);

        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create archive: {$outputPath}");
        }

        $zip->addFromString('bundle.json',   json_encode($bundleData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('manifest.json', json_encode($manifest,   JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($config !== null) {
            $zip->addFromString('config.json', json_encode($config->toArray(), JSON_PRETTY_PRINT));
        }

        $zip->close();
    }

    /**
     * Unpack a .cbz archive and return the sessions it contains.
     *
     * @return Collection<int, SessionDTO>
     * @throws RuntimeException on corrupted archive
     */
    public function unpack(string $cbzPath): Collection
    {
        if (!file_exists($cbzPath)) {
            throw new RuntimeException("Archive not found: {$cbzPath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($cbzPath) !== true) {
            throw new RuntimeException("Cannot open archive: {$cbzPath}");
        }

        $bundleJson = $zip->getFromName('bundle.json');
        $zip->close();

        if ($bundleJson === false) {
            throw new RuntimeException('Archive is missing bundle.json');
        }

        $data = json_decode($bundleJson, true);

        if (!is_array($data)) {
            throw new RuntimeException('bundle.json contains invalid JSON');
        }

        BundleSchema::validate($data);

        return collect($data['sessions'])->map(fn(array $s) => SessionDTO::fromArray($s));
    }

    private function machineSha(): string
    {
        $hostname = (string) gethostname();
        return substr(hash('sha256', $hostname), 0, 16);
    }
}
