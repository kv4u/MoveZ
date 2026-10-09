<?php

declare(strict_types=1);

$home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? (getenv('HOME') ?: (getenv('USERPROFILE') ?: sys_get_temp_dir()));

return [

    'key_path' => env('MOVEZ_KEY_PATH', $home . '/.movez/key'),

    'token_path' => env('MOVEZ_TOKEN_PATH', $home . '/.movez/token'),

    // Sync defaults; overridable per command with --server / --token.
    // Prefer MOVEZ_TOKEN over --token: arguments are visible in process lists.
    'server_url' => env('MOVEZ_SERVER_URL'),

    'token' => env('MOVEZ_TOKEN'),

    'tools' => [
        'cursor' => [
            // New Cursor (1.0+) stores agent transcripts in ~/.cursor/projects/
            'storage' => [
                'Darwin'  => '~/.cursor/projects',
                'Linux'   => '~/.cursor/projects',
                'Windows' => '%USERPROFILE%/.cursor/projects',
            ],
            // Global state.vscdb — contains cursorDiskKV (composerData + bubbleId entries)
            'global_db' => [
                'Darwin'  => '~/Library/Application Support/Cursor/User/globalStorage/state.vscdb',
                'Linux'   => '~/.config/Cursor/User/globalStorage/state.vscdb',
                'Windows' => '%APPDATA%/Cursor/User/globalStorage/state.vscdb',
            ],
            // Per-workspace storage — needed to register sessions in sidebar
            'workspace_storage' => [
                'Darwin'  => '~/Library/Application Support/Cursor/User/workspaceStorage',
                'Linux'   => '~/.config/Cursor/User/workspaceStorage',
                'Windows' => '%APPDATA%/Cursor/User/workspaceStorage',
            ],
            'format'  => 'jsonl',
        ],
        'windsurf' => [
            'storage' => [
                'Darwin'  => '~/Library/Application Support/Windsurf/User/workspaceStorage',
                'Linux'   => '~/.config/Windsurf/User/workspaceStorage',
                'Windows' => '%APPDATA%/Windsurf/User/workspaceStorage',
            ],
            'format'    => 'protobuf',
            // Cascade conversations are encrypted — read/write not supported yet
            'supported' => false,
        ],
        'claude-code' => [
            'storage' => [
                'Darwin'  => '~/.claude/projects',
                'Linux'   => '~/.claude/projects',
                'Windows' => '%USERPROFILE%/.claude/projects',
            ],
            // Claude desktop app session registry — imported sessions must be
            // registered here to show up in the desktop app's history.
            'desktop_registry' => [
                'Darwin'  => '~/Library/Application Support/Claude/claude-code-sessions',
                'Linux'   => '~/.config/Claude/claude-code-sessions',
                'Windows' => '%APPDATA%/Claude/claude-code-sessions',
            ],
            'desktop_versions' => [
                'Darwin'  => '~/Library/Application Support/Claude/claude-code',
                'Linux'   => '~/.config/Claude/claude-code',
                'Windows' => '%APPDATA%/Claude/claude-code',
            ],
            'default_model'   => env('MOVEZ_CLAUDE_MODEL', 'claude-opus-4-6'),
            'default_version' => '2.1.78',
            'format' => 'jsonl',
        ],
        'codex' => [
            'storage' => [
                'Darwin' => '~/.codex/sessions',
                'Linux'  => '~/.codex/sessions',
                'Windows' => '%USERPROFILE%/.codex/sessions',
            ],
            'format' => 'jsonl',
        ],
        'copilot-cli' => [
            // One directory per session: session-state/<uuid>/events.jsonl + workspace.yaml
            'storage' => [
                'Darwin'  => '~/.copilot/session-state',
                'Linux'   => '~/.copilot/session-state',
                'Windows' => '%USERPROFILE%/.copilot/session-state',
            ],
            'format' => 'jsonl-events',
        ],
        'cline' => [
            'storage' => [
                'Darwin'  => '~/.vscode/extensions/saoudrizwan.claude-dev-*/data/tasks',
                'Linux'   => '~/.vscode/extensions/saoudrizwan.claude-dev-*/data/tasks',
                'Windows' => '%USERPROFILE%/.vscode/extensions/saoudrizwan.claude-dev-*/data/tasks',
            ],
            'format' => 'json',
        ],
        'continue' => [
            // sessions/<sessionId>.json plus a sessions.json index (CONTINUE_GLOBAL_DIR overrides ~/.continue)
            'storage' => [
                'Darwin'  => '~/.continue/sessions',
                'Linux'   => '~/.continue/sessions',
                'Windows' => '%USERPROFILE%/.continue/sessions',
            ],
            'format' => 'json',
        ],
    ],

];
