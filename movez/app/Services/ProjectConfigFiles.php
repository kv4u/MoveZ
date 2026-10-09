<?php
declare(strict_types=1);

namespace App\Services;

use App\DTOs\ProjectConfigDTO;

/**
 * Reads and writes the per-project AI configuration files that travel with
 * a bundle (bundle.json's sibling config.json).
 */
final class ProjectConfigFiles
{
    /** ProjectConfigDTO field => file name in the project root */
    public const FILES = [
        'cursorRules' => '.cursorrules',
        'claudeMd'    => 'CLAUDE.md',
        'mcpJson'     => '.mcp.json',
        'agentsMd'    => 'AGENTS.md',
    ];

    /** Larger files are skipped — these are instruction files, not data. */
    private const MAX_BYTES = 1024 * 1024;

    /** Collect the config files present in a project, or null when there are none. */
    public function collect(string $projectPath): ?ProjectConfigDTO
    {
        $values = [];

        foreach (self::FILES as $field => $file) {
            $path = rtrim($projectPath, '/\\') . DIRECTORY_SEPARATOR . $file;
            if (is_file($path) && filesize($path) <= self::MAX_BYTES) {
                $values[$field] = (string) file_get_contents($path);
            }
        }

        return $values === [] ? null : new ProjectConfigDTO(...$values);
    }

    /**
     * Write config files into a project. Existing files are kept unless $overwrite.
     *
     * @return array<string, string> file name => "written" | "kept existing"
     */
    public function apply(ProjectConfigDTO $config, string $projectPath, bool $overwrite = false): array
    {
        if (!is_dir($projectPath)) {
            mkdir($projectPath, 0755, true);
        }

        $result = [];

        foreach (self::FILES as $field => $file) {
            $content = $config->{$field};
            if ($content === null) {
                continue;
            }

            $path = rtrim($projectPath, '/\\') . DIRECTORY_SEPARATOR . $file;
            if (file_exists($path) && !$overwrite) {
                $result[$file] = 'kept existing';
                continue;
            }

            file_put_contents($path, $content);
            $result[$file] = 'written';
        }

        return $result;
    }
}
