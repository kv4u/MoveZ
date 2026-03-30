# MoveZ

**Unified AI coding session transfer — cross-tool AND cross-machine.**

Transfer sessions between Cursor, Windsurf, Claude Code, Codex, Copilot CLI, Cline, and Continue.
Sync encrypted sessions across machines via a self-hosted server.

---

## Quick Start (Desktop App)

The easiest way to use MoveZ is through the desktop app — no terminal needed.

### 1. Install

Download `MoveZ Setup 1.0.0.exe` from [Releases](https://github.com/kv4u/MoveZ/releases) and run the installer.

> The installer bundles PHP and the CLI automatically — no extra dependencies required.

### 2. Open the App

Launch **MoveZ** from the Start Menu. You'll see a sidebar with these sections:

| Section | What it does |
|---|---|
| **Dashboard** | Overview of detected AI tools and session counts |
| **Sessions** | Browse all sessions from all your AI tools |
| **Migrate** | Transfer sessions from one tool to another |
| **Sync** | Push/pull encrypted sessions to a sync server |
| **Doctor** | Check that everything is working |
| **Settings** | Configure CLI path, PHP path, sync server |

### 3. Export Sessions (Backup)

1. Go to **Sessions**
2. Select a tool tab (e.g. Cursor, Claude Code)
3. Check the sessions you want to export
4. Click **Export Selected** → choose a save location
5. A `.cbz` bundle file is created — this is your portable backup

### 4. Import Sessions (Restore)

1. Go to **Sessions**
2. Click **Import**
3. Select your `.cbz` bundle file
4. Choose the target tool (e.g. Cursor, Windsurf)
5. Optionally remap project paths if your folder structure changed
6. Click **Import** — sessions appear in the target tool

### 5. Transfer Between Tools

1. Go to **Migrate**
2. Select **From** tool (e.g. Cursor) and **To** tool (e.g. Claude Code)
3. Set the project directory
4. Click **Start Migration**
5. Sessions are read from the source and written to the target tool

### 6. Run Doctor (Troubleshooting)

Click **Doctor** to verify:
- PHP is available and the right version
- All AI tool storage directories are detected
- SQLite extensions are loaded
- Encryption key exists

---

## Quick Start (CLI)

For power users who prefer the terminal.

### Install

```bash
# Download the PHAR
curl -L https://github.com/kv4u/MoveZ/releases/latest/download/movez.phar -o movez
chmod +x movez
sudo mv movez /usr/local/bin/movez

# Verify installation
movez doctor
```

On Windows (with PHP installed):
```powershell
# Download movez.phar to a folder in your PATH
php movez.phar doctor
```

### Common Workflows

**Backup all Cursor sessions:**
```bash
movez export --tool=cursor --output=cursor-backup.cbz
```

**Restore to a new machine:**
```bash
movez import --input=cursor-backup.cbz --tool=cursor
```

**Transfer Cursor sessions to Claude Code:**
```bash
movez transfer --from=cursor --to=claude-code --project=/path/to/project
```

**Remap paths when restoring on a different machine:**
```bash
movez import --input=backup.cbz --tool=cursor \
  --from-path=/old/machine/projects \
  --to-path=/new/machine/projects
```

**Encrypted backup:**
```bash
movez export --tool=cursor --output=backup.cbz --encrypt
# Encryption key is auto-generated at ~/.movez/key
```

**List sessions without exporting:**
```bash
movez list-sessions --tool=cursor
movez list-sessions --tool=claude-code --json
```

**Sync across machines (requires self-hosted server):**
```bash
# On machine A — push
movez sync:push --token=YOUR_TOKEN --server=https://your-server.com

# On machine B — pull
movez sync:pull --token=YOUR_TOKEN --server=https://your-server.com --tool=cursor
```

---

## All CLI Commands

| Command | Description |
|---|---|
| `movez export` | Export sessions to a `.cbz` bundle |
| `movez import` | Import sessions from a `.cbz` bundle |
| `movez transfer` | Export + import in one step |
| `movez package` | Package raw session files into a `.cbz` |
| `movez unpack` | Extract a `.cbz` bundle |
| `movez list-sessions` | List detected sessions |
| `movez sync:push` | Push encrypted sessions to sync server |
| `movez sync:pull` | Pull sessions from sync server |
| `movez doctor` | Check system requirements |

Use `--help` on any command for full options:
```bash
movez export --help
```

---

## Supported Tools

| Tool | Read | Write | Format |
|---|---|---|---|
| Cursor | Yes | Yes | SQLite + JSONL (3-layer) |
| Windsurf | Yes | Yes | SQLite |
| Claude Code | Yes | Yes | JSONL |
| Codex | Yes | Yes | JSONL |
| Copilot CLI | Yes | Yes | JSON |
| Cline | Yes | — | JSON |
| Continue | Yes | — | SQLite |

---

## Importing into Cursor (Important!)

Cursor uses a 3-layer storage system. MoveZ writes to all three so imported sessions appear correctly in the sidebar with full conversation history.

### Before You Import

**You must open each project in Cursor at least once before importing.** Cursor creates a workspace storage directory the first time you open a project — MoveZ needs this directory to register sessions in the sidebar.

### Step-by-Step

1. **Close Cursor** completely (File → Exit, not just close window)
2. **Open each project folder** you want to import sessions for in Cursor — just open it briefly, then close Cursor again. This creates the workspace storage entry.
3. **Run the import:**
   ```bash
   movez import --input=backup.cbz --tool=cursor
   ```
   Or use the desktop app: Sessions → Import → select your `.cbz` file → choose Cursor as target.
4. **Reopen Cursor** on any project — your imported sessions will appear in the chat history sidebar.

### What Gets Written

| Layer | Location | Purpose |
|---|---|---|
| 1. JSONL transcript | `~/.cursor/projects/<path>/agent-transcripts/<id>/` | Raw conversation text |
| 2. Global SQLite | `%APPDATA%/Cursor/User/globalStorage/state.vscdb` | Session metadata + individual messages (composerData + bubbleId entries) |
| 3. Workspace SQLite | `%APPDATA%/Cursor/User/workspaceStorage/<hash>/state.vscdb` | Sidebar registration (makes session clickable) |

### If Sessions Don't Appear

- **Session not in sidebar?** → You didn't open the project in Cursor before importing. Open it once, close Cursor, re-run the import.
- **Session shows but content is empty?** → Close Cursor fully and reopen. Cursor caches session state in memory.
- **Sessions from a different machine?** → Use `--from-path` and `--to-path` to remap project paths:
  ```bash
  movez import --input=backup.cbz --tool=cursor \
    --from-path="D:/Projects" \
    --to-path="C:/Work/Projects"
  ```

### Limitations

- **AI won't remember context** — Cursor uses an internal protobuf blob (`conversationState`) to feed conversation history to the model. MoveZ can't generate this format, so the AI won't "remember" imported conversations. You get full visual history of all messages, but to continue the conversation with context, start a new chat and reference the history.
- **Workspace hash is opaque** — Cursor generates workspace directory hashes using an internal algorithm. MoveZ can only register sessions for projects that have been opened (and thus have an existing hash). There's no way to pre-create workspace directories.

---

## Bundle Format (.cbz)

MoveZ uses `.cbz` (Compressed Bundle Zip) files as a portable bundle format:

```
bundle.cbz (ZIP archive)
└── bundle.json
    ├── version: 1
    ├── source_tool: "cursor"
    ├── exported_at: "2026-03-26T..."
    └── sessions: [
          { id, title, project, turns: [{role, content, timestamp}...] }
        ]
```

Bundles can optionally be encrypted with AES-256-GCM.

---

## Architecture

```
contextbridge/    ← Laravel Zero CLI (builds to movez.phar)
electron-app/     ← Electron + Vue 3 desktop app
vscode-extension/ ← VS Code extension (TypeScript)
web/              ← Laravel 12 + Inertia.js web dashboard
.github/          ← GitHub Actions CI/CD
```

See [AGENTS.md](AGENTS.md) for the full engineering specification.

---

## Building from Source

### Requirements

- PHP 8.2+ with extensions: pdo_sqlite, openssl, zip, mbstring
- Composer 2.x
- Node 20+

### Build the Desktop App

```bash
# 1. Install dependencies
cd contextbridge && composer install && cd ..
cd electron-app && npm install && cd ..

# 2. Download PHP runtime (Windows)
# Place php.exe + DLLs in electron-app/resources/php/

# 3. Build PHAR
cd contextbridge
php -d phar.readonly=0 box.phar compile
cp contextbridge.phar ../electron-app/resources/movez.phar
cd ..

# 4. Build Electron installer
cd electron-app
npm run package
# Output: electron-app/dist/MoveZ Setup 1.0.0.exe
```

Or use the included build script (Windows):
```batch
build.bat
```

### Run Tests

```bash
# CLI tests
cd contextbridge && php vendor/bin/pest

# Web tests
cd web && php artisan test

# TypeScript check
cd vscode-extension && npx tsc --noEmit
```

---

## Security

- All synced data is encrypted with AES-256-GCM before leaving your machine
- Encryption key stored at `~/.movez/key` with 0600 permissions
- API tokens stored as SHA-256 hashes in the database
- No telemetry, no external calls except to your configured sync server

---

## License

MIT — see [LICENSE](LICENSE)
