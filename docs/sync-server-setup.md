# Sync Server Setup

Self-host the MoveZ sync server for encrypted cross-machine session storage.

The server stores **only ciphertext**. The CLI encrypts sessions with AES-256-GCM using `~/.movez/key` before uploading, so the server can't read your sessions, and neither can anyone who compromises it.

---

## Requirements

- PHP 8.2+ with `pdo_mysql` (or `pdo_sqlite`), `openssl`, `mbstring`
- MySQL 8.0+, or SQLite for a single-user setup
- Composer 2.x
- Node 20+ (to build the dashboard assets)
- Optional: Redis 7+ and `pcntl`/`posix` (Linux/macOS) if you run Laravel Horizon. Sync itself doesn't use queues.

> **Windows:** `laravel/horizon` requires `ext-pcntl`/`ext-posix`, which don't exist on Windows. Install with
> `composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` and don't run Horizon.

---

## Installation

### 1. Clone and install

```bash
git clone https://github.com/kv4u/MoveZ.git
cd MoveZ/web
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### 2. Configure

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sync.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=movez
DB_USERNAME=movez
DB_PASSWORD=your_secure_password
```

For a single-user server you can keep `DB_CONNECTION=sqlite` and run `touch database/database.sqlite`.

### 3. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE movez CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'movez'@'localhost' IDENTIFIED BY 'your_secure_password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON movez.* TO 'movez'@'localhost';"

php artisan migrate --force
```

### 4. Create a user and an API token

```bash
php artisan tinker --execute="App\Models\User::create(['name' => 'Me', 'email' => 'me@example.com', 'password' => 'change-me']);"
php artisan movez:token me@example.com
```

`movez:token` prints the token **once**. Only its SHA-256 hash is stored. Running it again replaces the old token.

### 5. Web server (Nginx)

```nginx
server {
    listen 443 ssl;
    server_name sync.example.com;

    root /path/to/MoveZ/web/public;
    index index.php;

    ssl_certificate     /etc/ssl/certs/your-cert.pem;
    ssl_certificate_key /etc/ssl/private/your-key.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

> The web dashboard has **no login yet**. Restrict it (VPN, IP allow-list or HTTP basic auth on everything except `/api/*`) until authentication lands. The `/api/sync/*` endpoints are protected by API tokens.

---

## Using it from the CLI

On every machine:

```bash
export MOVEZ_SERVER_URL=https://sync.example.com
export MOVEZ_TOKEN=<token from movez:token>   # or save it in ~/.movez/token
```

Every machine needs the same encryption key. **Without it, pulled sessions can't be decrypted.**

```bash
movez key:export > movez.key            # first machine
movez key:import --file=movez.key       # every other machine
movez key:fingerprint                   # must print the same value everywhere
```

```bash
movez sync:push --tool=claude-code                  # upload (replaces the previous upload)
movez sync:pull --tool=claude-code --project=.      # download, decrypt, import
```

---

## API reference

All endpoints need a Bearer token. The server hashes it with SHA-256 and matches `users.api_token`.

```
Authorization: Bearer <token>
```

### Push

```
POST /api/sync/push
Content-Type: application/json

{ "sessions": "<base64 AES-256-GCM ciphertext>", "count": 12 }
```

Response:

```json
{ "status": "ok", "count": 12 }
```

Each push replaces the user's previous payload.

### Pull

```
GET /api/sync/pull
```

Response (`sessions` is `null` until the first push):

```json
{ "sessions": "<base64 AES-256-GCM ciphertext>", "count": 12 }
```

Errors: `401` for a missing or invalid token, `422` for a missing `sessions` field.

---

## Security notes

- Session content is encrypted on the client. The server never sees plaintext or the key.
- API tokens are stored as SHA-256 hashes, and `api_token` is never serialized.
- Serve over HTTPS only.
- If you use Redis, keep it firewalled.
