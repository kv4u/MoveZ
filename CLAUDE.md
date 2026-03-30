# MoveZ — Claude Code Instructions

This is a symlink-equivalent to AGENTS.md. See [AGENTS.md](AGENTS.md) for the full engineering spec.

## Quick Reference for Claude Code

### Running Tests
```bash
# CLI tests (from movez/)
php vendor/bin/pest

# Web tests (from web/)
php artisan test

# TypeScript check (from vscode-extension/)
npx tsc --noEmit
```

### Key Rules
1. `declare(strict_types=1)` in every PHP file
2. All DTOs must be `final readonly`
3. Pest PHP 3 syntax only — no `$this->assert*()`
4. `withoutVite()` on all Inertia HTTP test requests
5. Never use Eloquent in CLI (movez/) code paths

### Directory Roots
- CLI: `movez/` (Laravel Zero)
- Web: `web/` (Laravel 12 + Inertia + Vue 3)
- Extension: `vscode-extension/` (TypeScript)
- CI/CD: `.github/workflows/`
