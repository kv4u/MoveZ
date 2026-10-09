# MoveZ for VS Code & Cursor

Browse, export, import and sync your AI coding sessions (Cursor, Claude Code, Codex, Copilot CLI, Cline, Continue) without leaving the editor.

This extension is a front end for the [MoveZ CLI](https://github.com/kv4u/MoveZ). Install the CLI first.

## Requirements

- The `movez` CLI on your `PATH`, **or** `movez.phar` plus PHP 8.2+ (with `pdo_sqlite`, `openssl`, `zip`, `mbstring`).
  - Download `movez.phar` from [Releases](https://github.com/kv4u/MoveZ/releases/latest).
  - The Windows desktop app ships its own PHP: point `movez.phpPath` at `…\MoveZ\resources\php\php.exe` and `movez.cliPath` at `…\MoveZ\resources\movez.phar`.

## Features

- **Sessions view** (MoveZ icon in the activity bar): sessions belonging to the open folder, newest first. Use the refresh button after working in another tool.
- **MoveZ: Export Sessions**: save sessions for this project as a portable `.cbz` bundle, optionally encrypted with your MoveZ key.
- **MoveZ: Import Sessions**: load a `.cbz` or `.json` bundle into Cursor, Claude Code, Codex or Copilot CLI.
- **MoveZ: Migration Wizard**: copy sessions from one tool into another on this machine, with optional path remapping.
- **MoveZ: Sync Push / Sync Pull**: move encrypted sessions between machines through your self-hosted MoveZ server.
- **MoveZ: Set Sync Token**: stores the sync API token in your OS keychain (VS Code SecretStorage).

CLI output and errors are logged to the **MoveZ** output channel.

## Settings

| Setting | Default | Description |
|---|---|---|
| `movez.cliPath` | `movez` | Path to the `movez` executable or `movez.phar` |
| `movez.phpPath` | `php` | PHP used to run a `.phar` |
| `movez.serverUrl` | | Base URL of your MoveZ sync server |
| `movez.token` | | Deprecated: use **MoveZ: Set Sync Token** instead |

## Sync between machines

Bundles and sync payloads are encrypted with the key at `~/.movez/key`. Every machine you pull on needs the same key: run `movez key:export` on one machine and `movez key:import` on the others, or decryption will fail.

## Install from VSIX

Download `movez-vscode.vsix` from [Releases](https://github.com/kv4u/MoveZ/releases/latest), then run **Extensions: Install from VSIX…**.

## License

MIT
