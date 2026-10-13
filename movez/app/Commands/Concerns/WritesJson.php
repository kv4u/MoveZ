<?php
declare(strict_types=1);

namespace App\Commands\Concerns;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Machine-readable output must bypass the console formatter: line() treats
 * "<info>…</info>"-style text as styles (stripping it) and "\<" as an escape
 * (eating the backslash), which corrupted session text and produced invalid
 * JSON for the desktop app and VS Code extension.
 */
trait WritesJson
{
    protected function writeJson(mixed $data): void
    {
        $this->output->writeln(
            (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            OutputInterface::OUTPUT_RAW,
        );
    }
}
