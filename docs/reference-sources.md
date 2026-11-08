# Reference sources and compatibility baseline

This document records the sources used to understand the current game version
and runtime. References provide compatibility information; they are not bundled
dependencies and do not imply affiliation.

## DarksTeam MuServer 97d+99i

The `darksteam_97d99i` game and event profiles are developed and tested against
[DarksTeam MuServer 97d+99i Beta 43.3][darksteam-97d99i].

The DarksTeam files provide the reference layout for:

- MuOnline SQL Server tables, procedures and legacy column names;
- 97d+99i character classes and progression;
- ten-byte item records, warehouse storage and serial generation;
- `DTData/EventManager.dat`, `DTData/Events.ini` and
  `Data/commonserver.cfg` event behavior;
- Happy Hour, Party Bonus, Sky Event, Lucky Jewels and Lucky Mixes schedules.

## DarksTeam MuServer 1.0M

The `darksteam_1_0m` game and event profiles are developed and tested against
[DarksTeam MuServer 0.99.60/62T (1.0M) Beta 15][darksteam-1-0m].

The DarksTeam files provide the reference layout for:

- the 1.0M MuOnline SQL Server schema and account-bound password hashing;
- Dark Lord, Command, version-specific class progression and map names;
- ten-byte 1.0M item records, +13 items, Cape of Lord and serial generation;
- Blood Castle, Devil Square, Chaos Castle, Castle Siege and the additional
  schedules enabled by the supplied event configuration.

MuOnline Web never edits or deploys those server files. Server configuration is
translated into reviewed application profiles and administrator-managed
overrides. Credentials or secrets found in legacy files must be considered
unsafe examples and must never be copied into an installation.

## Schema reference in this repository

The repository keeps separate sanitized schema references for each supported
adapter:

- `tests/Fixtures/SqlServer/97d99i-schema.sql` documents the 97d+99i contract;
- `tests/Fixtures/SqlServer/1.0m-schema.sql` documents the 1.0M contract.

They include only the tables, views, stored procedures, indexes and defaults
needed during adapter development and isolated integration testing.

They contain no application migrations and must never be executed
automatically. Laravel migrations own only the web application's database
objects. Game tables remain an external legacy contract accessed through
version-specific adapters.

## Legacy functional research

Development also used historical MuOnline web applications to understand
common workflows such as resets, banks, VIP, votes, markets and administration.
Those projects are research references only. Their executable code, deprecated
database calls, credentials and commercial themes are not included or deployed.

Business rules are implemented independently and are accepted only after they
are verified against the active version profile, database schema and dedicated
test server.

Additional game and event integrations are in preparation. A server owner who
needs another distribution or a custom adapter can request compatibility work
at [office@interweb.bg](mailto:office@interweb.bg).

## Official platform documentation

- [Laravel release and support policy](https://laravel.com/framework/docs/releases)
- [PHP supported versions](https://www.php.net/supported-versions.php)
- [Microsoft PHP SQL Server driver release notes](https://learn.microsoft.com/en-us/sql/connect/php/release-notes-php-sql-driver)
- [Microsoft PHP SQL Server support matrix](https://learn.microsoft.com/en-us/sql/connect/php/microsoft-php-drivers-for-sql-server-support-matrix)
- [Docker documentation](https://docs.docker.com/)
- [Let's Encrypt documentation](https://letsencrypt.org/docs/)

The current application targets Laravel 13, PHP 8.5, Microsoft ODBC Driver 18
and Microsoft PHP SQL Server drivers 5.13 or newer. Confirm the installed
versions in `composer.lock`, `package-lock.json` and the Dockerfiles rather than
assuming the latest release.

[darksteam-97d99i]: https://darksteam.net/threads/darksteam-muserver-97d-99i-beta-43-3.6440/
[darksteam-1-0m]: https://darksteam.net/threads/darksteam-muserver-0-99-60-62t-1-0m-beta-15.21354/
