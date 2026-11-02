# MuOnline Web

Modern, version-aware MuOnline server web application, starting with
97d+99i. The application is built as a Laravel 13 monolith with an
interactive Blade-based interface and a PHP 8.5 container runtime.

## Project documentation

- [Reference sources](docs/reference-sources.md)
- [Product and UX direction](docs/product-ux.md)
- [Delivery roadmap](docs/roadmap.md)
- [Coding standards](docs/coding-standards.md)

The exported MuOnline schema is currently stored in `script.sql`. It is a
reference export and is not an application migration.

## 97d+99i compatibility baseline

The initial 97d+99i profile is developed and tested against the **DarksTeam
MuServer 97d99i** server-file layout. This is a compatibility reference, not an
affiliation or a bundled server dependency. Game-owned files are never modified
or deployed by this application.

The event profile follows `DTData/EventManager.dat`, `DTData/Events.ini` and
`Data/commonserver.cfg`, including the DarksTeam custom Happy Hour, Party Bonus,
Sky Event, Lucky Jewels and Lucky Mixes events. Web display schedules remain
explicitly configurable in `config/game_events.php` because server owners often
change event hours and intervals without changing the executable files.

The active profile is selected with `GAME_EVENT_PROFILE`; the home page limit
uses `GAME_EVENT_HOME_LIMIT`. Each event may use either explicit `times` with an
optional ISO weekday list (`days_of_week`), or `interval_minutes` with an
`interval_anchor`. Adding another event requires only a new profile entry. The
schedule calculator, nearest-event ordering, live timer and expanded list do
not require event-specific controller or JavaScript changes.

Administrators with the settings capability can publish bilingual event
overrides from **Administration → Event schedules**. Overrides are scoped to
the active event profile and game version, merge by stable event key and keep
immutable before/after revisions. The reviewed PHP profile remains the safe
fallback until an override is published; MuServer files are never edited.

Player-market listings keep the original 10-byte item in recoverable escrow.
`GAME_MARKET_LISTING_DURATION_DAYS` controls their lifetime (30 days by
default); the scheduler automatically removes expired listings and retries the
exact item return until the seller's active warehouse can accept it.

Timed auctions use the same lossless escrow. Each leading bid is held in the
application Credits ledger; a higher bid atomically releases the previous hold.
At expiry the scheduler credits the seller and delivers the original item to
the winner, or returns it to the seller when no valid bid exists.

Web Storage keeps each original item blob and serial in application custody,
then returns those exact bytes to the first valid warehouse rectangle. Jewel
Bank is backed by the same physical-item records: its displayed count is never
a synthetic balance and currently supports Chaos, Bless, Soul, Life and
Creation jewels from the verified 97d+99i catalog.

Player-facing modules are controlled from **Administration → Modules**. Each
published configuration is scoped to `GAME_SERVER_PROFILE` and
`GAME_SERVER_VERSION`, uses optimistic revisions and records immutable
before/after snapshots in the shared administrator audit log. Disabled routes
return `404` and disappear from player navigation, while existing custody and
recovery records remain available to administrators. Timed auctions cannot be
enabled unless the player market is also enabled.

Zen Bank uses an application ledger plus compare-and-set warehouse updates.
Deposits, withdrawals and retries are idempotent; interrupted transfers remain
recoverable, while an unexpected game balance produces an audited conflict
instead of silently overwriting Zen. VIP, market escrow, Web Storage and Zen
Bank recovery commands run every minute in both local and production scheduler
containers.

## Local development

The supported runtime is provided by Docker and uses PHP 8.5, Microsoft ODBC
Driver 18, Microsoft SQL Server PHP drivers 5.13+, Nginx and Redis.

```bash
cp docker/.env.example docker/.env

./docker/compose up --detach --build
```

The application is then available at `http://localhost`. Nginx owns local
port 80 and forwards PHP requests to the Laravel PHP-FPM container. The first
milestone uses fixture data and does not require MuOnline database credentials.
The local scheduler service runs the same pending-operation recovery jobs used
in production.

## Production deployment

Production uses immutable PHP and Nginx images, queue and scheduler workers,
Redis persistence, HTTPS redirection and automatic Let's Encrypt renewal.

```bash
cp docker/.env.example docker/.env
```

Set the two Docker-specific values:

```dotenv
DOCKER_ENV=production
APP_DOMAIN=mu.example.com
```

Build the immutable images and apply application-owned migrations explicitly:

```bash
./docker/compose build
./docker/compose run --rm app php artisan game:doctor
./docker/compose run --rm app php artisan migrate --force --no-interaction
```

`game:doctor` is read-only and must pass before deployment. It verifies the
required 97d+99i tables and columns, closed write switches, and rejects SQL
Server identities that are members of `sysadmin` or `db_owner`.

Then start the same services used for local development:

```bash
./docker/compose up --detach
```

Laravel, application database and MuOnline SQL Server settings remain in the
root `.env` file for both environments. Before the first production start,
configure a valid Laravel application key and the real game database credentials
there. Public DNS must point to the host and inbound ports 80 and 443 must be
reachable so Certbot can complete the initial HTTP challenge. The certificate
email is derived as `webmaster@APP_DOMAIN`. Renewal runs twice daily and reloads
Nginx only after a successful renewal.

Keep `GAME_DB_READONLY=true` and `GAME_WRITES_ENABLED=false` during initial
deployment and smoke testing. Enable writes only after the least-privilege SQL
account, backup and rollback procedure have been verified. Email delivery can
remain `MAIL_MAILER=log` until provider credentials are configured and tested.
