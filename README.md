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

MuOnline Web currently provides matching game and event profiles for three
tested server distributions:

| Profile key | Compatible server files |
| --- | --- |
| `darksteam_97d99i` | [DarksTeam MuServer 97d+99i Beta 43.3][darksteam-97d99i] |
| `darksteam_1_0m` | [DarksTeam MuServer 0.99.60/62T (1.0M) Beta 15][darksteam-1-0m] |
| `muemu_season_6` | MuEMU Season 6 Episode 3 Update 32, verified against owner-supplied server files and database exports |

Select the same key through `GAME_SERVER_PROFILE` and `GAME_EVENT_PROFILE` so
database mappings, classes, maps, item formats, limits and event schedules come
from one compatible adapter bundle. When `GAME_EVENT_PROFILE` is omitted, it
follows `GAME_SERVER_PROFILE`. MuOnline Web is not affiliated with DarksTeam or
MuEMU and does not bundle, modify or redistribute their server files.

More version integrations are in preparation. Server owners who need another
MuOnline distribution or a custom compatibility profile can request an
integration at [office@interweb.bg](mailto:office@interweb.bg). Versions not
listed above must be treated as unsupported until their own profile and adapters
have been implemented and verified.

## Highlights

- Responsive bilingual public website, account panel and administration.
- Rankings, character and guild profiles, events, news, guides and downloads.
- Character resets, grand resets, stats, PK clear and move-to-town with previews.
- Paid character rename, class change, reset and LevelUpPoints services with
  explicit confirmation and administrator-controlled Credits pricing.
- Multi-vault inventory, Web Vault and physical-item Jewel Bank.
- Credits packages with Stripe, PayPal and Revolut Pay, VIP, vote rewards,
  Zen Bank, Market and timed Auctions.
- Credits earning through bounded online-time rewards and exact-option item
  exchange offers configured from the verified version catalog.
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
- [Testing and SQL Server integration tests](docs/testing.md)
- [Complete feature catalogue](docs/features.md)
- [Current implementation state](docs/current-state.md)
- [Product and UX direction](docs/product-ux.md)
- [Delivery roadmap](docs/roadmap.md)
- [Reference sources and compatibility baseline](docs/reference-sources.md)
- [Coding standards](docs/coding-standards.md)
- [Contributor and agent instructions](AGENTS.md)

The exported `tests/Fixtures/SqlServer/97d99i-schema.sql` and
`tests/Fixtures/SqlServer/1.0m-schema.sql` files are adapter schema references.
They are not Laravel migrations and must never be executed automatically
against a configured game database. Each isolated integration-test workflow may
load its matching fixture only into a new disposable SQL Server database.

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

[darksteam-97d99i]: https://darksteam.net/threads/darksteam-muserver-97d-99i-beta-43-3.6440/
[darksteam-1-0m]: https://darksteam.net/threads/darksteam-muserver-0-99-60-62t-1-0m-beta-15.21354/
