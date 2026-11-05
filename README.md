# MuOnline Web

MuOnline Web is a free, open-source website, account panel and administration
suite for MuOnline servers. It combines a modern player experience with strict
boundaries around the legacy game database, exact-item custody and auditable
administrative operations.

The project is built with Laravel, Blade, Alpine.js and Tailwind CSS. It is
designed as a version-aware platform: game-specific schemas, character classes,
item formats and rules belong to compatibility profiles instead of being spread
through controllers and templates.

## Compatibility

The architecture is prepared for multiple MuOnline versions, but **the only
currently implemented and tested profile is 97d+99i**.

That profile was developed against the DarksTeam 97d+99i server files published
in [DarksTeam MuServer 97d+99i Beta 43.3][darksteam-reference]. The reference
defines the current database, item and event compatibility baseline. MuOnline
Web is not affiliated with DarksTeam and does not bundle, modify or redistribute
their server files.

Adding another version requires a new profile and adapters for its schema,
classes, maps, items and supported operations. Until that work is implemented
and verified, other MuOnline versions must be treated as unsupported.

## Highlights

- Responsive bilingual public website, account panel and administration.
- Rankings, character and guild profiles, events, news, guides and downloads.
- Character resets, grand resets, stats, PK clear and move-to-town with previews.
- Multi-vault inventory, Web Vault and physical-item Jewel Bank.
- Credits packages with Stripe, PayPal and Revolut Pay, VIP, vote rewards,
  Zen Bank, Market and timed Auctions.
- Items Shop, version-aware Item Generator and configurable Mystery Boxes.
- Mystery Boxes can release one hidden random item or every configured item.
- Separate administrator identities, granular Game Master permissions and audit
  history.
- Independent module switches, safe recovery workflows and exact-item escrow.

See the [complete feature catalogue](docs/features.md) for the full Public,
Account, Administration and Operations breakdown.

## Why Docker is recommended

Docker is the supported runtime because it keeps the application and its native
dependencies isolated from the host. The provided stack includes the compatible
PHP runtime, Microsoft ODBC and SQL Server drivers, Nginx and Redis, so local and
production environments follow the same tested layout.

The production stack also provides dedicated queue and Laravel scheduler
services, automatic Let's Encrypt certificate creation and renewal, health
checks and persistent application storage. This reduces configuration drift and
avoids installing the web runtime directly on the game server host. Containers
improve isolation and repeatability, but they do not replace backups,
least-privilege database credentials or normal server hardening.

## Quick start

```bash
cp .env.example .env
cp docker/.env.example docker/.env
# Edit .env and replace the SQL Server host and password placeholders.
npm ci
npm run build
./docker/compose up --detach --build
```

The first container start creates and migrates **MuOnline_Web** automatically.
Then generate the application key:

```bash
./docker/compose exec -T app php artisan key:generate
```

The local site is available at `http://localhost` and uses fixture game data by
default. Read the [Docker installation guide](docs/installation.md) before
connecting a real MuOnline database or enabling writes. Windows/XAMPP and LAMP
operators can instead follow the
[manual Apache installation guide](docs/installation-apache.md).

Live and production installations keep Laravel data in an isolated
**MuOnline_Web** database on the SQL Server instance. Docker can provision that
database, its restricted login, migrations and safe seed data automatically on
first start; the installation guide documents the temporary credential flow.

## Documentation

- [Installation with Docker](docs/installation.md)
- [Manual Apache installation (XAMPP, LAMP and similar stacks)](docs/installation-apache.md)
- [Credits payments configuration](docs/payments.md)
- [Complete feature catalogue](docs/features.md)
- [Current implementation state](docs/current-state.md)
- [Product and UX direction](docs/product-ux.md)
- [Delivery roadmap](docs/roadmap.md)
- [Reference sources and compatibility baseline](docs/reference-sources.md)
- [Coding standards](docs/coding-standards.md)
- [Contributor and agent instructions](AGENTS.md)

The exported `script.sql` file is a schema reference for 97d+99i. It is not a
Laravel migration and must never be executed automatically against a game
database.

## License

MuOnline Web was created by **InterWeb Ltd.** and is released free of charge
under the [MIT License](LICENSE). You may use, modify and redistribute it under
the terms of that license.

MuOnline and any related names, game data or assets remain the property of their
respective owners. Third-party server files and commercial assets are not
included in this repository.

## Support the project

If MuOnline Web is useful to your server or community, you can support ongoing
development through:

- [Buy Me a Coffee](https://buymeacoffee.com/interweb.bg)
- [Ko-fi](https://ko-fi.com/interwebltd)
- [PayPal](https://paypal.me/interwebltd)

[darksteam-reference]: https://darksteam.net/threads/darksteam-muserver-97d-99i-beta-43-3.6440/
