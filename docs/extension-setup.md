# VS Code / Cursor Extension Setup

The MoveZ extension is a front end for the `movez` CLI. It lists, exports, imports, transfers and syncs sessions from inside the editor.

---

## 1. Install the CLI

Download `movez.phar` from the [latest release](https://github.com/kv4u/MoveZ/releases/latest) (needs PHP 8.2+), or use the PHP and PHAR bundled with the Windows desktop app.

```bash
php movez.phar doctor
```

## 2. Install the extension

Download `movez-vscode.vsix` from the [latest release](https://github.com/kv4u/MoveZ/releases/latest), then either:

```bash
code --install-extension movez-vscode.vsix      # or: cursor --install-extension ...
```

or run **Extensions: Install from VSIX…** from the Command Palette.

## 3. Configure

Open Settings (`Ctrl+,`) and search for "movez":

| Setting | Default | Description |
|---|---|---|
| `movez.cliPath` | `movez` | `movez` executable on your PATH, or the full path to `movez.phar` |
| `movez.phpPath` | `php` | PHP used to run a `.phar` |
| `movez.serverUrl` | | Your sync server, e.g. `https://sync.example.com` |

Example using the Windows desktop app's bundled runtime:

```json
{
  "movez.cliPath": "C:\\Program Files\\MoveZ\\resources\\movez.phar",
  "movez.phpPath": "C:\\Program Files\\MoveZ\\resources\\php\\php.exe",
  "movez.serverUrl": "https://sync.example.com"
}
```

For sync, run **MoveZ: Set Sync Token**. The token is stored in your OS keychain through VS Code SecretStorage, not in `settings.json`. It is passed to the CLI as the `MOVEZ_TOKEN` environment variable.

---

## Usage

### Sessions view

Click the MoveZ icon in the activity bar. The view lists sessions belonging to the **open folder**, newest first, with tool and turn count. Use the refresh button after working in another tool. If the CLI can't be found or fails, the view says so. Details are in the **MoveZ** output channel (View → Output → MoveZ).

### Commands (`Ctrl+Shift+P`)

| Command | What it does |
|---|---|
| **MoveZ: Export Sessions** | Pick a tool, choose where to save a `.cbz` bundle, optionally encrypt it. Only this project's sessions are exported. |
| **MoveZ: Import Sessions** | Pick a `.cbz` or `.json` bundle and a target tool (Cursor, Claude Code, Codex, Copilot CLI). Encrypted bundles are detected automatically. |
| **MoveZ: Migration Wizard** | Opens a form: source tool, target tool, project folder, optional path remap. Runs `movez transfer` locally. |
| **MoveZ: Sync Push** | Encrypts and uploads this project's sessions to your sync server. |
| **MoveZ: Sync Pull** | Downloads, decrypts and imports sessions into the chosen tool. |
| **MoveZ: Set Sync Token** | Saves (or clears, if left empty) the sync API token. |
| **MoveZ: Refresh Sessions** | Reloads the Sessions view. |

Sync needs the same `~/.movez/key` on every machine. See [sync-server-setup.md](sync-server-setup.md).

---

## Building from source

```bash
cd vscode-extension
npm ci
npm test                                  # type check
npx vsce package --no-dependencies        # → movez-<version>.vsix
```
