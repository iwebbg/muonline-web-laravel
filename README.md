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

Then start the same launcher used for local development:

```bash
./docker/compose up --detach --build
```

Laravel, application database and MuOnline SQL Server settings remain in the
root `.env` file for both environments. Before the first production start,
configure a valid Laravel application key and the real game database credentials
there. Public DNS must point to the host and inbound ports 80 and 443 must be
reachable so Certbot can complete the initial HTTP challenge. The certificate
email is derived as `webmaster@APP_DOMAIN`. Renewal runs twice daily and reloads
Nginx only after a successful renewal.
