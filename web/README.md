# MoveZ Web: dashboard and sync server

A Laravel 12 + Inertia + Vue 3 app that provides:

- **Sync API** (`/api/sync/push`, `/api/sync/pull`): stores each user's AES-256-GCM encrypted session payload. Bearer tokens are matched by SHA-256 hash.
- **Dashboard**: projects, sessions, and a migration wizard that builds the `movez transfer` command to run locally.

Deployment: see [docs/sync-server-setup.md](../docs/sync-server-setup.md). Project overview: [main README](../README.md).

## Development

```bash
composer install
npm ci
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed   # demo login demo@movez.test / password, prints dev API tokens
npm run dev            # Vite
php artisan serve      # http://localhost:8000
```

```bash
php artisan test       # Pest feature + unit tests
npm run type-check     # vue-tsc
npm run test:e2e       # Playwright (starts php artisan serve locally; needs the seeded demo user)
php artisan movez:user you@example.com    # create a dashboard user (no public registration)
php artisan movez:token you@example.com   # issue a sync token
```
