# Manual Apache installation (XAMPP, LAMP and similar stacks)

This guide runs MuOnline Web directly through Apache and PHP without Docker. It
is intended for Windows development stacks such as XAMPP or Laragon and for
administrators who already operate a hardened LAMP server.

Docker remains the recommended and tested deployment because it supplies the
exact PHP, Microsoft ODBC, SQL Server, Redis, scheduler and queue environment.
A manual installation makes the server administrator responsible for matching
those dependencies, running background processes, renewing TLS certificates,
applying security updates and preserving writable-directory permissions.

## Runtime requirements

- Apache 2.4 with `mod_rewrite` and permission to use `.htaccess` files.
- **PHP 8.5**, matching the requirement in `composer.json`.
- Composer 2.
- Git.
- Node.js 24 and npm to build the frontend assets.
- SQLite for application-owned data, or another configured application
  database supported by Laravel.
- Microsoft ODBC Driver 18 and Microsoft Drivers 5.13 for PHP for SQL Server
  when connecting to a live MuOnline database.

Older XAMPP releases commonly bundle an older PHP version. Changing only the
version reported by the Apache module is not enough: the `php` command used by
Composer and Artisan must also resolve to PHP 8.5. Check both environments:

```powershell
php --version
php --ini
composer check-platform-reqs
```

Create a temporary `phpinfo()` page only when necessary to identify Apache's
loaded `php.ini`, then delete it immediately because it exposes server details.

## PHP extensions

Enable these extensions for the Apache PHP runtime and for command-line PHP:

- Required Laravel runtime: `ctype`, `curl`, `dom`, `fileinfo`, `filter`,
  `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `pdo`,
  `session`, `tokenizer` and `xml`.
- Used by the supplied runtime and recommended for this project: `bcmath`,
  `intl`, `xmlreader`, `xmlwriter` and `zip`.
- Default application database: `pdo_sqlite` and `sqlite3`.
- Live MuOnline database: `sqlsrv` and `pdo_sqlsrv` plus Microsoft ODBC Driver
  18.
- Optional Redis services: `redis` when `SESSION_DRIVER`, `CACHE_STORE` or
  `QUEUE_CONNECTION` uses Redis.

`pcntl` is used by Unix process management but is not available on Windows and
is not required for normal web requests. Production queue workers still need a
real process supervisor or Windows service wrapper.

On Windows, extension DLLs must match all three properties of the PHP runtime:
PHP 8.5, x64/x86 architecture and Thread Safe (`TS`) or Non Thread Safe (`NTS`)
mode. Apache module installations normally use the Thread Safe build. Do not
mix DLLs from another PHP release or architecture.

After changing `php.ini`, restart Apache and verify the effective modules:

```powershell
php -m
composer check-platform-reqs
```

For live SQL Server data, follow Microsoft's
[PHP driver system requirements](https://learn.microsoft.com/sql/connect/php/system-requirements-for-the-php-sql-driver)
and install ODBC Driver 18 before enabling the matching `sqlsrv` and
`pdo_sqlsrv` extensions.

## Windows installation with XAMPP or Laragon

Clone the project under the stack's web directory. This example uses XAMPP:

```powershell
Set-Location C:\xampp\htdocs
git clone https://github.com/iwebbg/muonline-web-laravel.git
Set-Location muonline-web-laravel
Copy-Item .env.example .env
New-Item database\database.sqlite -ItemType File -Force
composer install --no-interaction --prefer-dist
npm ci
npm run build
php artisan key:generate
php artisan migrate --force --no-interaction
php artisan storage:link
php artisan admin:create
```

If `storage:link` cannot create a symbolic link, enable Windows Developer Mode
or run that one command from an elevated terminal. Uploaded website assets are
not publicly available until `public/storage` points to `storage/app/public`.

### Local environment without Redis

The default `.env.example` targets the supplied Redis container. A basic XAMPP
installation can start safely without Redis by changing these values before
running migrations:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://muonline.test

DB_CONNECTION=sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

GAME_DATA_SOURCE=fixture
GAME_DB_READONLY=true
GAME_WRITES_ENABLED=false
```

Keep fixture data and both write switches closed until the live SQL Server
connection passes `php artisan game:doctor`.

### Connecting live data and enabling writes

Configure the `GAME_DB_*` credentials for a dedicated restricted SQL Server
login, then switch from fixtures to the real database while keeping the two
write gates closed:

```dotenv
GAME_DATA_SOURCE=database
GAME_DB_READONLY=true
GAME_WRITES_ENABLED=false
```

Clear cached configuration and run the compatibility and permission check:

```bash
php artisan optimize:clear
php artisan game:doctor
```

First verify accounts, characters, rankings and warehouses in read-only mode.
Test every mutation against a cloned database and disposable game accounts and
confirm that both the application and MuOnline backups can be restored. Then
activate the operational configuration:

```dotenv
GAME_DATA_SOURCE=database
GAME_DB_READONLY=false
GAME_WRITES_ENABLED=true
```

Apply it to Apache and background workers:

```bash
php artisan optimize:clear
```

Restart Apache, the queue worker and the scheduler or their service wrappers.
Both switches must permit writes; otherwise registration, password changes,
character actions, moderation, item delivery and other game mutations remain
blocked. The rankings connection remains independently read-only.

`game:doctor` deliberately accepts only the safe commissioning values. Run it
before enabling writes, or close both write gates temporarily when repeating a
schema and SQL-permission audit.

### Recommended Apache VirtualHost

The secure configuration points Apache directly at the `public` directory. In
XAMPP, ensure this line is enabled in `apache/conf/httpd.conf`:

```apacheconf
LoadModule rewrite_module modules/mod_rewrite.so
```

Add a host to `apache/conf/extra/httpd-vhosts.conf`, adjusting the path when the
project is elsewhere:

```apacheconf
<VirtualHost *:80>
    ServerName muonline.test
    DocumentRoot "C:/xampp/htdocs/muonline-web-laravel/public"

    <Directory "C:/xampp/htdocs/muonline-web-laravel/public">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/muonline-error.log"
    CustomLog "logs/muonline-access.log" combined
</VirtualHost>
```

Map the local name in `C:\Windows\System32\drivers\etc\hosts`:

```text
127.0.0.1 muonline.test
```

Restart Apache and open `http://muonline.test`. Administrator login is at
`http://muonline.test/admin/login`.

### Subfolder fallback using the root `.htaccess`

When a local control panel cannot create a VirtualHost, the repository root
`.htaccess` forwards requests into `public/`. Clone the whole repository into
the web directory, leave `public/index.php` in place and use a URL such as:

```dotenv
APP_URL=http://localhost/muonline-web-laravel
```

Then open `http://localhost/muonline-web-laravel`. Apache must allow
`FileInfo`/`Options` overrides and load `mod_rewrite`; otherwise the root rule
fails closed instead of exposing `.env` and application source files.

This fallback is for local or constrained shared-hosting setups. A production
host must use a VirtualHost whose `DocumentRoot` is the `public` directory. Do
not move `public/index.php` into the repository root and do not point a public
domain at the full project directory.

## Linux Apache installation

Install PHP 8.5 and the extensions above from a trusted distribution source,
then deploy the application outside a user's home directory:

```bash
cd /var/www
git clone https://github.com/iwebbg/muonline-web-laravel.git
cd muonline-web-laravel
cp .env.example .env
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan key:generate
php artisan storage:link
sudo install -d -o www-data -g www-data -m 750 /var/lib/muonline-web
sudo -u www-data touch /var/lib/muonline-web/database.sqlite
```

Set the application database to the writable file outside the source checkout:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/var/lib/muonline-web/database.sqlite
```

Give the Apache user write permission only where Laravel needs it, then finish
the application-owned database and administrator setup as that user:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
sudo -u www-data php artisan migrate --force --no-interaction
sudo -u www-data php artisan admin:create
```

Use an Apache site equivalent to the Windows VirtualHost, with
`DocumentRoot /var/www/muonline-web-laravel/public`, enable `rewrite`, enable the
site and reload Apache. Add HTTPS with the certificate automation used by your
server distribution before accepting real credentials or payment webhooks.

## Connecting the live MuOnline database

After `sqlsrv` and `pdo_sqlsrv` appear in both Apache and CLI `php -m` output,
configure the `GAME_DB_*` values described in `.env.example`. SQL Server must be
reachable through TCP, normally on port 1433; `127.0.0.1` refers to the same
machine as Apache and not to another game server.

Keep writes disabled and verify the restricted database login:

```bash
php artisan optimize:clear
php artisan game:doctor
```

Use the least-privilege SQL login template in
`docker/sql/create-game-web-login.sql`. Never connect the website with
`sysadmin`, `db_owner` or the SQL Server service account.

## Scheduler and queues

The fixture/local configuration above uses a synchronous queue and does not
need a queue worker. To exercise scheduled operations during development, keep
this command running in a separate terminal:

```bash
php artisan schedule:work
```

Production needs `php artisan schedule:run` every minute. On Linux, add one cron
entry using the absolute project and PHP paths:

```cron
* * * * * cd /var/www/muonline-web-laravel && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

On Windows, create a Task Scheduler task that repeats every minute and runs:

```text
Program: C:\xampp\php\php.exe
Arguments: artisan schedule:run
Start in: C:\xampp\htdocs\muonline-web-laravel
```

When production uses `QUEUE_CONNECTION=database` or `redis`, run
`php artisan queue:work` under Supervisor, systemd, NSSM or another process
manager that restarts failed workers. A browser request must never be expected
to keep a queue worker alive.

## Production hardening

Before exposing a manual installation to the Internet:

1. Set `APP_ENV=production`, `APP_DEBUG=false` and the final HTTPS `APP_URL`.
2. Use a public directory VirtualHost, a valid TLS certificate and secure
   database credentials.
3. Make only `storage`, `bootstrap/cache` and the SQLite file writable by the
   Apache user; application source and `.env` must not be writable or public.
4. Configure the scheduler, queue worker, backups and log rotation.
5. Run `php artisan game:doctor`, then `php artisan optimize`.
6. Register payment webhooks only after the HTTPS URL is stable.

## Troubleshooting

- **Composer rejects the PHP version:** CLI PHP is older than 8.5 or Composer
  resolves a different executable than Apache.
- **Apache returns 500 immediately:** enable `mod_rewrite`, allow overrides and
  inspect the Apache error log for a disallowed `.htaccess` directive.
- **Every route except the home page returns 404:** the VirtualHost does not
  allow the supplied `public/.htaccess`, or `mod_rewrite` is disabled.
- **Vite manifest is missing:** run `npm ci` and `npm run build` from the project
  root.
- **`could not find driver`:** enable `pdo_sqlite` for the application database
  or `sqlsrv`/`pdo_sqlsrv` for the game database in the `php.ini` used by the
  failing Apache or CLI process.
- **SQL Server driver fails to load:** match PHP 8.5, architecture and TS/NTS,
  install ODBC Driver 18 and restart Apache.
- **Laravel cannot write cache, sessions or logs:** repair permissions on
  `storage` and `bootstrap/cache`; do not make the whole project writable.
- **Configuration changes have no effect:** run `php artisan optimize:clear`
  after editing `.env`.

For game-write safety, payment setup, backups and update procedures, continue
with the [installation and deployment guidance](installation.md). The same
application-level safeguards apply to a manual Apache deployment.
