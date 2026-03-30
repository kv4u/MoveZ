<?php

return [

    'key_path' => env('CB_KEY_PATH', ($_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '') . '/.movez/key'),

    'token_path' => env('CB_TOKEN_PATH', ($_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '') . '/.movez/token'),

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
            'db_file' => 'state.vscdb',
            'db_key'  => 'workbench.panel.aichat.view.aichat.chatdata',
            'format'  => 'sqlite',
        ],
        'claude-code' => [
            'storage' => [
                'Darwin'  => '~/.claude/projects',
                'Linux'   => '~/.claude/projects',
                'Windows' => '%USERPROFILE%/.claude/projects',
            ],
            'format' => 'jsonl',
        ],
        'codex' => [
            'storage' => [
                'Darwin' => '~/.codex/sessions',
                'Linux'  => '~/.codex/sessions',
                'Windows' => '%APPDATA%/codex/sessions',
            ],
            'format' => 'jsonl',
        ],
        'copilot-cli' => [
            'storage' => [
                'Darwin'  => '~/.copilot/sessions',
                'Linux'   => '~/.copilot/sessions',
                'Windows' => '%APPDATA%/copilot/sessions',
            ],
            'format' => 'json',
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
            'storage' => [
                'Darwin'  => '~/.continue',
                'Linux'   => '~/.continue',
                'Windows' => '%APPDATA%/continue',
            ],
            'db_file' => 'sessions.db',
            'format'  => 'sqlite',
        ],
    ],

];
