<h1 align="center">MoveZ</h1>

<p align="center">
  <strong>Move your AI coding sessions between tools <em>and</em> between machines.</strong><br>
  Cursor · Claude Code · Codex · Copilot CLI · Cline · Continue
</p>

<p align="center">
  <a href="https://github.com/kv4u/MoveZ/actions/workflows/ci.yml"><img src="https://github.com/kv4u/MoveZ/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://github.com/kv4u/MoveZ/releases/latest"><img src="https://img.shields.io/github/v/release/kv4u/MoveZ?sort=semver" alt="Latest release"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777bb4.svg" alt="PHP 8.2+">
</p>

---

## Why MoveZ?

Your conversations with AI coding assistants hold real project context. Switching tools or laptops usually means losing it.

| | Cross-tool | Cross-machine | Tools covered |
|---|:---:|:---:|---|
| cli-continues | ✅ | ❌ | 14 agents |
| cursor-chat-transfer | ❌ | ✅ | Cursor only |
| claude-conversation-extractor | ❌ | ✅ | Claude Code only |
| **MoveZ** | ✅ | ✅ | 6 tools (read), 4 tools (write) |

With MoveZ you can:

- **Back up** sessions to a portable `.cbz` bundle, optionally AES-256-GCM encrypted.
- **Transfer** sessions from one tool to another, e.g. Cursor → Claude Code.
- **Migrate** to a new machine, remapping project paths (`D:\Projects` → `C:\Work`).
- **Sync** through a self-hosted server. Sessions are encrypted before they leave your machine.

---

## Get MoveZ

Everything is on the [latest release](https://github.com/kv4u/MoveZ/releases/latest):

| Download | For | Needs |
|---|---|---|
| `MoveZ-Setup-<version>.exe` | Windows desktop app | Nothing, PHP and the CLI are bundled |
| `movez.phar` | CLI on macOS, Linux, Windows | PHP 8.2+ with `pdo_sqlite`, `openssl`, `zip`, `mbstring` |
| `movez-vscode.vsix` | VS Code / Cursor extension | The CLI (above) |

### CLI install

```bash
curl -L https://github.com/kv4u/MoveZ/releases/latest/download/movez.phar -o movez
chmod +x movez
sudo mv movez /usr/local/bin/movez
movez doctor
```

On Windows, run it through PHP: `php movez.phar doctor`.

`movez doctor` checks PHP and its extensions, your encryption key, and which AI tools it finds on this machine.

---

## Quick start

```bash
# See what's on this machine
movez list-sessions
movez list-sessions --tool=claude-code --project=. --json

# Back up all Cursor sessions to a bundle
movez export --tool=cursor --output=cursor-backup.cbz

# Back up only this project's Claude Code sessions, encrypted
movez export --tool=claude-code --project=. --output=claude.cbz --encrypt

# Restore a bundle into Claude Code on a new machine, fixing paths
movez import --input=cursor-backup.cbz --tool=claude-code \
  --project=/home/me/work/my-app \
  --from-path="D:/Projects" --to-path="/home/me/work"

# Copy sessions from one tool to another in one step
movez transfer --from=cursor --to=claude-code --project=/path/to/project

# Read one full session
movez show --tool=claude-code --id=<session-id>
```

`--project` limits a command to sessions whose project folder matches the given path. Leave it out to include every project.

---

## Commands

| Command | What it does | Key options |
|---|---|---|
| `list-sessions` | List detected sessions | `--tool`, `--project`, `--json` |
| `show` | Print one session with all turns | `--tool`, `--id`, `--json` |
| `export` | Write sessions to a `.cbz` bundle (or `.json`) | `--tool` (default `auto`), `--output`, `--project`, `--encrypt` |
| `import` | Load a bundle into a tool | `--input`, `--tool`, `--project`, `--from-path`, `--to-path` |
| `transfer` | Export + import in one step | `--from`, `--to`, `--project`, `--from-path`, `--to-path` |
| `package` | Turn a `.json` session list into a `.cbz` | `--input`, `--output`, `--encrypt` |
| `unpack` | Extract a `.cbz` into one JSON file per session | `--input`, `--output` |
| `sync:push` | Encrypt and upload sessions to your server | `--server`, `--tool`, `--project` |
| `sync:pull` | Download, decrypt and import sessions | `--server`, `--tool`, `--project`, `--from-path`, `--to-path` |
| `doctor` | Check requirements and detected tools | |

Run `movez <command> --help` for details.

---

## Supported tools

| Tool | Read | Write | Storage |
|---|:---:|:---:|---|
| Cursor | ✅ | ✅ | `~/.cursor/projects` transcripts + `state.vscdb` |
| Claude Code | ✅ | ✅ | `~/.claude/projects/*.jsonl` (+ Claude desktop app registry) |
| Codex CLI | ✅ | ✅ | `~/.codex/sessions/YYYY/MM/DD/rollout-*.jsonl` |
| Copilot CLI | ✅ | ✅ | `~/.copilot/sessions/*.json` |
| Cline | ✅ | — | VS Code extension `tasks/<id>/api_conversation_history.json` |
| Continue | ✅ | — | `~/.continue/sessions.db` |
| Windsurf | — | — | Not supported yet: Cascade stores conversations in an encrypted format |

### Importing into Cursor

Cursor only shows sessions in its sidebar for workspaces it already knows about.

1. Open each target project in Cursor once, then **quit Cursor completely**.
2. Run `movez import --input=backup.cbz --tool=cursor --project=/path/to/project`.
3. Reopen Cursor. The imported chats appear in the history sidebar.

MoveZ writes the transcript, the global `state.vscdb` entries and the workspace registration. The full conversation is visible, but Cursor's internal model context (`conversationState`) can't be recreated. To continue a conversation, start a new chat and reference the old one.

### Importing into Claude Code

Sessions are written under `~/.claude/projects/<encoded-path>/`. On Windows they are also registered with the Claude desktop app, so they appear in its history. Re-importing a session updates it rather than duplicating it.

---

## Sync between machines

1. **Run a sync server.** See [docs/sync-server-setup.md](docs/sync-server-setup.md). Then issue yourself a token:
   ```bash
   php artisan movez:token you@example.com
   ```
2. **Configure each machine.** Prefer environment variables, because command-line arguments are visible to other processes:
   ```bash
   export MOVEZ_SERVER_URL=https://sync.example.com
   export MOVEZ_TOKEN=<token>          # or save it in ~/.movez/token
   ```
3. **Share the encryption key.** Sessions are encrypted with `~/.movez/key`, which is created on your first encrypted export or push. **Copy that file to every machine you pull on.** Without it, nothing can be decrypted, including by the server.
4. **Push and pull:**
   ```bash
   movez sync:push --tool=claude-code                    # machine A
   movez sync:pull --tool=claude-code --project=~/work/app \
     --from-path="C:/Users/me/work" --to-path="/home/me/work"   # machine B
   ```

---

## Desktop app (Windows)

Install `MoveZ-Setup-<version>.exe` and launch **MoveZ**:

| Page | What it does |
|---|---|
| Dashboard | Detected tools and session counts |
| Sessions | Browse, read, export and import sessions |
| Migrate | Tool-to-tool transfer with path remapping |
| Sync | Push and pull through your sync server |
| Doctor | Environment checks |
| Settings | CLI/PHP paths and sync server |

## VS Code / Cursor extension

Install `movez-vscode.vsix` with **Extensions: Install from VSIX…**. It adds a Sessions view for the open project, plus Export, Import, Migration Wizard and Sync commands. The sync token is kept in your OS keychain. See [docs/extension-setup.md](docs/extension-setup.md).

## Web dashboard

The sync server also serves a dashboard: projects, sessions, and a migration wizard that builds the exact `movez transfer` command for you. It is meant for a private, single-user deployment; the dashboard has no login yet (see Roadmap).

---

## Bundle format (`.cbz`)

A `.cbz` is a ZIP archive:

| File | Contents |
|---|---|
| `bundle.json` | `{ version, source_tool, exported_at, sessions: [...] }`, AES-256-GCM encrypted when `manifest.encrypted` is true |
| `manifest.json` | `{ version, source_tool, machine_sha, exported_at, session_count, encrypted }` |
| `config.json` | Optional project config (rules, `CLAUDE.md`, MCP config) |

Each session has `id, title, project, source_tool, created_at, last_active_at, turn_count, turns[]`. Each turn has `role, content, timestamp, files_referenced, file_diffs, reasoning_trace, tool_calls`.

Encrypted data uses the layout `base64(iv[12] ‖ tag[16] ‖ ciphertext)`.

---

## Repository layout

```
movez/             Laravel Zero CLI → movez.phar
web/               Laravel 12 + Inertia + Vue 3 dashboard and sync server
electron-app/      Electron + Vue 3 Windows desktop app (bundles PHP + movez.phar)
vscode-extension/  VS Code / Cursor extension (TypeScript)
docs/              Sync server and extension guides
```

See [AGENTS.md](AGENTS.md) for the engineering spec and coding rules.

## Building from source

Requirements: PHP 8.2+ (`pdo_sqlite`, `openssl`, `zip`, `mbstring`), Composer 2, Node 20+.

```bash
# CLI
cd movez && composer install
php movez doctor
php movez app:build movez.phar          # → movez/builds/movez.phar

# Web
cd web && composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate && php artisan migrate

# Extension
cd vscode-extension && npm ci && npx vsce package --no-dependencies

# Desktop app (Windows)
build.bat   # needs movez/box.phar and PHP in electron-app/resources/php/
```

Pushing a `v*` tag runs the release workflow, which builds and publishes the PHAR, the VSIX and the Windows installer.

### Tests

```bash
cd movez && php vendor/bin/pest            # CLI
cd web && php artisan test                 # web
cd web && npm run type-check               # web frontend types
cd vscode-extension && npm test            # extension type check
cd electron-app && npm run typecheck       # desktop app type check
```

---

## Security

- Sessions are encrypted client-side with AES-256-GCM before export or sync. The server stores only ciphertext.
- The key lives at `~/.movez/key` (0600 on macOS/Linux) and never leaves your machines unless you copy it.
- API tokens are stored server-side as SHA-256 hashes.
- No telemetry. MoveZ only talks to the sync server you configure.

## Roadmap

- Windsurf support (blocked on its encrypted Cascade storage)
- Login and per-user scoping for the web dashboard
- Server-side migration jobs (Horizon)
- macOS and Linux desktop builds

## License

MIT, see [LICENSE](LICENSE).
