<?php
declare(strict_types=1);

namespace App\Parsers;

use RuntimeException;

abstract class JsonlParser
{
    /**
     * Read a JSONL file and return an array of decoded objects.
     * Streams line by line (large sessions can exceed 60 MB) and skips
     * malformed lines — e.g. a half-written last line of a live session.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function readJsonlFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException("JSONL file not found: {$path}");
        }

        $fh = fopen($path, 'r');
        if ($fh === false) {
            throw new RuntimeException("Cannot open JSONL file: {$path}");
        }

        $result = [];

        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            $result[] = $decoded;
        }

        fclose($fh);
        return $result;
    }

    protected function machineSha(): string
    {
        return substr(hash('sha256', (string) gethostname()), 0, 16);
    }
}
