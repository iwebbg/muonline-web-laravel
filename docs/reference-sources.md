# Reference sources

This document records the approved sources that may be consulted while
building MuOnline Web. They are references, not dependencies, unless a later
decision explicitly promotes an asset or algorithm into this repository.

## Database schema

### Current 97d+99i export

- Path: `/home/zdev/Documents/GitHub/muonline-web-laravel/script.sql`
- Purpose: authoritative reference for the current MuOnline SQL Server schema.
- Contains: 23 tables, one view, stored procedures, indexes and defaults.
- Important tables: `MEMB_INFO`, `MEMB_STAT`, `AccountCharacter`, `Character`,
  `warehouse`, `Guild`, `GuildMember`, `MEMB_CREDITS`.
- Notes: UTF-16 schema-only export; it contains no table data. It must not be
  executed automatically as a Laravel migration.

Game-owned tables are treated as an external legacy contract. Application
migrations must not alter them. New application tables use an explicit
`muweb_` namespace/prefix or a dedicated SQL Server schema.

## Previous Laravel prototype

- Root: `/home/zdev/Documents/GitHub/muonline-web`
- Laravel application: `/home/zdev/Documents/GitHub/muonline-web/php-core`
- Purpose: visual direction, 97d+99i item decoding research, item images,
  character class assets, inventory and warehouse experiments.
- Useful code locations:
  - `app/Repositories/Helpers/Version97d99i.php`
  - `app/Repositories/CharacterRepository.php`
  - `app/Repositories/WarehouseRepository.php`
  - `resources/views/default`
  - `public/assets/items/97d_99i`
  - `database/seeders/sql/items_97d_99i.sql`
- Caveats: prototype-only code, hard-coded test accounts, incomplete routes and
  models, and a large Codebase theme asset set. Reuse of commercial theme code
  or assets requires a valid licence.

The prototype's visual language may inspire the new design, but controllers,
authentication, database writes and version switching must be reimplemented.

## DTWeb functional reference

- Root: `/home/zdev/Downloads/DTWeb 2.0 Update v1.3`
- Purpose: catalogue legacy features and recover business rules for 97d+99i.
- Useful code locations:
  - `configs/config.php` — reset, grand reset, stats, PK and economy settings.
  - `inc/modules_funcs.php` — legacy operation rules.
  - `mod/user` — account and character modules.
  - `mod/rankings` — ranking variants.
  - `mod/admin` — administration feature catalogue.
  - `install/sql` — legacy feature tables.
- Features represented: reset, grand reset, add/reset stats, PK clear, character
  changes, inventory, warehouse, web storage, banks, credits, VIP, votes,
  market, auction, rankings, news, downloads and administration.
- Security rule: DTWeb is never deployed, included or copied as executable
  application code. It uses deprecated `mssql_*` calls and unsafe raw queries.
  Only verified business rules and independently reviewed static assets may be
  translated into the new implementation.

## DarksTeam MuServer 97d99i reference

- Root: `/home/zdev/Downloads/DarksTeam MuServer 97d99i/MuServer`
- Purpose: runtime compatibility baseline for the initial 97d+99i profile.
- Event sources: `DTData/EventManager.dat`, `DTData/Events.ini` and
  `Data/commonserver.cfg`.
- Custom scheduled events: Happy Hour, Party Bonus, Sky Event, Lucky Jewels and
  Lucky Mixes.
- Integration rule: server files are read as reference material only. The web
  application uses its own typed configuration and never edits or deploys the
  MuServer files.
- Secret rule: values found in legacy configuration files are considered
  compromised examples and must never be copied into application configuration.

## Container references

### Laravel/PHP container patterns

- Root: `/home/zdev/Documents/GitHub/Apps/service-workspace-lite-app`
- Useful paths:
  - `php-api-v2/docker/php/app`
  - `docker/docker-compose.local.yaml`
  - `docker/docker-compose.prod.yaml`
- Purpose: PHP-FPM, queue worker, scheduler, healthcheck and local/production
  Compose patterns.
- Required adaptation: replace PostgreSQL-specific packages with Microsoft
  ODBC Driver 18 and Microsoft `sqlsrv`/`pdo_sqlsrv` drivers supporting PHP 8.5.
- Production rule: do not copy automatic migration-on-container-start behaviour
  for the game database.

### Nginx and TLS patterns

- Root: `/home/zdev/Documents/GitHub/Apps/saas-app`
- Useful paths:
  - `nginx/saas/prod`
  - `docker/docker-compose.saas.yaml`
  - `scripts/container-rollout.sh`
- Purpose: Nginx reverse proxy, Certbot certificate creation/renewal,
  healthchecks and controlled container rollout.
- Required adaptation: Laravel front controller/FastCGI routing, private admin
  tooling, safe renewal reload and MuOnline-specific deployment paths.

## Official platform references

- Laravel release and support policy: https://laravel.com/framework/docs/releases
- PHP supported versions: https://www.php.net/supported-versions.php
- PHP downloads: https://www.php.net/downloads.php
- Microsoft PHP SQL Server driver release notes:
  https://learn.microsoft.com/en-us/sql/connect/php/release-notes-php-sql-driver
- Microsoft PHP SQL Server support matrix:
  https://learn.microsoft.com/en-us/sql/connect/php/microsoft-php-drivers-for-sql-server-support-matrix

The project targets Laravel 13, PHP 8.5, Microsoft PHP SQL Server driver 5.13+
and ODBC Driver 18. Patch releases are updated continuously through the normal
dependency maintenance process.
