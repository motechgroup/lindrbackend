# LINDR PRODUCTION DEPLOYMENT & ARCHITECTURE MANUAL

## 1. System Requirements & Stack Overview
- **OS**: Standard Linux VPS (Ubuntu 24.04 LTS / Debian 12 / RHEL 9 recommended).
- **Backend Framework**: Laravel 12 on PHP 8.5+.
- **Database**: PostgreSQL 16+ (or managed PostgreSQL instance on AWS RDS / DigitalOcean Managed DB).
- **Cache & Key-Value Store**: Redis 7+.
- **Web Server**: Nginx with HTTP/2 and Let's Encrypt SSL (Certbot).
- **Process Manager**: Supervisor or Systemd for background queue workers.
- **Task Scheduler**: System Cron running `php artisan schedule:run`.
- **Media Storage**: Private local storage for liveness verifications; S3/GCS or local symlink for public media.
- **External Services**:
  - **LiveKit Server**: WebRTC video communication (`LIVEKIT_URL`, `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET`).
  - **Safaricom Daraja**: M-Pesa B2C payout API (`MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_SHORTCODE`, `MPESA_PASSKEY`).
  - **Google OAuth**: Auth client credentials.

---

## 2. Nginx & SSL Setup
Example Nginx Virtual Host (`/etc/nginx/sites-available/lindr`):

```nginx
server {
    listen 80;
    server_name api.lindr.app;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.lindr.app;

    root /var/www/lindr/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/api.lindr.app/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.lindr.app/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

---

## 3. Queue Worker Setup (Supervisor)
`/etc/supervisor/conf.d/lindr-worker.conf`:

```ini
[program:lindr-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/lindr/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/lindr/storage/logs/worker.log
stopwaitsecs=3600
```

---

## 4. System Cron Scheduler
Run `crontab -e -u www-data`:

```cron
* * * * * cd /var/www/lindr && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Environment Variables Audit & Security Checklist
Production secrets **MUST** remain in `/var/www/lindr/.env` on the server and **NEVER** be committed to Git or embedded in Expo.

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=base64:...`
- `APP_URL=https://api.lindr.app`
- `DB_CONNECTION=pgsql`
- `DB_HOST=127.0.0.1` (or managed DB host)
- `DB_PORT=5432`
- `DB_DATABASE=lindr_prod`
- `DB_USERNAME=lindr_user`
- `DB_PASSWORD=...`
- `REDIS_HOST=127.0.0.1`
- `REDIS_PORT=6379`
- `CACHE_STORE=redis`
- `QUEUE_CONNECTION=redis`
- `LIVEKIT_URL=https://livekit.lindr.app`
- `LIVEKIT_API_KEY=...`
- `LIVEKIT_API_SECRET=...`
- `MPESA_ENVIRONMENT=production`
- `MPESA_CONSUMER_KEY=...`
- `MPESA_CONSUMER_SECRET=...`
- `MPESA_SHORTCODE=...`
- `MPESA_PASSKEY=...`

---

## 6. Backup Strategy
1. **PostgreSQL Automated Daily Backups**:
   ```bash
   pg_dump -U lindr_user -h 127.0.0.1 lindr_prod | gzip > /var/backups/lindr/db_$(date +\%Y\%m\%d_\%H\%M\%S).sql.gz
   ```
2. **Media Backup**: Sync `/var/www/lindr/storage/app` to encrypted remote object storage daily.
3. **Retention**: Keep daily backups for 30 days, weekly backups for 90 days.

---

## 7. Rollback Architecture & Procedure
If a production deployment encounters an unexpected issue:

1. **Rollback Code**:
   ```bash
   git checkout PREVIOUS_KNOWN_GOOD_COMMIT
   composer install --no-dev --optimize-autoloader
   ```
2. **Rebuild Cache & Restart Workers**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan queue:restart
   ```
3. **Check System Health**:
   ```bash
   curl -i https://api.lindr.app/api/v1/health
   ```
