<?php
declare(strict_types=1);

namespace App\Support;

final class PlatformPaths
{
    /**
     * Expand a path that may contain ~ or %APPDATA% / %USERPROFILE% placeholders.
     */
    public static function expand(string $path): string
    {
        $os = PHP_OS_FAMILY;

        if ($os === 'Windows') {
            $path = str_replace('%APPDATA%', (string) getenv('APPDATA'), $path);
            $path = str_replace('%USERPROFILE%', (string) getenv('USERPROFILE'), $path);
            // forward-slash normalisation is not needed on Windows (PHP handles both)
        } else {
            $home = getenv('HOME') ?: '';
            $path = str_replace('~', $home, $path);
        }

        return $path;
    }

    /**
     * Expand a glob-style path (e.g. containing *) and return all matching paths.
     *
     * @return string[]
     */
    public static function globExpand(string $pattern): array
    {
        $expanded = self::expand($pattern);
        $matches  = glob($expanded, GLOB_ONLYDIR | GLOB_NOSORT);

        return $matches !== false ? $matches : [];
    }

    /**
     * Return the current OS family: 'Darwin', 'Linux', or 'Windows'.
     */
    public static function osfamily(): string
    {
        return PHP_OS_FAMILY;
    }

    /**
     * Return the platform key used in the config ('Darwin', 'Linux', 'Windows').
     */
    public static function configKey(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin'  => 'Darwin',
            'Windows' => 'Windows',
            default   => 'Linux',
        };
    }
}
