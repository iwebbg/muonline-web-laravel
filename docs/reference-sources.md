# Reference sources and compatibility baseline

This document records the sources used to understand the current game version
and runtime. References provide compatibility information; they are not bundled
dependencies and do not imply affiliation.

## DarksTeam MuServer 97d+99i

The first MuOnline Web profile is developed and tested against
[DarksTeam MuServer 97d+99i Beta 43.3][darksteam-reference]. It is currently the
only supported game profile.

The DarksTeam files provide the reference layout for:

- MuOnline SQL Server tables, procedures and legacy column names;
- 97d+99i character classes and progression;
- ten-byte item records, warehouse storage and serial generation;
- `DTData/EventManager.dat`, `DTData/Events.ini` and
  `Data/commonserver.cfg` event behavior;
- Happy Hour, Party Bonus, Sky Event, Lucky Jewels and Lucky Mixes schedules.

MuOnline Web never edits or deploys those server files. Server configuration is
translated into reviewed application profiles and administrator-managed
overrides. Credentials or secrets found in legacy files must be considered
unsafe examples and must never be copied into an installation.

## Schema reference in this repository

The `tests/Fixtures/SqlServer/97d99i-schema.sql` file is a schema-only export
used to document the current 97d+99i database contract. It includes the game
tables, view, stored procedures, indexes and defaults needed during adapter
development.

It contains no application migration and must never be executed automatically.
Laravel migrations own only the web application's database objects. Game tables
remain an external legacy contract accessed through version-specific adapters.

## Legacy functional research

Development also used historical MuOnline web applications to understand
common workflows such as resets, banks, VIP, votes, markets and administration.
Those projects are research references only. Their executable code, deprecated
database calls, credentials and commercial themes are not included or deployed.

Business rules are implemented independently and are accepted only after they
are verified against the active version profile, database schema and dedicated
test server.

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

[darksteam-reference]: https://darksteam.net/threads/darksteam-muserver-97d-99i-beta-43-3.6440/
