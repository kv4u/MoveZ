<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Turns the lossy folder names tools use for projects back into real paths.
 *
 * Cursor ("c-Work-my-app") and Claude Code ("C--Work-my-app", "-home-me-my-app")
 * replace path separators — and other punctuation — with "-", so "my-app" and
 * "my/app" encode identically. Guessing from the string alone picks the wrong
 * folder name for hyphenated projects. When the project still exists on this
 * machine we can resolve it unambiguously by walking the file system and
 * matching each directory's encoded name.
 */
final class EncodedPathResolver
{
    /** @var array<string, ?string> */
    private static array $cache = [];

    /** Real path for an encoded project folder name, or null if it can't be found here. */
    public static function resolve(string $encoded): ?string
    {
        if (array_key_exists($encoded, self::$cache)) {
            return self::$cache[$encoded];
        }

        return self::$cache[$encoded] = self::walk($encoded);
    }

    /** Folder name of the resolved path, or null when unresolvable. */
    public static function projectName(string $encoded): ?string
    {
        $path = self::resolve($encoded);

        return $path === null ? null : basename($path);
    }

    /** Encode a single path segment the way the tools do (anything but letters/digits → "-"). */
    public static function encodeSegment(string $name): string
    {
        return (string) preg_replace('/[^A-Za-z0-9]/', '-', $name);
    }

    private static function walk(string $encoded): ?string
    {
        // Windows drive: "c-rest" (Cursor) or "C--rest" (Claude Code)
        if (preg_match('/^([A-Za-z])--?(.+)$/', $encoded, $m) && PHP_OS_FAMILY === 'Windows') {
            $root = strtoupper($m[1]) . ':/';
            $rest = $m[2];
        } else {
            $root = '/';
            $rest = ltrim($encoded, '-');
        }

        if (!is_dir($root) || $rest === '') {
            return null;
        }

        $tokens  = explode('-', $rest);
        $current = rtrim($root, '/');

        while ($tokens !== []) {
            $children = self::childDirectories($current === '' ? '/' : $current);
            $matched  = null;

            // Prefer the longest directory name that matches the next tokens
            for ($take = count($tokens); $take >= 1; $take--) {
                // Normalise both sides: Claude Code turns "_" and "." into "-", Cursor keeps them
                $key  = self::encodeSegment(implode('-', array_slice($tokens, 0, $take)));
                $name = $children[$key] ?? $children[strtolower($key)] ?? null;
                if ($name !== null) {
                    $matched = [$name, $take];
                    break;
                }
            }

            if ($matched === null) {
                return null;
            }

            $current .= '/' . $matched[0];
            $tokens   = array_slice($tokens, $matched[1]);
        }

        return $current;
    }

    /** @return array<string, string> encoded name => real directory name */
    private static function childDirectories(string $dir): array
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return [];
        }

        $map = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir($dir . '/' . $entry)) {
                continue;
            }

            $key = self::encodeSegment($entry);
            // Case-insensitive file systems: match "MoveZ" for "movez" too
            $map[$key] ??= $entry;
            $map[strtolower($key)] ??= $entry;
        }

        return $map;
    }

    /** @internal for tests */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
