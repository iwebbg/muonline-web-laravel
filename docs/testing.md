# Testing

MuOnline Web has two automated test environments. Both are disposable and are
separate from the long-running application containers and configured databases.

## Prerequisites

Install Docker with the Compose plugin, clone the project as a Git working copy
and complete the normal local setup far enough for the development image and
Composer dependency volume to exist:

```bash
cp .env.example .env
cp docker/.env.example docker/.env
./docker/compose up --detach --build
```

The root `.env` may contain real local development connections. The test
workflows do not use those connections, but the files must exist because the
Compose wrapper validates the normal project setup before selecting a test
environment.

## Main test suite

Run all unit and feature tests with:

```bash
./docker/compose test
```

This command creates a one-shot PHP 8.5 container with the following safety
boundaries:

- SQLite `:memory:` is the only database available to the suite.
- The container has no network interface.
- `pdo_sqlsrv` is disabled in the test image.
- The project source is mounted read-only.
- Cache and storage paths use temporary in-memory filesystems.
- The container is removed automatically when PHPUnit exits.

PHPUnit and the Laravel base test case both verify the resolved environment,
driver and database before database-refresh traits can run. Tests stop before
migrations when any of those checks fail.

### Run a specific file

Prefer the narrowest file that covers the changed behavior:

```bash
./docker/compose test tests/Feature/MarketPageTest.php
```

Multiple files can be supplied in one run:

```bash
./docker/compose test \
    tests/Feature/DemoModeTest.php \
    tests/Feature/MarketPageTest.php
```

### Run a specific test

Pass normal PHPUnit arguments after the wrapper command:

```bash
./docker/compose test --filter=test_authenticated_market_page
```

Do not run PHPUnit or `php artisan test` inside the long-running `app`
container. Always use `./docker/compose test` so the network and driver
restrictions are present.

## SQL Server integration tests

SQLite verifies most application behavior quickly, but it cannot prove SQL
Server syntax, metadata, types or driver behavior. Run the adapter integration
suite separately:

```bash
./docker/compose test-integration
```

The integration workflow:

1. Creates a separate internal-only Docker project.
2. Starts SQL Server 2022 Developer without publishing a host port.
3. Creates a temporary `MuOnline` database.
4. Converts the committed UTF-16LE
   `tests/Fixtures/SqlServer/97d99i-schema.sql` export to UTF-8.
5. Loads its 97d+99i objects through `sqlcmd`.
6. Runs only the tests in `tests/Integration` through `phpunit.integration.xml`.
7. Removes the containers, internal network and database volume after the run,
   including after a failed test command.

The first integration run downloads the official SQL Server image and is much
slower than later runs. The image remains cached locally, but the database
volume never persists between runs.

The integration environment has fixed test-only credentials and the hostname
`sqlserver-test`. Its test bootstrap refuses any other SQL Server hostname,
database or username. It cannot resolve or connect to the SQL Server configured
in the root `.env`.

Run one integration file or method with the same argument style:

```bash
./docker/compose test-integration \
    tests/Integration/Game/Repositories/SqlServer97d99iAccountReadRepositoryTest.php

./docker/compose test-integration \
    --filter=test_repository_reads_owned_character_from_sql_server
```

## Using the legacy schema fixture

`tests/Fixtures/SqlServer/97d99i-schema.sql` is the committed compatibility
reference for the DarksTeam 97d+99i database schema. It contains database
settings, tables, constraints, views and stored procedures, but no reusable
fixture accounts or characters.

Never execute it against a configured development, demonstration or production
game database. The automated integration workflow is its only automatic
consumer and loads it exclusively into a newly created disposable database.

Integration tests insert their own sanitized records with reserved test names
and remove them during teardown. The complete database volume is then deleted.

## Recommended workflow

For an application change:

1. Run the narrowest affected unit or feature test.
2. Rerun that test after every change made to it.
3. Run `./docker/compose test` before committing.
4. Run `./docker/compose test-integration` when changing a game repository,
   database mapping, version profile or legacy schema fixture.
5. Perform manual browser testing separately for the affected user workflow.

Automated tests do not replace manual verification against dedicated test
accounts when enabling live game writes, payment callbacks or external mail.

## Investigating failures

First identify which environment failed:

- Main-suite failures mention `phpunit.xml`, SQLite and usually `:memory:`.
- Integration failures mention `phpunit.integration.xml`, `sqlsrv` and the
  `sqlserver-test` host.

A missing legacy table in the main suite normally means the test did not create
its portable fixture schema or did not replace a repository that is outside the
behavior under test. It is not a reason to reconnect the test to a live game
database.

When schema initialization fails, inspect the `sqlserver-schema` output in the
same command. The wrapper preserves the failing exit code and still removes the
temporary integration resources.
