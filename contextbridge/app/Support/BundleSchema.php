<?php
declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class BundleSchema
{
    private const VERSION = '1.0';

    private const REQUIRED_KEYS = ['version', 'sessions', 'source_tool', 'exported_at'];

    /**
     * Validate a decoded bundle array. Returns true on success.
     *
     * @throws InvalidArgumentException on any validation failure
     */
    public static function validate(array $data): bool
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidArgumentException(
                    "Bundle is missing required key: \"{$key}\""
                );
            }
        }

        if (!is_array($data['sessions'])) {
            throw new InvalidArgumentException('Bundle "sessions" must be an array');
        }

        return true;
    }

    /**
     * Build a manifest array for a new bundle.
     */
    public static function makeManifest(string $sourceTool, string $machineSha): array
    {
        return [
            'version'            => self::VERSION,
            'source_tool'        => $sourceTool,
            'source_machine_sha' => $machineSha,
            'exported_at'        => (new \DateTimeImmutable())->format(\DateTimeInterface::ISO8601),
        ];
    }
}
