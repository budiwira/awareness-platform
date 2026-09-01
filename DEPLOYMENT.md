# Deployment Guide

Production deployment guide untuk Cyber Security Awareness Platform.

## Prerequisites

### Server Requirements
- **OS**: Ubuntu 22.04 LTS atau Debian 12 (64-bit)
- **RAM**: Minimum 4GB (8GB recommended)
- **CPU**: 2 cores minimum (4 cores recommended)
- **Storage**: 50GB SSD minimum
- **Network**: Public IP dengan domain name

### Software Stack
- **PHP**: 8.2 atau higher dengan extensions:
  - `php8.2-fpm`, `php8.2-cli`, `php8.2-pgsql`, `php8.2-mbstring`
  - `php8.2-xml`, `php8.2-curl`, `php8.2-zip`, `php8.2-bcmath`
  - `php8.2-intl`, `php8.2-gd`, `php8.2-redis`
- **PostgreSQL**: 15 atau higher
- **Node.js**: 18 LTS atau higher
- **Composer**: 2.x
- **Redis**: 7.x (untuk queue dan cache)
- **Nginx**: 1.24 atau higher
- **Supervisor**: Untuk queue workers
- **Certbot**: Untuk SSL certificates (Let's Encrypt)

## Server Setup

### 1. System Update
```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Install PHP 8.2
```bash
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y php8.2-fpm php8.2-cli php8.2-pgsql php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl \
  php8.2-gd php8.2-redis
```

### 3. Install PostgreSQL 15
```bash
sudo apt install -y postgresql-15 postgresql-contrib-15

# Start dan enable service
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

### 4. Install Node.js 18
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

### 5. Install Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### 6. Install Redis
```bash
sudo apt install -y redis-server

# Configure Redis untuk production
sudo nano /etc/redis/redis.conf
# Set: maxmemory 512mb
# Set: maxmemory-policy allkeys-lru

sudo systemctl restart redis
sudo systemctl enable redis
```

### 7. Install Nginx
```bash
sudo apt install -y nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

### 8. Install Supervisor
```bash
sudo apt install -y supervisor
sudo systemctl enable supervisor
```

## Database Setup

### 1. Create Database dan Roles

```bash
sudo -u postgres psql
```

```sql
-- Create database
CREATE DATABASE awareness_platform;

-- Create owner role (untuk migrations)
CREATE USER owner_user WITH PASSWORD 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON DATABASE awareness_platform TO owner_user;
ALTER DATABASE awareness_platform OWNER TO owner_user;

-- Create app role (untuk runtime)
CREATE USER app_user WITH PASSWORD 'STRONG_PASSWORD_HERE';
GRANT CONNECT ON DATABASE awareness_platform TO app_user;

-- Connect ke database
\c awareness_platform

-- Grant schema privileges
GRANT USAGE ON SCHEMA public TO app_user;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_user;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_user;

-- Set default privileges untuk future tables
ALTER DEFAULT PRIVILEGES FOR ROLE owner_user IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app_user;

ALTER DEFAULT PRIVILEGES FOR ROLE owner_user IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO app_user;

\q
```

### 2. Configure PostgreSQL untuk Production

```bash
sudo nano /etc/postgresql/15/main/postgresql.conf
```

Recommended settings:
```
max_connections = 100
shared_buffers = 1GB
effective_cache_size = 3GB
maintenance_work_mem = 256MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
work_mem = 10MB
min_wal_size = 1GB
max_wal_size = 4GB
```

```bash
sudo systemctl restart postgresql
```

## Application Setup

### 1. Create Deploy User
```bash
sudo adduser deploy
sudo usermod -aG www-data deploy
```

### 2. Clone Repository
```bash
sudo mkdir -p /var/www
sudo chown deploy:deploy /var/www

sudo su - deploy
cd /var/www
git clone https://github.com/organization/awareness-lab.git
cd awareness-lab
```

### 3. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
npm ci
```

### 4. Configure Environment
```bash
cp .env.example .env
nano .env
```

Production `.env` configuration:
```env
APP_NAME="Awareness Platform"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=

LOG_CHANNEL=daily
LOG_LEVEL=warning

# Database - App Role (Runtime)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=awareness_platform
DB_USERNAME=app_user
DB_PASSWORD=STRONG_PASSWORD_HERE

# Database - Owner Role (Migrations)
DB_CONNECTION_OWNER=pgsql_owner
DB_HOST_OWNER=127.0.0.1
DB_PORT_OWNER=5432
DB_DATABASE_OWNER=awareness_platform
DB_USERNAME_OWNER=owner_user
DB_PASSWORD_OWNER=STRONG_PASSWORD_HERE

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail (configure sesuai SMTP provider)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### 5. Generate Application Key
```bash
php artisan key:generate
```

### 6. Run Migrations
```bash
php artisan migrate --database=pgsql_owner --force
```

### 7. Seed Plans (Required)
```bash
php artisan db:seed --class=PlanSeeder --force
```

**PENTING**: Jangan run `DatabaseSeeder` di production (contains demo data).

### 8. Build Frontend Assets
```bash
npm run build
```

### 9. Optimize Laravel
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 10. Create Storage Link
```bash
php artisan storage:link
```

### 11. Set Permissions
```bash
sudo chown -R deploy:www-data /var/www/awareness-lab
sudo chmod -R 755 /var/www/awareness-lab

# Storage dan cache writable
sudo chmod -R 775 /var/www/awareness-lab/storage
sudo chmod -R 775 /var/www/awareness-lab/bootstrap/cache
```

## Nginx Configuration

### 1. Create Server Block

```bash
sudo nano /etc/nginx/sites-available/awareness-platform
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/awareness-lab/public;
    
    index index.php;
    
    charset utf-8;
    
    # SSL certificates (akan diisi oleh Certbot)
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    
    # SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Logs
    access_log /var/log/nginx/awareness-platform-access.log;
    error_log /var/log/nginx/awareness-platform-error.log;
    
    # Client body size (untuk file upload)
    client_max_body_size 20M;
    
    # Location blocks
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    # Deny access ke hidden files
    location ~ /\. {
        deny all;
    }
    
    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
}
```

### 2. Enable Site
```bash
sudo ln -s /etc/nginx/sites-available/awareness-platform /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## SSL Certificate (Let's Encrypt)

### 1. Install Certbot
```bash
sudo apt install -y certbot python3-certbot-nginx
```

### 2. Obtain Certificate
```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

Follow prompts:
- Email: admin@yourdomain.com
- Agree to Terms of Service
- Redirect HTTP to HTTPS: Yes

### 3. Auto-Renewal
Certbot automatically creates cron job. Verify:
```bash
sudo systemctl status certbot.timer
```

Test renewal:
```bash
sudo certbot renew --dry-run
```

## Queue Worker (Supervisor)

### 1. Create Supervisor Config
```bash
sudo nano /etc/supervisor/conf.d/awareness-platform-worker.conf
```

```ini
[program:awareness-platform-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/awareness-lab/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/awareness-lab/storage/logs/worker.log
stopwaitsecs=3600
```

### 2. Start Worker
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start awareness-platform-worker:*
```

### 3. Check Status
```bash
sudo supervisorctl status
```

## Scheduled Tasks (Cron)

### 1. Edit Crontab untuk Deploy User
```bash
crontab -e
```

Add line:
```
* * * * * cd /var/www/awareness-lab && php artisan schedule:run >> /dev/null 2>&1
```

### 2. Verify Scheduled Tasks
```bash
php artisan schedule:list
```

## Performance Optimization

### 1. PHP-FPM Configuration
```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

Optimize:
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

```bash
sudo systemctl restart php8.2-fpm
```

### 2. OPcache Configuration
```bash
sudo nano /etc/php/8.2/fpm/conf.d/10-opcache.ini
```

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.fast_shutdown=1
```

```bash
sudo systemctl restart php8.2-fpm
```

### 3. Redis Configuration untuk Cache
Laravel automatically uses Redis jika `CACHE_DRIVER=redis`.

## Security Hardening

### 1. Firewall (UFW)
```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### 2. Disable Root Login
```bash
sudo nano /etc/ssh/sshd_config
```

Set:
```
PermitRootLogin no
PasswordAuthentication no
```

```bash
sudo systemctl restart ssh
```

### 3. Fail2Ban (Optional)
```bash
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
```

### 4. File Permissions Lock
```bash
sudo chmod 600 /var/www/awareness-lab/.env
sudo chown deploy:deploy /var/www/awareness-lab/.env
```

## Post-Deployment Checklist

- [ ] `.env` configured dengan `APP_ENV=production` dan `APP_DEBUG=false`
- [ ] `APP_KEY` generated
- [ ] Database migrations run via `pgsql_owner` connection
- [ ] `PlanSeeder` executed (plans exist di database)
- [ ] Frontend assets built (`npm run build`)
- [ ] Laravel optimized (`config:cache`, `route:cache`, `view:cache`)
- [ ] Storage link created
- [ ] Permissions set (storage writable)
- [ ] Nginx configured dengan SSL
- [ ] SSL certificate obtained dan auto-renewal active
- [ ] Supervisor queue workers running
- [ ] Cron scheduled tasks active
- [ ] Firewall configured
- [ ] Root login disabled
- [ ] `.env` file permission `600`
- [ ] Test login dengan seeded Super Admin
- [ ] Create first production tenant via Super Admin
- [ ] Verify RLS working (tenant isolation test)
- [ ] Smoke test: Create user, assign training, take quiz
- [ ] Monitor logs untuk errors

## Backup Strategy

### 1. Database Backup Script
```bash
sudo nano /usr/local/bin/backup-awareness-db.sh
```

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/awareness-platform"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_NAME="awareness_platform"
DB_USER="owner_user"

mkdir -p $BACKUP_DIR

# Dump database
PGPASSWORD="OWNER_PASSWORD_HERE" pg_dump -h localhost -U $DB_USER -F c -b -v -f "$BACKUP_DIR/db_backup_$TIMESTAMP.dump" $DB_NAME

# Keep only last 30 days
find $BACKUP_DIR -name "db_backup_*.dump" -mtime +30 -delete

echo "Backup completed: db_backup_$TIMESTAMP.dump"
```

```bash
sudo chmod +x /usr/local/bin/backup-awareness-db.sh
```

### 2. Storage Backup Script
```bash
sudo nano /usr/local/bin/backup-awareness-storage.sh
```

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/awareness-platform"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
STORAGE_DIR="/var/www/awareness-lab/storage/app"

mkdir -p $BACKUP_DIR

# Tar storage
tar -czf "$BACKUP_DIR/storage_backup_$TIMESTAMP.tar.gz" -C /var/www/awareness-lab storage/app

# Keep only last 30 days
find $BACKUP_DIR -name "storage_backup_*.tar.gz" -mtime +30 -delete

echo "Backup completed: storage_backup_$TIMESTAMP.tar.gz"
```

```bash
sudo chmod +x /usr/local/bin/backup-awareness-storage.sh
```

### 3. Schedule Backups
```bash
sudo crontab -e
```

Add:
```
# Daily database backup at 2 AM
0 2 * * * /usr/local/bin/backup-awareness-db.sh >> /var/log/awareness-backup.log 2>&1

# Daily storage backup at 3 AM
0 3 * * * /usr/local/bin/backup-awareness-storage.sh >> /var/log/awareness-backup.log 2>&1
```

### 4. Off-Site Backup (Recommended)

Sync backups ke remote storage (S3, Backblaze, dll):
```bash
# Example: AWS S3
aws s3 sync /var/backups/awareness-platform s3://your-backup-bucket/awareness-platform/
```

## Rollback Plan

### 1. Application Rollback
```bash
cd /var/www/awareness-lab
git log --oneline -10  # Lihat recent commits
git checkout <previous-commit-hash>

composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo systemctl reload php8.2-fpm
```

### 2. Database Rollback
```bash
# Restore from backup
PGPASSWORD="OWNER_PASSWORD" pg_restore -h localhost -U owner_user -d awareness_platform -c /var/backups/awareness-platform/db_backup_TIMESTAMP.dump
```

**PENTING**: Database rollback akan kehilangan data yang dibuat setelah backup. Coordinate dengan users.

## Monitoring & Logging

### 1. Application Logs
```bash
tail -f /var/www/awareness-lab/storage/logs/laravel.log
```

### 2. Nginx Logs
```bash
tail -f /var/log/nginx/awareness-platform-error.log
tail -f /var/log/nginx/awareness-platform-access.log
```

### 3. Queue Worker Logs
```bash
tail -f /var/www/awareness-lab/storage/logs/worker.log
```

### 4. Database Logs
```bash
sudo tail -f /var/log/postgresql/postgresql-15-main.log
```

### 5. Log Rotation

Laravel automatically rotates logs (daily channel). Nginx logs:
```bash
sudo nano /etc/logrotate.d/nginx
```

Ensure:
```
/var/log/nginx/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        [ -f /var/run/nginx.pid ] && kill -USR1 `cat /var/run/nginx.pid`
    endscript
}
```

## Monitoring Tools (Recommended)

### 1. Error Tracking: Sentry
Add to `.env`:
```env
SENTRY_LARAVEL_DSN=https://your-sentry-dsn
```

Install:
```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=YOUR_DSN
```

### 2. Performance: New Relic (Optional)
Follow New Relic PHP agent installation guide.

### 3. Uptime Monitoring: Uptime Robot
Configure HTTP(S) monitor untuk `https://yourdomain.com`

### 4. Server Monitoring: Netdata (Optional)
```bash
bash <(curl -Ss https://my-netdata.io/kickstart.sh)
```

## Scaling Considerations

### Horizontal Scaling

**Load Balancer** (Nginx atau HAProxy):
- Multiple application servers behind load balancer
- Sticky sessions untuk Laravel session (atau use Redis session driver)

**Shared Storage**:
- NFS atau S3 untuk `storage/app` (file uploads)
- Semua app servers akses storage yang sama

**Database**:
- Read replicas untuk analytics queries
- Connection pooling via PgBouncer

### Vertical Scaling

**Database**:
- Upgrade RAM untuk larger `shared_buffers`
- SSD RAID 10 untuk I/O intensive workloads

**Application Servers**:
- More PHP-FPM workers (`pm.max_children`)
- More Redis memory (`maxmemory`)

### Dedicated Queue Workers

Heavy queue loads:
- Separate server untuk queue workers
- Multiple Supervisor processes dengan different queues

```ini
[program:awareness-high-priority]
command=php artisan queue:work redis --queue=high --tries=3
numprocs=4

[program:awareness-default]
command=php artisan queue:work redis --queue=default --tries=3
numprocs=2
```

### CDN (Content Delivery Network)

Serve static assets dari CDN:
- Cloudflare, AWS CloudFront, atau Fastly
- Configure `ASSET_URL` di `.env`

## Troubleshooting

### 500 Internal Server Error
1. Check Nginx error log: `tail -f /var/log/nginx/awareness-platform-error.log`
2. Check Laravel log: `tail -f storage/logs/laravel.log`
3. Verify `.env` configuration (especially `APP_KEY`)
4. Check file permissions: `storage/` dan `bootstrap/cache/` writable

### Database Connection Error
1. Verify PostgreSQL running: `sudo systemctl status postgresql`
2. Test connection: `psql -h 127.0.0.1 -U app_user -d awareness_platform`
3. Check `.env` database credentials
4. Verify RLS policies tidak blocks connection

### Queue Worker Not Processing Jobs
1. Check Supervisor status: `sudo supervisorctl status`
2. Check worker log: `tail -f storage/logs/worker.log`
3. Verify Redis running: `redis-cli ping` (should return `PONG`)
4. Restart workers: `sudo supervisorctl restart awareness-platform-worker:*`

### Slow Page Load
1. Check OPcache enabled: `php -i | grep opcache.enable`
2. Verify Laravel cached: `config:cache`, `route:cache`, `view:cache`
3. Check database indexes (see migration `xxxx_performance_indexes.php`)
4. Enable query log temporarily: `DB::enableQueryLog()` untuk debug N+1 queries

### SSL Certificate Renewal Failed
1. Check Certbot logs: `sudo journalctl -u certbot`
2. Verify domain resolves: `nslookup yourdomain.com`
3. Test manual renewal: `sudo certbot renew --dry-run`
4. Check Nginx config: `sudo nginx -t`

---

**Document Maintenance**: Update setelah setiap deployment procedure change.
