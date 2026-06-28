# Student Information System (SIS)

A college Student Information System built with PHP 8.x · MVC · MySQL 5.7 · Bootstrap 5.

**Roles:** Student · Department Staff · Department Admin · Institution Admin  
**Repository:** https://github.com/vjrtrm/student-information-system

---

## Modules

| # | Module | Status |
|---|--------|--------|
| 1 | Authentication & Access Control | ✅ |
| 2 | Master Data & Department Management | ✅ |
| 3 | Student Onboarding (bulk upload) | ✅ |
| 4 | Enrolment Number Generation & Approval | ✅ |
| 5 | Student Information Form (dynamic fields, partial save) | ✅ |
| 6 | Submission & Edit Approval (Request-to-Change) | ✅ |
| 7 | Notifications (email via SMTP) | ✅ |
| 8 | Dashboards, Statistics & Personalisation | ✅ |
| 9 | Staff Management | ✅ |
| 10 | Field Management (configurable fields + custom fields) | ✅ |
| 11 | Student Data Grid & Excel Export | ✅ |
| 12 | Student Promotion (bulk year-end promotion) | ✅ |

---

## Local Developer Setup

### Prerequisites

| Tool | Version | Notes |
|------|---------|-------|
| XAMPP | 8.x | Includes Apache, PHP 8.x, MySQL 5.7/MariaDB |
| Composer | Latest | PHP dependency manager |
| Git | Any | For cloning the repo |

---

### macOS — step by step

#### 1. Install XAMPP

1. Download **XAMPP for macOS** from https://www.apachefriends.org (choose the PHP 8.x build).
2. Open the `.dmg`, drag XAMPP to `/Applications`.
3. Open **XAMPP Control** (`/Applications/XAMPP/manager-osx.app`) and start **Apache** and **MySQL**.
4. Verify PHP works — open Terminal and run:
   ```bash
   /Applications/XAMPP/xamppfiles/bin/php -v
   ```
   You should see `PHP 8.x.x`.

#### 2. Add PHP to your PATH (one-time)

```bash
echo 'export PATH="/Applications/XAMPP/xamppfiles/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
php -v        # should show PHP 8.x
mysql --version
```

> If you use bash instead of zsh, replace `~/.zshrc` with `~/.bash_profile`.

#### 3. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

#### 4. Clone the repository

```bash
cd /Applications/XAMPP/xamppfiles/htdocs
git clone https://github.com/vjrtrm/student-information-system sis
cd sis
```

#### 5. Install PHP dependencies

```bash
composer install
```

#### 6. Create the database

Open **http://localhost/phpmyadmin** in your browser and run in the SQL tab:

```sql
CREATE DATABASE sis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Or from Terminal:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root sis -e \
  "CREATE DATABASE sis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

#### 7. Run all migrations

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/sis

for f in database/migrations/*.sql; do
    echo "→ $f"
    /Applications/XAMPP/xamppfiles/bin/mysql -u root sis < "$f"
done
```

> XAMPP's default MySQL root password is blank. If you've set one, add `-p` to each command.

#### 8. Configure the application

The app reads configuration from environment variables. For local dev, set them in Apache's vhost config.

Open `/Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf` and add:

```apache
<VirtualHost *:80>
    ServerName sis.test
    DocumentRoot /Applications/XAMPP/xamppfiles/htdocs/sis/public

    SetEnv APP_ENV        local
    SetEnv APP_DEBUG      true
    SetEnv APP_BASE_URL   http://sis.test
    SetEnv DB_HOST        127.0.0.1
    SetEnv DB_NAME        sis
    SetEnv DB_USER        root
    SetEnv DB_PASS        
    SetEnv MAIL_HOST      localhost
    SetEnv MAIL_PORT      1025
    SetEnv MAIL_FROM      dev@sis.test
    SetEnv MAIL_FROM_NAME "SIS Dev"
    SetEnv MAIL_ENCRYPTION

    <Directory /Applications/XAMPP/xamppfiles/htdocs/sis/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Make sure `httpd-vhosts.conf` is included — open `/Applications/XAMPP/xamppfiles/etc/httpd.conf` and confirm this line is uncommented:

```apache
Include etc/extra/httpd-vhosts.conf
```

Add `sis.test` to your hosts file:

```bash
echo "127.0.0.1 sis.test" | sudo tee -a /etc/hosts
```

Restart Apache from XAMPP Control, then open **http://sis.test**.

#### 9. Seed an Institution Admin user

```bash
# Generate a bcrypt hash for your chosen password
php -r "echo password_hash('Admin1234', PASSWORD_BCRYPT) . PHP_EOL;"
```

Copy the hash and run in phpMyAdmin (SQL tab):

```sql
INSERT INTO departments (name, code, created_at)
VALUES ('Administration', 'ADMIN', NOW());

INSERT INTO users (name, email, password, role, department_id, status, created_at)
VALUES ('Admin', 'admin@sis.test', '<PASTE_HASH_HERE>', 'institution_admin', 1, 'active', NOW());
```

Open **http://sis.test** and log in as `admin@sis.test` with your chosen password.

---

### Windows — step by step

#### 1. Install XAMPP

1. Download **XAMPP for Windows** from https://www.apachefriends.org (PHP 8.x build).
2. Run the installer — default path is `C:\xampp`, accept defaults.
3. Open **XAMPP Control Panel** and start **Apache** and **MySQL**.
4. Open Command Prompt and verify:
   ```cmd
   C:\xampp\php\php.exe -v
   ```

#### 2. Add PHP and MySQL to your PATH (one-time)

1. Open **Start → Search → "Edit the system environment variables"**.
2. Click **Environment Variables**, find `Path` under System variables, click **Edit**.
3. Add two new entries:
   - `C:\xampp\php`
   - `C:\xampp\mysql\bin`
4. Click OK, open a **new** Command Prompt, and verify:
   ```cmd
   php -v
   mysql --version
   ```

#### 3. Install Composer

Download and run the Composer Windows installer from https://getcomposer.org/Composer-Setup.exe. It auto-detects `php.exe` from your PATH. After installation:

```cmd
composer --version
```

#### 4. Clone the repository

```cmd
cd C:\xampp\htdocs
git clone https://github.com/vjrtrm/student-information-system sis
cd sis
```

#### 5. Install PHP dependencies

```cmd
composer install
```

#### 6. Create the database

Open **http://localhost/phpmyadmin** and run in the SQL tab:

```sql
CREATE DATABASE sis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Or from Command Prompt:

```cmd
mysql -u root -e "CREATE DATABASE sis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

#### 7. Run all migrations

**Command Prompt:**
```cmd
cd C:\xampp\htdocs\sis

for %f in (database\migrations\*.sql) do (
    echo Running %f
    mysql -u root sis < %f
)
```

**PowerShell:**
```powershell
cd C:\xampp\htdocs\sis

Get-ChildItem database\migrations\*.sql | Sort-Object Name | ForEach-Object {
    Write-Host "→ $($_.Name)"
    mysql -u root sis < $_.FullName
}
```

#### 8. Configure the application

Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf` and add:

```apache
<VirtualHost *:80>
    ServerName sis.test
    DocumentRoot C:/xampp/htdocs/sis/public

    SetEnv APP_ENV        local
    SetEnv APP_DEBUG      true
    SetEnv APP_BASE_URL   http://sis.test
    SetEnv DB_HOST        127.0.0.1
    SetEnv DB_NAME        sis
    SetEnv DB_USER        root
    SetEnv DB_PASS        
    SetEnv MAIL_HOST      localhost
    SetEnv MAIL_PORT      1025
    SetEnv MAIL_FROM      dev@sis.test
    SetEnv MAIL_FROM_NAME "SIS Dev"
    SetEnv MAIL_ENCRYPTION

    <Directory C:/xampp/htdocs/sis/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Confirm `httpd-vhosts.conf` is included — open `C:\xampp\apache\conf\httpd.conf` and check this line is uncommented:

```apache
Include conf/extra/httpd-vhosts.conf
```

Add `sis.test` to your hosts file. Open **Notepad as Administrator**, then open `C:\Windows\System32\drivers\etc\hosts` and add:

```
127.0.0.1 sis.test
```

Restart Apache from XAMPP Control Panel, then open **http://sis.test**.

#### 9. Seed an Institution Admin user

```cmd
php -r "echo password_hash('Admin1234', PASSWORD_BCRYPT) . PHP_EOL;"
```

Copy the hash and paste into phpMyAdmin (SQL tab):

```sql
INSERT INTO departments (name, code, created_at)
VALUES ('Administration', 'ADMIN', NOW());

INSERT INTO users (name, email, password, role, department_id, status, created_at)
VALUES ('Admin', 'admin@sis.test', '<PASTE_HASH_HERE>', 'institution_admin', 1, 'active', NOW());
```

---

### Running the test suite

The tests use PHPUnit with an **in-memory SQLite database** — no MySQL required for testing.

```bash
# macOS / Linux — from the project root
./vendor/bin/phpunit

# Run a specific test file
./vendor/bin/phpunit tests/Integration/PromotionBatchApproveTest.php

# Verbose output
./vendor/bin/phpunit --testdox
```

```cmd
:: Windows
vendor\bin\phpunit
```

All tests must pass with zero failures before committing any code.

---

### Local email (optional)

To test notification emails locally without a real SMTP server, install **Mailpit**:

- **Mac:** `brew install mailpit && mailpit`
- **Windows:** Download the `.exe` from https://github.com/axllent/mailpit/releases and run it

Mailpit listens on SMTP port `1025` and provides a web inbox at **http://localhost:8025**.  
The vhost config above already points `MAIL_HOST=localhost` and `MAIL_PORT=1025`.

---

### Setting env vars without a vhost

If you prefer not to set up a named vhost, create a file `public/.env.php` (do **not** commit this file):

```php
<?php
// public/.env.php — local dev only, never committed
putenv('APP_ENV=local');
putenv('APP_DEBUG=true');
putenv('APP_BASE_URL=http://localhost/sis/public');
putenv('DB_NAME=sis');
putenv('DB_USER=root');
putenv('DB_PASS=');
putenv('MAIL_HOST=localhost');
putenv('MAIL_PORT=1025');
putenv('MAIL_FROM=dev@sis.test');
```

Then add at the very top of `public/index.php` (before `require`):

```php
if (file_exists(__DIR__ . '/.env.php')) require __DIR__ . '/.env.php';
```

Access the app at **http://localhost/sis/public**.

---

## Project structure

```
public/               ← web root (point Apache DocumentRoot here)
  index.php           ← front controller and router
  assets/             ← CSS, JS

app/
  Controllers/        ← one controller per module
  Models/             ← thin model layer (static, PDO-backed)
  Helpers/            ← Auth, Db, View, Csrf, Config, FieldConfig, FieldRegistry ...
  Middleware/         ← AuthMiddleware, RoleMiddleware, DepartmentScopeMiddleware
  Views/              ← PHP templates
    layouts/app.php   ← Bootstrap 5 shell with nav

config/               ← app.php, database.php, mail.php, enrolment.php, form.php
database/
  migrations/         ← 001–031 SQL files; run in order on first deploy

docs/
  module-NN-name/     ← Requirements, Design, Tasks .md per module
  SIS_Release_Checklist.md  ← deployment runbook

storage/
  uploads/students/   ← uploaded documents (not in git; created on deploy)

tests/
  Unit/               ← model and helper unit tests
  Integration/        ← controller + DB integration tests
  bootstrap.php       ← in-memory SQLite schema for tests

scripts/
  commit-module.sh    ← commit helper used during development

.claude/              ← Claude Code project context (agents, hooks, skills)
CLAUDE.md             ← auto-loaded by Claude Code; project conventions
```

---

## Code conventions

- **Routing:** add routes to the table in `public/index.php`. Static paths before `{param}` wildcards.
- **Controllers:** extend `App\Controllers\Controller`; call `RoleMiddleware::handle([...])` at the top of each action; call `$this->requireCsrf()` on every POST handler.
- **DB:** use `App\Helpers\Db` static methods with prepared statements only. Timestamps: `date('Y-m-d H:i:s')` — never `NOW()` (breaks SQLite tests).
- **Audit:** use `MasterAuditLogger::log()` for business actions; `AuditLogger` for auth events only. Never mix.
- **PII:** mask Aadhaar with `View::maskAadhaar()`. No student PII in emails or logs.
- **Views:** `ob_start()` → render template into `$content` → `require layouts/app.php`.
- **Tests:** extend `Tests\TestCase`; use `seedDepartment()`, `seedUser()`, `seedStudent()`, `seedFullStudent()`.

---

## Deployment

See [`docs/SIS_Release_Checklist.md`](docs/SIS_Release_Checklist.md) for the full deployment runbook (Linux VPS · Apache · MySQL 5.7).
