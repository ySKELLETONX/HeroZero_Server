# Hero Zero — Production deployment

Turns the dev setup (PHP built-in server + `node server.js`) into a hardened
production stack: **Nginx** (reverse proxy, TLS, rate limiting) → **PHP-FPM**
(Laravel game server) + **Node** (real-time socket), with **MySQL backups** and
**active monitoring**.

> This closes the "dev-server-only" release blocker in `docs/PRODUCTION_AUDIT.md`.
> The remaining blockers (real payment provider, asset/trademark rights) are out
> of scope here.

```
                         ┌──────── Nginx (443, TLS) ─────────┐
   client / browser ───▶ │  /request.php  /beta-api  /js/    │──▶ PHP-FPM (Laravel)
                         │  /socket.io/  (WebSocket upgrade) │──▶ Node socket :8090
                         └───────────────────────────────────┘
   backups (systemd timer, daily)      healthcheck (systemd timer, 2 min)
```

## Files

| Path | Goes to | Purpose |
|---|---|---|
| `nginx/herozero.conf` | `/etc/nginx/sites-available/` | server block: TLS, proxy, rate limit |
| `nginx/rate-limit.conf` | `/etc/nginx/conf.d/` | `limit_req_zone` + WebSocket `map` (http context) |
| `php-fpm/herozero.pool.conf` | `/etc/php/8.3/fpm/pool.d/` | dedicated FPM pool + hardening |
| `systemd/herozero-socket.service` | `/etc/systemd/system/` | Node socket server |
| `systemd/herozero-queue.service` | `/etc/systemd/system/` | Laravel queue worker |
| `systemd/herozero-backup.{service,timer}` | `/etc/systemd/system/` | daily MySQL backup |
| `systemd/herozero-healthcheck.{service,timer}` | `/etc/systemd/system/` | web+socket+db healthcheck |
| `backup/hz-backup.sh` | `/usr/local/bin/` | mysqldump + rotation |
| `monitoring/hz-healthcheck.sh` | `/usr/local/bin/` | active healthcheck + webhook alert |
| `env/*.env.example` | `/etc/herozero/*.env` | secrets/config for the services |

## 1. Prerequisites

```bash
# Debian/Ubuntu
sudo apt install nginx php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
                 php8.3-curl php8.3-zip mysql-server certbot python3-certbot-nginx nodejs
sudo useradd -r -s /usr/sbin/nologin herozero
sudo mkdir -p /var/www/herozero /var/log/herozero /var/backups/herozero /etc/herozero
sudo git clone https://github.com/ySKELLETONX/HeroZero_Server /var/www/herozero
```

## 2. App setup

```bash
cd /var/www/herozero/server-laravel
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan config:cache && php artisan route:cache
php artisan migrate --force            # + seed as needed
sudo chown -R herozero:herozero /var/www/herozero /var/log/herozero

cd /var/www/herozero/socket-server
npm ci --omit=dev
```

## 3. Config & secrets

```bash
# Copy templates, then edit each with real secrets (chmod 600).
for f in laravel socket backup monitoring; do
  sudo cp deploy/env/$f.env.example /etc/herozero/$f.env
done
sudo chmod 600 /etc/herozero/*.env && sudo chown herozero:herozero /etc/herozero/*.env
```

Generate strong secrets and keep `HZ_SOCKET_TOKEN` **identical** in `laravel.env`
and `socket.env`:

```bash
openssl rand -hex 24   # use for HZ_SOCKET_TOKEN, DB pass, etc.
```

Create a **restricted** MySQL user (never use root for the app):

```sql
CREATE USER 'herozero'@'127.0.0.1' IDENTIFIED BY '<strong>';
GRANT SELECT, INSERT, UPDATE, DELETE ON herozero.* TO 'herozero'@'127.0.0.1';
FLUSH PRIVILEGES;
```

## 4. Web server + TLS

```bash
sudo cp deploy/nginx/rate-limit.conf /etc/nginx/conf.d/hz-rate-limit.conf
sudo cp deploy/nginx/herozero.conf   /etc/nginx/sites-available/herozero.conf
sudo ln -s /etc/nginx/sites-available/herozero.conf /etc/nginx/sites-enabled/
sudo cp deploy/php-fpm/herozero.pool.conf /etc/php/8.3/fpm/pool.d/herozero.conf

# Edit server_name / root / cert paths in herozero.conf first, then:
sudo nginx -t
sudo certbot --nginx -d your-game-host.example   # issues + wires TLS
sudo systemctl reload nginx php8.3-fpm
```

Certbot installs a renewal timer automatically (`systemctl list-timers certbot`).

## 5. Services (socket, queue, backups, monitoring)

```bash
sudo cp deploy/backup/hz-backup.sh        /usr/local/bin/ && sudo chmod +x /usr/local/bin/hz-backup.sh
sudo cp deploy/monitoring/hz-healthcheck.sh /usr/local/bin/ && sudo chmod +x /usr/local/bin/hz-healthcheck.sh
sudo cp deploy/systemd/*.{service,timer}  /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now herozero-socket herozero-queue
sudo systemctl enable --now herozero-backup.timer herozero-healthcheck.timer
```

## 6. Verify

```bash
curl -sf https://your-game-host.example/up            && echo "web ok"
curl -sf http://127.0.0.1:8090/health                 && echo "socket ok"
sudo /usr/local/bin/hz-backup.sh                      # one manual backup
sudo /usr/local/bin/hz-healthcheck.sh                 # exits 0 when healthy
sudo systemctl list-timers 'herozero-*'               # backup + healthcheck scheduled
```

Rate limiting is layered: **Nginx** `limit_req` (per-IP, first line) + **Laravel**
named limiters `game`/`auth` (defense-in-depth, tune via `HZ_RATE_*`). A blocked
request returns **HTTP 429**.

## Notes

- The socket server binds to `127.0.0.1` only; Nginx terminates TLS and proxies
  `/socket.io/`. The internal `/push` endpoint is never exposed (Nginx returns 404).
- `disable_functions` in the FPM pool blocks shell exec; relax only if a dependency
  needs it (the app itself does not — `SocketPush` uses cURL).
- Restore a backup: `gunzip < herozero-YYYYMMDD-HHMMSS.sql.gz | mysql herozero`.
- Scale out: run multiple FPM pools / socket instances behind the same Nginx; the
  socket registry is in-memory per process, so pin a user to one socket node (e.g.
  `ip_hash`) or move the registry to Redis if you shard it.
