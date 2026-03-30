<?php
declare(strict_types=1);

namespace App\Parsers;

use RuntimeException;

abstract class JsonlParser
{
    /**
     * Read a JSONL file and return an array of decoded objects.
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

        $result      = [];
        $lineNumber  = 0;

        while (($line = fgets($fh)) !== false) {
            $lineNumber++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                fclose($fh);
                throw new RuntimeException(
                    "Invalid JSON on line {$lineNumber} of {$path}: " . json_last_error_msg()
                );
            }
            $result[] = $decoded;
        }

        fclose($fh);
        return $result;
    }
}
