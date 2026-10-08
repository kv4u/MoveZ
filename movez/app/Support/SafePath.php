<?php
declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Guards against path traversal when untrusted bundle data (session ids,
 * project names) is used to build file system paths.
 */
final class SafePath
{
    /**
     * Assert that a session id is safe to use as a file name.
     *
     * @throws InvalidArgumentException
     */
    public static function id(string $id): string
    {
        if ($id === '' || $id === '.' || $id === '..' || !preg_match('/^[A-Za-z0-9._-]{1,200}$/', $id)) {
            throw new InvalidArgumentException("Unsafe session id rejected: \"{$id}\"");
        }

        return $id;
    }

    /**
     * Reduce a project name to a single safe directory segment.
     *
     * @throws InvalidArgumentException
     */
    public static function segment(string $name): string
    {
        $segment = basename(str_replace('\\', '/', $name));

        if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, ':')) {
            throw new InvalidArgumentException("Unsafe project name rejected: \"{$name}\"");
        }

        return $segment;
    }
}
