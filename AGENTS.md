# MoveZ — Complete Engineering Spec
## For AI Coding Agents: Read Every Section Before Writing Any Code

> **Save this file as `AGENTS.md` at project root.**
> Also exists as `CLAUDE.md` for Claude Code auto-detection.
> Works with: Cursor Agent · Claude Code · Codex · GitHub Copilot Workspace

---

## 0. Mission Briefing

**MoveZ** — a unified platform that fills the gap no existing tool covers:

| Existing Tool | Cross-Tool | Cross-Machine | Tools Covered |
|---|---|---|---|
| `cli-continues` | ✅ | ❌ | 14 agents |
| `cursor-chat-transfer` | ❌ | ✅ | Cursor only |
| `claude-conversation-extractor` | ❌ | ✅ | Claude Code only |
| **MoveZ** | ✅ | ✅ | 14+ agents |

**The gap:** No tool handles BOTH cross-tool handoff AND cross-machine migration in one product.

### Four Components
1. **CLI** (`movez`) — export, import, transfer, package, unpack, sync
2. **Web Dashboard** — visual session browser + migration wizard (Laravel 12 + Inertia + Vue 3)
3. **Sync Server** — self-hosted AES-256-GCM encrypted storage (Laravel Horizon + Redis)
4. **VS Code/Cursor Extension** — in-editor sidebar + one-click migration (TypeScript)

---

## 1. Absolute Rules

### ✅ Always
- `declare(strict_types=1)` in every PHP file
- `readonly` classes for all DTOs
- `match` over `switch`, named arguments, constructor promotion
- Tests before implementation (TDD — red → green → refactor)
- Pest PHP 3.x syntax only (no PHPUnit `$this->assert...`)
- Thin controllers — all logic in `Services/` or `Actions/`
- `Collection<int, SessionDTO>` type hints — never plain `array` for objects
- One class per file, PSR-4 namespacing

### ❌ Never
- Hard-code file system paths — use `config/movez.php`
- Skip interface implementation
- Use `dd()`, `dump()`, `var_dump()` in non-test code
- Mix Eloquent into CLI-only code paths
- Proceed to next phase with failing tests

---

## 2. Tech Stack

```
CLI             → Laravel Zero (latest)
Web Framework   → Laravel 12 + Inertia.js + Vue 3
Styling         → Tailwind CSS 4 + shadcn-vue
Testing         → Pest PHP 3
Encryption      → PHP OpenSSL AES-256-GCM (built-in, no package)
Queue           → Laravel Horizon + Redis
SQLite (local)  → PHP PDO (no Eloquent for CLI)
DB (server)     → MySQL 8
Extension       → TypeScript + VS Code Extension API
Build/Release   → GitHub Actions + PHAR
```

---

## 3. Directory Tree

```
movez/         ← Laravel Zero CLI
├── app/
│   ├── Contracts/     ParserInterface.php, WriterInterface.php
│   ├── DTOs/          SessionDTO, TurnDTO, FileDiffDTO, ProjectConfigDTO
│   ├── Parsers/       CursorParser, WindsurfParser, ClaudeCodeParser, CodexParser,
│   │                  CopilotCliParser, ClineParser, ContinueParser
│   ├── Writers/       CursorWriter, WindsurfWriter, ClaudeCodeWriter, CodexWriter,
│   │                  CopilotCliWriter, AbstractWriter
│   ├── Services/      Encryptor, PathMapper, Packager, ToolDetector, SyncClient
│   ├── Support/       BundleSchema, PlatformPaths
│   └── Commands/      ExportCommand, ImportCommand, TransferCommand, PackageCommand,
│                      UnpackCommand, Sync/SyncPushCommand, Sync/SyncPullCommand,
│                      ListCommand, DoctorCommand
├── config/movez.php
└── tests/             Unit/ + Feature/ (Pest PHP 3)

web/                   ← Laravel 12 Dashboard + Sync Server
├── app/
│   ├── Http/Controllers/  DashboardController, ProjectController, SessionController,
│   │                      MigrationController, SyncController
│   ├── Http/Middleware/   ApiTokenMiddleware (Bearer → SHA-256 lookup)
│   └── Models/            User, Project, AiSession, SyncEvent
├── database/migrations/   users, projects, ai_sessions, sync_events, api_token
├── resources/js/
│   ├── Pages/             Dashboard.vue, Projects/Index.vue, Projects/Show.vue,
│   │                      Sessions/Show.vue, Migration/Wizard.vue
│   └── Components/        ToolBadge.vue, SyncStatus.vue, DiffViewer.vue,
│                          TurnBlock.vue, SessionCard.vue
└── e2e/                   Playwright tests

vscode-extension/      ← TypeScript VS Code extension
├── src/               extension.ts, SessionTreeProvider.ts, MigrationPanel.ts
└── package.json
```

---

## 4. Key Interfaces

### ParserInterface
```php
interface ParserInterface {
    public function detect(string $projectPath): bool;
    public function getStoragePath(string $projectPath): string;
    /** @return Collection<int, SessionDTO> */
    public function parse(string $projectPath): Collection;
    public function toolName(): string;
}
```

### WriterInterface
```php
interface WriterInterface {
    /** @param Collection<int, SessionDTO> $sessions */
    public function write(Collection $sessions, string $projectPath): void;
    /** @return Collection<int, SessionDTO> */
    public function remapPaths(string $from, string $to, Collection $sessions): Collection;
    public function toolName(): string;
}
```

---

## 5. Encryption (AES-256-GCM)

Format: `base64(iv[12] + tag[16] + ciphertext)`

Key stored at `~/.movez/key` with chmod 0600.

---

## 6. Bundle Format (.cbz)

ZIP archive containing:
- `bundle.json` — array of SessionDTO serialized via `toArray()`
- `manifest.json` — `{ version, source_tool, machine_sha, exported_at, session_count }`
- `config.json` (optional) — ProjectConfigDTO

Required keys validated by `BundleSchema::validate()`: `version`, `sessions`, `source_tool`, `exported_at`

---

## 7. Supported Tools

| Tool | Format | Storage |
|---|---|---|
| cursor | SQLite state.vscdb | workspaceStorage/ |
| windsurf | SQLite state.vscdb | workspaceStorage/ |
| claude-code | JSONL | ~/.claude/projects/ |
| codex | JSONL | ~/.codex/sessions/ |
| copilot-cli | JSON | ~/.copilot/sessions/ |
| cline | JSON (glob) | ~/.vscode/extensions/saoudrizwan.claude-dev-*/ |
| continue | SQLite sessions.db | ~/.continue/ |

---

## 8. API Routes (Sync Server)

```
POST /api/sync/push    — Bearer token, body: { sessions: encrypted_json }
GET  /api/sync/pull    — Bearer token, returns: { sessions: encrypted_json }
```

Auth: `ApiTokenMiddleware` — Bearer token → `hash('sha256', $token)` → match `users.api_token`

---

## 9. VS Code Extension Commands

- `movez.exportSession`
- `movez.importSession`
- `movez.openMigrationWizard`
- `movez.syncPush`
- `movez.syncPull`
- `movez.refreshSessions`

Settings: `movez.cliPath`, `movez.serverUrl`, `movez.token`
