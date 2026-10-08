# MoveZ CLI

The `movez` command-line tool, built with [Laravel Zero](https://laravel-zero.com). It reads, writes, bundles, encrypts and syncs AI coding sessions.

User documentation lives in the [main README](../README.md).

## Development

```bash
composer install
php movez list                 # available commands
php movez doctor
php vendor/bin/pest            # tests
php movez app:build movez.phar # → builds/movez.phar
```

## Layout

| Path | Contents |
|---|---|
| `app/Commands` | CLI commands (`export`, `import`, `transfer`, `show`, `sync:*`, …) |
| `app/Parsers` | One parser per tool, all producing `SessionDTO`s |
| `app/Writers` | One writer per writable tool |
| `app/Services` | `Encryptor` (AES-256-GCM), `Packager` (.cbz), `SyncClient`, `PathMapper`, `ToolDetector` |
| `app/Support` | `BundleSchema`, `PlatformPaths`, `SafePath`, `ProjectFilter`, `ContentFlattener` |
| `config/movez.php` | Per-OS storage paths for every tool, key and token locations |

## Environment variables

| Variable | Purpose |
|---|---|
| `MOVEZ_KEY_PATH` | Encryption key location (default `~/.movez/key`) |
| `MOVEZ_TOKEN_PATH` | Sync token file (default `~/.movez/token`) |
| `MOVEZ_TOKEN` | Sync token (preferred over `--token`) |
| `MOVEZ_SERVER_URL` | Default sync server |

Rules for contributors (strict types, readonly DTOs, Pest-only tests, no Eloquent) are in [AGENTS.md](../AGENTS.md).
