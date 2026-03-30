# VS Code Extension Setup

Install and configure the MoveZ VS Code/Cursor extension.

---

## Installation

### From VS Code Marketplace

Search "MoveZ" in the Extensions panel and click Install.

### From VSIX (GitHub Releases)

```bash
code --install-extension movez-*.vsix
```

Or in VS Code: `Ctrl+Shift+P` → "Extensions: Install from VSIX..."

---

## Configuration

Open VS Code Settings (`Ctrl+,`) and search "movez":

| Setting | Default | Description |
|---|---|---|
| `movez.cliPath` | `movez` | Path to the movez CLI binary |
| `movez.serverUrl` | `""` | URL of your sync server (e.g., `https://sync.example.com`) |
| `movez.token` | `""` | Your API token for the sync server |

### Example `settings.json`

```json
{
  "movez.cliPath": "/usr/local/bin/movez",
  "movez.serverUrl": "https://sync.example.com",
  "movez.token": "your_raw_api_token_here"
}
```

---

## Features

### Session Tree View

The **MoveZ Sessions** panel in the Activity Bar shows all AI sessions detected on your machine. It shells out to:

```bash
movez list-sessions --json
```

Expand each tool node to see individual sessions.

### Commands

Access via `Ctrl+Shift+P`:

| Command | Description |
|---|---|
| MoveZ: Export Session | Export sessions from a tool to a .cbz bundle |
| MoveZ: Import Session | Import a .cbz bundle into a tool |
| MoveZ: Open Migration Wizard | Open the web-based 5-step migration wizard |
| MoveZ: Sync Push | Push encrypted sessions to sync server |
| MoveZ: Sync Pull | Pull sessions from sync server |
| MoveZ: Refresh Sessions | Refresh the session tree view |

### Migration Wizard

The migration wizard opens a WebView panel. If `movez.serverUrl` is configured, it loads the wizard from your server. Otherwise it shows an embedded wizard.

Steps:
1. **Source Tool** — select where sessions are coming from
2. **Target Tool** — select where to import sessions
3. **Project** — optionally select a specific project
4. **Paths** — configure from/to path remapping for cross-machine migration
5. **Confirm** — review and execute

---

## Development

### Build from Source

```bash
cd vscode-extension
npm install
npx tsc --noEmit   # type check
npm run compile    # build
npx vsce package   # produce .vsix
```

### Run Tests

```bash
npm test
```

### Debug in VS Code

1. Open `vscode-extension/` in VS Code
2. Press `F5` to launch Extension Development Host
3. The extension will be loaded in the new window

---

## Troubleshooting

**"movez: command not found"**
- Set `movez.cliPath` to the full path of your CLI binary
- Run `movez doctor` to verify the CLI is working

**Sessions panel is empty**
- Ensure the CLI is installed and working: `movez doctor`
- Check that AI tools have session data in their default storage paths
- Click the Refresh button in the Sessions panel

**Sync push/pull fails with 401**
- Verify `movez.token` matches the raw token you created on the server
- Verify `movez.serverUrl` is correct and the server is reachable
