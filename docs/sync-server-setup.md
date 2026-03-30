# Sync Server Setup

Self-host the MoveZ sync server for encrypted cross-machine session storage.

---

## Requirements

- PHP 8.3+ with extensions: `pdo_mysql`, `openssl`, `redis`, `pcntl`
- MySQL 8.0+
- Redis 7+
- Composer 2.x
- Node 20+ (for building frontend assets)

---

## Installation

### 1. Clone and Install Dependencies

```bash
git clone https://github.com/your-org/movez.git
cd movez/web
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

### 2. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```ini
APP_ENV=production
APP_URL=https://your-server.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=movez
DB_USERNAME=movez
DB_PASSWORD=your_secure_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

QUEUE_CONNECTION=redis
```

### 3. Database Setup

```bash
mysql -u root -p -e "CREATE DATABASE movez CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'movez'@'localhost' IDENTIFIED BY 'your_secure_password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON movez.* TO 'movez'@'localhost';"

php artisan migrate --force
```

### 4. Create API User

```php
php artisan tinker
>>> $user = App\Models\User::create(['name' => 'Me', 'email' => 'me@example.com', 'password' => bcrypt('secret')]);
>>> $token = Illuminate\Support\Str::random(40);
>>> $user->update(['api_token' => hash('sha256', $token)]);
>>> echo "Your token: $token";
```

Save the raw token — this is what you pass to `movez sync:push --token=...`.

### 5. Start Laravel Horizon (Queue Worker)

```bash
php artisan horizon
```

For production, use Supervisor:

```ini
[program:movez-horizon]
command=php /path/to/web/artisan horizon
directory=/path/to/web
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/movez-horizon.log
```

### 6. Web Server (Nginx)

```nginx
server {
    listen 443 ssl;
    server_name your-server.com;

    root /path/to/web/public;
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

---

## API Reference

### Authentication

All API endpoints require a Bearer token:

```
Authorization: Bearer YOUR_RAW_TOKEN
```

The server hashes the token with SHA-256 and looks up `users.api_token`.

### Endpoints

#### Push Sessions
```
POST /api/sync/push
Content-Type: application/json
Authorization: Bearer TOKEN

{ "sessions": "<AES-256-GCM encrypted JSON string>" }
```

Response:
```json
{ "status": "ok", "count": 5 }
```

#### Pull Sessions
```
GET /api/sync/pull
Authorization: Bearer TOKEN
```

Response:
```json
{ "sessions": "<AES-256-GCM encrypted JSON string>" }
```

---

## Security Notes

- All session data is AES-256-GCM encrypted **before** leaving the client machine
- The server never has access to plaintext session data
- API tokens are stored as SHA-256 hashes — the raw token is never stored
- Use HTTPS in production — the server should not be accessible over HTTP
- Firewall the Redis port (6379) — it should not be publicly accessible
