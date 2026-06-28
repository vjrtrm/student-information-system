# SIS — Release Checklist & Deployment Runbook

**Version:** 1.0 · June 2026  
**Target:** Linux VPS / shared hosting — Apache 2.4 · PHP 8.x · MySQL 5.7  
**Repository:** https://github.com/vjrtrm/student-information-system

---

## Pre-deployment checklist

Work through every item before touching the server.

### Code
- [ ] All 12 modules committed and pushed to `main`
- [ ] `./vendor/bin/phpunit` passes with zero failures locally
- [ ] No `APP_DEBUG=true` or hardcoded credentials anywhere in source
- [ ] `config/mail.php` contains no real SMTP password (use env var `MAIL_PASS`)
- [ ] `storage/uploads/` is in `.gitignore` — no student documents in the repo
- [ ] `.env` / `*.env` files are in `.gitignore`

### Security
- [ ] All POST routes verified to call `$this->requireCsrf()`
- [ ] Aadhaar rendering uses `View::maskAadhaar()` — no raw 12-digit numbers in views
- [ ] No PII (name, mobile, DOB) in email notification payloads — batch/change IDs only
- [ ] `storage/uploads/` is outside `public/` and unreachable via browser
- [ ] Error display is off in production (`APP_DEBUG=false`, `display_errors=Off` in php.ini)

### Database
- [ ] All 031 migrations reviewed and ordered correctly (`001` → `031`)
- [ ] Backup of any existing production database taken before migration run

---

## Server prerequisites

Run these once per server. Skip if already provisioned.

```bash
# PHP 8.x + required extensions
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql \
    php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd

# Verify
php -v          # must show 8.x
php -m | grep -E 'pdo|mbstring|xml|gd'

# Apache + mod_rewrite
sudo apt install -y apache2
sudo a2enmod rewrite
sudo systemctl restart apache2

# MySQL 5.7 (or compatible MariaDB 10.x)
sudo apt install -y mysql-server-5.7   # or mariadb-server

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## Step 1 — Create the database and user

```sql
-- Run in MySQL as root
CREATE DATABASE sis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sis_user'@'localhost' IDENTIFIED BY '<STRONG_PASSWORD>';
GRANT ALL PRIVILEGES ON sis.* TO 'sis_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## Step 2 — Deploy the application

```bash
# Clone (first deploy) or pull (update)
cd /var/www
git clone https://github.com/vjrtrm/student-information-system sis
# — or —
cd /var/www/sis && git pull origin main

# Install PHP dependencies (no dev packages in production)
composer install --no-dev --optimize-autoloader

# Create writable directories
mkdir -p storage/uploads/students storage/uploads/photos
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

---

## Step 3 — Configure environment variables

Create `/var/www/sis/.env` (or set via Apache `SetEnv` in the vhost — see Step 4):

```ini
APP_NAME="Student Information System"
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://sis.yourdomain.com

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=sis
DB_USER=sis_user
DB_PASS=<STRONG_PASSWORD>

MAIL_HOST=smtp.yourdomain.com
MAIL_PORT=587
MAIL_USER=noreply@yourdomain.com
MAIL_PASS=<SMTP_PASSWORD>
MAIL_FROM=noreply@yourdomain.com
MAIL_FROM_NAME="Student Information System"
MAIL_ENCRYPTION=tls
```

> The application reads all config via `getenv()`. If your host doesn't allow `.env` files, add `SetEnv` directives to the Apache vhost instead (see Step 4).

---

## Step 4 — Apache virtual host

Create `/etc/apache2/sites-available/sis.conf`:

```apache
<VirtualHost *:80>
    ServerName sis.yourdomain.com
    DocumentRoot /var/www/sis/public

    # Redirect HTTP → HTTPS (remove if SSL is handled by a proxy)
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName sis.yourdomain.com
    DocumentRoot /var/www/sis/public

    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/sis.crt
    SSLCertificateKeyFile /etc/ssl/private/sis.key

    # Environment variables (alternative to .env file)
    SetEnv APP_ENV        production
    SetEnv APP_DEBUG      false
    SetEnv APP_BASE_URL   https://sis.yourdomain.com
    SetEnv DB_HOST        127.0.0.1
    SetEnv DB_NAME        sis
    SetEnv DB_USER        sis_user
    SetEnv DB_PASS        <STRONG_PASSWORD>
    SetEnv MAIL_HOST      smtp.yourdomain.com
    SetEnv MAIL_PORT      587
    SetEnv MAIL_USER      noreply@yourdomain.com
    SetEnv MAIL_PASS      <SMTP_PASSWORD>
    SetEnv MAIL_FROM      noreply@yourdomain.com
    SetEnv MAIL_FROM_NAME "Student Information System"
    SetEnv MAIL_ENCRYPTION tls

    <Directory /var/www/sis/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Block access to everything outside public/
    <Directory /var/www/sis>
        Options -Indexes
        Require all denied
    </Directory>
    <Directory /var/www/sis/public>
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/sis_error.log
    CustomLog ${APACHE_LOG_DIR}/sis_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite sis.conf
sudo a2enmod ssl rewrite
sudo systemctl reload apache2
```

---

## Step 5 — Run database migrations

Run all migration files in numeric order. Each file is idempotent-safe (no `IF NOT EXISTS` on ALTER statements — run once only).

```bash
cd /var/www/sis

# Helper: run all migrations in order
for f in database/migrations/*.sql; do
    echo "→ $f"
    mysql -u sis_user -p'<STRONG_PASSWORD>' sis < "$f"
done
```

Or run individually if you need to pick up from a specific migration:

```bash
mysql -u sis_user -p sis < database/migrations/001_create_departments.sql
# ... repeat through ...
mysql -u sis_user -p sis < database/migrations/031_create_settings.sql
```

---

## Step 6 — Seed institution admin

```sql
-- Run in MySQL
INSERT INTO departments (name, code, created_at)
VALUES ('Administration', 'ADMIN', NOW());

INSERT INTO users (name, email, password, role, department_id, status, created_at)
VALUES (
    'Institution Admin',
    'admin@yourinstitution.edu',
    '$2y$10$REPLACE_WITH_BCRYPT_HASH',   -- generate with: php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"
    'institution_admin',
    1,
    'active',
    NOW()
);
```

Generate the bcrypt hash on your server:
```bash
php -r "echo password_hash('YourInitialPassword', PASSWORD_BCRYPT) . PHP_EOL;"
```

---

## Step 7 — File permissions

```bash
# Web server owns storage; app files owned by deploy user
chown -R www-data:www-data /var/www/sis/storage
chmod -R 775 /var/www/sis/storage

# Everything else: read-only for web server
chown -R deploy_user:www-data /var/www/sis
find /var/www/sis -type f -exec chmod 644 {} \;
find /var/www/sis -type d -exec chmod 755 {} \;
chmod -R 775 /var/www/sis/storage
```

---

## Step 8 — PHP configuration (`php.ini`)

Confirm these settings in `/etc/php/8.x/apache2/php.ini`:

```ini
; Security
display_errors = Off
log_errors = On
error_log = /var/log/php/sis_errors.log

; Uploads (student documents ≤ 2 MB, so 8 MB headroom is enough)
upload_max_filesize = 8M
post_max_size = 10M
max_file_uploads = 20

; Session
session.cookie_httponly = 1
session.cookie_secure = 1       ; only if HTTPS
session.gc_maxlifetime = 1800   ; matches AUTH_SESSION_TIMEOUT default (30 min)

; Timezone
date.timezone = Asia/Kolkata    ; adjust to your institution's timezone
```

```bash
sudo systemctl restart apache2
```

---

## Post-deployment verification

Work through this checklist after the deployment is live.

### Smoke tests
- [ ] `https://sis.yourdomain.com` loads the login page (no PHP errors, no blank page)
- [ ] Institution Admin can log in with email + password
- [ ] Dashboard loads with stat cards
- [ ] Master Data → Option Lists shows seeded data from `013_seed_option_lists.sql`
- [ ] Create a Department from Master Data → Departments
- [ ] Create a Staff user; log in as that staff member
- [ ] Staff can access `/onboarding`, `/students`, `/promotion`

### Functional
- [ ] Student bulk upload (CSV with ≥ 2 rows) succeeds; duplicate handling works
- [ ] Enrolment batch created, approved; student sees enrolment number
- [ ] Student Information Form: partial save, full submit, form locks on submit
- [ ] Dept Admin approves a submitted form; student sees approved status
- [ ] Request-to-Change: student requests edit, staff approves, field updates
- [ ] Notification emails arrive (check SMTP logs if not)
- [ ] Student Data Grid: search, filter, sort, Excel export work
- [ ] Promotion: Institution Admin opens window; staff creates batch; dept admin approves; students' academic year updates

### Security
- [ ] Navigating to `/onboarding` when logged out redirects to `/login`
- [ ] Staff cannot reach `/master-data/departments` (403)
- [ ] Student cannot reach `/students` (403)
- [ ] Direct URL to a student's uploaded document (`/storage/uploads/...`) returns 403 or 404
- [ ] CSRF: submitting a form with `_csrf` removed returns 403

### Logs
- [ ] `audit_log` table has entries from the smoke tests
- [ ] `/var/log/apache2/sis_error.log` has no fatal errors
- [ ] `/var/log/php/sis_errors.log` is empty (or contains only expected notices)

---

## Ongoing maintenance

| Task | Frequency | Command / Location |
|------|-----------|--------------------|
| Database backup | Daily | `mysqldump -u sis_user -p sis > sis_$(date +%Y%m%d).sql` |
| Pull latest code | Per release | `git pull && composer install --no-dev -o` |
| Run new migrations | Per release | Run any new `NNN_*.sql` files in order |
| Clear PHP sessions | Weekly | `find /var/lib/php/sessions -name 'sess_*' -mtime +1 -delete` |
| Rotate logs | Monthly | `logrotate /etc/logrotate.d/apache2` |
| Review audit_log | Monthly | Query `SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 100` |

---

## Rollback procedure

If a deployment breaks production:

```bash
# 1. Revert to previous commit
cd /var/www/sis
git log --oneline -10      # find the last good commit hash
git checkout <HASH>
composer install --no-dev -o

# 2. Restore database if migrations were run
mysql -u sis_user -p sis < sis_backup_YYYYMMDD.sql

# 3. Reload Apache
sudo systemctl reload apache2
```

> Migration rollback scripts are not included in v1. Always take a full `mysqldump` backup before running migrations on production.

---

*Document owner: Institution Admin / DevOps lead. Update after each release.*
