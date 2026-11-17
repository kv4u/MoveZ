<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Where Cline keeps its tasks (github.com/cline/cline).
 *
 * Current Cline stores new sessions in an SDK store under ~/.cline/data/sessions
 * (one folder per session, indexed by an SQLite database). Tasks from before
 * that store use the "legacy" layout, which current Cline still lists:
 *
 *   <root>/state/taskHistory.json                      the task list
 *   <root>/tasks/<id>/api_conversation_history.json    the conversation
 *   <root>/tasks/<id>/ui_messages.json                 what the chat view renders
 *
 * where <root> is ~/.cline/data or a VS Code-family editor's global storage
 * folder for the extension (…/User/globalStorage/saoudrizwan.claude-dev).
 */
final class ClineLocations
{
    /** ~/.cline/data, honouring Cline's CLINE_DATA_DIR and CLINE_DIR overrides. */
    public static function dataDir(): string
    {
        $dataDir = trim((string) getenv('CLINE_DATA_DIR'));
        if ($dataDir !== '') {
            return $dataDir;
        }

        $clineDir = trim((string) getenv('CLINE_DIR'));
        if ($clineDir !== '') {
            return rtrim($clineDir, '/\\') . DIRECTORY_SEPARATOR . 'data';
        }

        return PlatformPaths::expand((string) config('movez.tools.cline.storage.' . PlatformPaths::configKey(), ''));
    }

    /** The SDK session store (one folder per session). */
    public static function sessionDir(): string
    {
        $sessionDir = trim((string) getenv('CLINE_SESSION_DATA_DIR'));

        return $sessionDir !== '' ? $sessionDir : self::dataDir() . DIRECTORY_SEPARATOR . 'sessions';
    }

    /** @return string[] The extension's global storage folder in each installed VS Code-family editor. */
    public static function editorDirs(): array
    {
        $pattern = (string) config('movez.tools.cline.editor_storage.' . PlatformPaths::configKey(), '');

        return $pattern === '' ? [] : PlatformPaths::globExpand($pattern);
    }

    /** @return string[] Existing legacy roots, ~/.cline/data first (Cline reads them in this order). */
    public static function legacyRoots(): array
    {
        $roots = is_dir(self::dataDir()) ? [self::dataDir()] : [];

        return array_values(array_unique(array_merge($roots, self::editorDirs())));
    }

    public static function exists(): bool
    {
        return self::legacyRoots() !== [] || is_dir(self::sessionDir());
    }
}
