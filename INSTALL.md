# Installation Guide - 5 Minute Installer

Like WordPress.

## Requirements
- PHP 8.1+
- Extensions: pdo, curl, json, mbstring, openssl + pdo_mysql OR pdo_pgsql OR pdo_sqlite
- Writable: project root, /config, /storage
- Bunny.net Stream library + Auth0 app

## Steps
1. Upload files to server
2. Visit `https://yourdomain.com/install/`
3. Step 1: Environment check - ensures PHP version, extensions, writability
4. Step 2: Choose database driver
   - MySQL: host/port/db/user/pass
   - PostgreSQL: same
   - SQLite: path (auto-creates file, zero-config for shared hosting)
5. Step 3: App & Auth0 - site URL, name, Auth0 domain/client/secret, admin emails, gate secret
6. Step 4: Bunny.net - library ID, API key, token auth key, CDN hostname
7. Step 5: Email service
   - Resend (default): just API key
   - SMTP: host/port/user/pass/encryption - works with Brevo, SMTP2GO, Gmail app password etc
   - SendGrid: API key
   - Mailgun: domain + API key
8. Step 6: Installer writes `config.php`, runs migrations, creates `storage/install.lock`

## After install
- Delete `/install/` folder OR keep lock file
- Configure Auth0: Allowed Callback = `https://yourdomain.com/auth/callback`, Logout = `https://yourdomain.com`
- Disable Auth0 signups, add users manually

## Docker quick start
```bash
composer install
php -S 0.0.0.0:8000 -t . index.php
# then open http://localhost:8000/install/
```
