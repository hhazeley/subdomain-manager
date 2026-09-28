# Subdomain Manager

A Laravel application for user registration, administrator-approved annual subdomain registrations, automatic renewals, and direct Cloudflare DNS management.

## Features

- Self-service user accounts and subdomain requests
- Verified-email user accounts protected by Google reCAPTCHA v2
- Administrator approval required before verified users can access subdomain and DNS services
- Full administrator and approval-only administrator roles
- Administrator-managed base domains, blocked labels, users, user approvals, and approval queue
- Administrator-created users, guarded user deletion, and configurable self-registration
- One-year registration period beginning on approval
- Auto-approved renewal during the final 45 days
- Automatic expiration and Cloudflare record removal
- User deletion requests with a 14-day administrator restoration window
- Email notifications for registration, approval, deletion, renewal, and expiration
- Self-service account settings for password and verified email changes
- Self-service password reset, admin-triggered password reset, and verification resend
- Per-browser light, dark, and system themes
- Cloudflare DNS support for A, AAAA, CAA, CERT, CNAME, DNSKEY, DS, HTTPS, LOC, MX, NAPTR, NS, OPENPGPKEY, PTR, SMIMEA, SRV, SSHFP, SVCB, TLSA, TXT, and URI records
- Generic structured `data` JSON support for advanced Cloudflare record types
- Strict ownership checks that prevent users from managing records outside their own subdomain
- Semantic application versioning and administrator-installed, checksum-verified GitHub updates

## Requirements

- PHP 8.3 or newer
- MySQL 8.0+ or MariaDB 10.6+
- Composer 2
- PHP extensions: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, PDO, pdo_mysql, session, tokenizer, xml, and zip
- Node.js 20+ for building frontend assets (Node.js is not required on the production server when built assets are uploaded)
- A Cloudflare API token with `Zone:Read` and `DNS:Edit` permissions for every managed zone
- Google reCAPTCHA v2 checkbox site and secret keys
- An SMTP account or another Laravel-compatible mail transport

## Local setup

```text
composer install
copy .env.example .env
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan key:generate
```

Configure the database and `CLOUDFLARE_API_TOKEN` in `.env`, then run:

```text
php artisan migrate
php artisan app:create-admin admin@example.com
npm install
npm run build
php artisan serve
```

Local `.env`, SQLite databases, dependencies, compiled assets, logs, caches, installation state, and updater backups are ignored by Git. Never commit production credentials. Keep `.env.example` limited to safe placeholders.

## cPanel browser installation

The packaged cPanel release includes production Composer dependencies and compiled frontend assets. Composer, Node.js, npm, SSH, and cPanel Terminal are not required for a fresh installation.

### 1. Create the database

In **cPanel > MySQL Databases**:

1. Create a database and database user.
2. Add the user to the database with all privileges.
3. Keep the fully prefixed cPanel database name and username for `.env`.

### 2. Upload the release

Download or build `Subdomain-Manager-cPanel.zip`. In **cPanel > File Manager**, create a directory outside `public_html`, upload the ZIP, and extract it there:

```text
/home/CPANEL_USER/subdomain-manager
```

For the hostname that will run the application, set its document root to:

```text
/home/CPANEL_USER/subdomain-manager/public
```

Never point the document root at the application root. Only the `public` directory may be web-accessible.

### 3. Select PHP

In **MultiPHP Manager**, select PHP 8.3 or newer for the application hostname. Enable the PHP extensions listed under Requirements. Ensure the application directory, `storage`, and `bootstrap/cache` are writable by the PHP process.

### 4. Run the browser wizard

Open:

```text
https://dns.example.com/install.php
```

The wizard:

- Checks PHP, required extensions, dependencies, compiled assets, and writable directories.
- Tests the MySQL connection and privileges.
- Verifies the Cloudflare API token.
- Checks connectivity to Mailgun SMTP.
- Writes a production `.env` with a cryptographically secure application key.
- Runs all database migrations.
- Creates and verifies the first full administrator.
- Sends a Mailgun diagnostic email.
- Writes `storage/app/installed`, permanently locking the installer.
- Displays cPanel cron commands customized for the installation path.

Create a scoped token in Cloudflare under **My Profile > API Tokens**. Grant only `Zone:Read` and `DNS:Edit` for the zones that administrators will add. Do not use the Global API Key.

Create the reCAPTCHA v2 checkbox keys in the Google reCAPTCHA console and include the application's hostname in the allowed domains.

For Mailgun, use the SMTP credentials shown under **Sending > Domain settings > SMTP credentials**. The sending domain must be verified in Mailgun, and `MAIL_FROM_ADDRESS` must use that domain. On a Mailgun sandbox, each recipient must also be authorized. After changing mail settings, run `php artisan config:clear` and restart every queue worker.

### 5. Configure cron jobs

Copy the two commands shown by the installer into **cPanel > Cron Jobs**. They resemble:

```text
* * * * * cd /home/CPANEL_USER/subdomain-manager && /usr/local/bin/ea-php83 artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/CPANEL_USER/subdomain-manager && /usr/local/bin/ea-php83 artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

Adjust the PHP path and home directory for the hosting account. The scheduler expires registrations and purges deletion holds; the queue worker delivers verification and lifecycle email notifications.

### Installer security

After setup, the installer refuses all configuration changes because `storage/app/installed` exists and `.env` contains an application key. Do not delete the lock file. The `.env` file is outside the public document root and is written with restrictive permissions.

## Versions and GitHub releases

The authoritative version is stored in `VERSION` using semantic versioning. To publish an update:

```text
1. Update VERSION, for example from 1.1.0 to 1.1.1.
2. Commit and push the change.
3. Create and push the matching tag: git tag v1.1.1 && git push origin v1.1.1
```

The GitHub release workflow tests the application, compiles assets, installs production Composer dependencies, and publishes:

- `Subdomain-Manager-cPanel.zip`
- `Subdomain-Manager-cPanel.zip.sha256`

The release ZIP excludes `.env`, local databases, logs, tests, source-control metadata, Node dependencies, and runtime caches. It includes `vendor`, `public/build`, `public/install.php`, and `CPANEL-INSTALL.txt`.

### Administrator updates

Full administrators can open **Updates** in the application navigation. The updater:

- Queries stable releases from `hhazeley/subdomain-manager`.
- Requires a release newer than the installed semantic version.
- Downloads the production ZIP and checksum through the GitHub API.
- Rejects packages whose SHA-256 checksum, release version, required files, or paths are invalid.
- Preserves `.env`, storage data, the installer lock, local SQLite databases, and other runtime data.
- Enters maintenance mode, backs up replaced files, installs the package, runs migrations, clears caches, and returns the application to service.

For a private repository, set `UPDATER_GITHUB_TOKEN` in `.env` to a fine-grained, read-only token with access to repository contents. It is not needed while the repository and releases are public. After changing updater settings, run:

```text
php artisan config:clear
```

Back up the application files and database before every production update. Update backups and history are stored under `storage/app/updates`, which must not be web-accessible or committed.

## Operations

Application and Cloudflare API errors are written to `storage/logs/laravel.log`. Cloudflare failures do not report success to users and failed record creations are removed from the local database.

## Testing

```text
php artisan test
npm run build
```
