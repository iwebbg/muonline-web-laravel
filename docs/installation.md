# Installation with Docker

Docker is the supported way to run MuOnline Web. It provides the exact PHP,
Microsoft SQL Server drivers, Nginx and Redis versions expected by the
application and keeps them isolated from packages installed on the host.

For an installation without containers, see the
[manual Apache guide for XAMPP, LAMP and similar stacks](installation-apache.md).
The manual route requires the operator to supply PHP 8.5, background services,
TLS and the matching Microsoft SQL Server drivers.

The local stack runs the application, Nginx, Redis and the Laravel scheduler.
The production stack additionally runs a queue worker, builds immutable images,
creates and renews Let's Encrypt certificates and persists application storage.

## Requirements

- Docker Engine with Docker Compose v2.
- Git.
- Node.js 24 and npm for local frontend builds. Production images build the
  frontend inside Docker.
- A supported SQL Server reachable from the containers for the isolated
  `MuOnline_Web` application database and, when enabled, live game data.
- A dedicated MuOnline test server before enabling any write operation.

## Source control and release selection

Every installation should remain a Git working copy instead of being downloaded
as a ZIP archive or copied manually between servers. Git records the exact
application revision, makes updates reproducible and allows an operator to
choose between the latest `main` branch and a published version tag.

Clone the repository and enter the project directory:

```bash
git clone https://github.com/iwebbg/muonline-web-laravel.git
cd muonline-web-laravel
```

The `main` branch contains the latest project changes. It is appropriate for
development, testing and operators who deliberately want to follow current
development:

```bash
git switch main
git pull --ff-only origin main
```

Published version tags provide a fixed, reproducible application release and
are recommended for production. Fetch the available tags, list them and switch
to the selected release:

```bash
git fetch origin --tags --prune
git tag --sort=-version:refname
git switch --detach v1.2.3
git describe --tags --exact-match
```

Replace `v1.2.3` with a tag that exists in the repository. The detached state
is intentional for a tagged deployment: application files remain fixed at that
release until the operator explicitly switches to a newer tag. Keep local
configuration in the untracked `.env` files and do not edit tracked application
files directly on the server.

## Local installation

### Interactive Ubuntu / WSL setup

From a cloned checkout, run:

```bash
bash docker/install.sh
```

The installer checks Docker Engine and Compose, creates missing environment
files, lets you select a registered game profile and asks for the SQL endpoint
and temporary bootstrap credentials. It generates restricted-login passwords
and APP_KEY, provisions web access to the existing game database, builds the
frontend and starts the stack. After success it removes temporary SQL
administrator passwords and offers to create a web administrator. Existing
runtime passwords and APP_KEY are preserved on rerun. SQL Server, restored game
databases, TCP access and Windows firewall configuration must be ready first.

Signed archives for Windows Manager are built separately with
`./deploy/release.sh 1.3.0`. This packages the current working tree through an
isolated production Docker build and writes ignored ZIPs and a signed manifest
under `deploy/`. It prompts for an Ed25519 private key path outside the checkout;
the public key must be trusted by the manager. Archives contain production
dependencies and compiled frontend assets, never the installation's `.env` or
runtime storage. The archive excludes the entire `storage/` tree, including
`.gitignore` placeholders; the manager creates these writable directories on
installation and preserves them during updates. Use a new release version when
publishing changed artifacts.

The minimum manager version supporting this S21 bundle is **0.1.0**. Generated
web manifests therefore declare `compatibility.managerMin: "0.1.0"`. This is separate
from the web artifact version passed to `release.sh`; the script never creates
Git tags or changes the manager repository version.

### Manual Docker setup

After cloning the repository and selecting the desired branch or tag, create
the two environment files:

```bash
cp .env.example .env
cp docker/.env.example docker/.env
```

Edit the root `.env` and replace the SQL Server host, runtime password and
temporary administrator-password placeholders. The default configuration uses
fixture game data, stores application-owned records in `MuOnline_Web`, and
keeps the game-write switches closed. Build the frontend and start the stack:

```bash
npm ci
npm run build
./docker/compose up --detach --build
```

The first start creates, migrates and seeds `MuOnline_Web` automatically.
Generate the Laravel application key:

```bash
./docker/compose exec -T app php artisan key:generate
```

Open `http://localhost`. The local Nginx container owns host port 80 and proxies
PHP requests to the application container. Composer dependencies are installed
into a Docker volume on the first local start.

Useful local commands:

```bash
./docker/compose ps
./docker/compose logs --follow app scheduler nginx
./docker/compose exec -T app php artisan about
./docker/compose test
./docker/compose down
```

The test command creates a disposable container that has no network access and
uses SQLite in memory. It never runs PHPUnit inside the long-running application
container. Pass a file path or PHPUnit filter after the command for a narrow run:

```bash
./docker/compose test tests/Feature/MarketPageTest.php
./docker/compose test --filter=test_authenticated_market_page
```

SQL Server adapter tests use separate disposable environments initialized from
the matching `97d99i-schema.sql` or `1.0m-schema.sql` legacy fixture. Each has
an internal-only Docker network, fixed test credentials and a temporary
database volume that is removed after every run:

```bash
./docker/compose test-integration
./docker/compose test-integration-1.0m
```

See the dedicated [testing guide](testing.md) for isolation guarantees, narrow
test commands, legacy schema handling and failure diagnosis.

## Selecting a supported game and event profile

MuOnline Web currently supports three matched profile bundles:

| Profile key | Compatible server files |
| --- | --- |
| `darksteam_97d99i` | [DarksTeam MuServer 97d+99i Beta 43.3](https://darksteam.net/threads/darksteam-muserver-97d-99i-beta-43-3.6440/) |
| `darksteam_1_0m` | [DarksTeam MuServer 0.99.60/62T (1.0M) Beta 15](https://darksteam.net/threads/darksteam-muserver-0-99-60-62t-1-0m-beta-15.21354/) |
| `muemu_season_6` | [Louis Emulator Season 6 Update 32](https://darksteam.net/threads/mu-server-season-6-louis-update-32-no-limits-crack.36704/), verified against the supplied installation and database export |

Set both selectors to the same supported key before bootstrap:

```dotenv
GAME_SERVER_PROFILE=darksteam_97d99i
GAME_EVENT_PROFILE=darksteam_97d99i
```

For a 1.0M installation use `darksteam_1_0m` for both values and set
`GAME_ACCOUNT_PASSWORD_SCHEME=darksteam_md5`. For the tested MuEMU build use
`muemu_season_6` and `GAME_ACCOUNT_PASSWORD_SCHEME=plaintext`. When
`GAME_EVENT_PROFILE` is omitted, it follows `GAME_SERVER_PROFILE`; explicit
selectors must not mix versions. Another server distribution or custom profile
can be requested at
[office@interweb.bg](mailto:office@interweb.bg).

## Automatic SQL Server database first start

SQLite remains convenient for a fixture-only evaluation. A live or production
installation should keep Laravel-owned tables in **MuOnline_Web** on the same
SQL Server instance as **MuOnline**, while using a separate database and login.

Configure the root **.env** before the first container start:

~~~dotenv
DB_CONNECTION=sqlsrv
DB_HOST=192.168.0.105
DB_PORT=1433
DB_DATABASE=MuOnline_Web
DB_USERNAME=muonline_web_app
DB_PASSWORD=replace-with-a-long-random-runtime-password
DB_ENCRYPT=true
DB_TRUST_SERVER_CERTIFICATE=true
AUTO_MIGRATE=true

APP_DB_BOOTSTRAP=true
APP_DB_SEED=true
APP_DB_ADMIN_USERNAME=sa
APP_DB_ADMIN_PASSWORD=replace-with-the-current-sa-password

GAME_DATA_SOURCE=database
GAME_DB_CONNECTION=sqlsrv
GAME_DB_HOST=192.168.0.105
GAME_DB_PORT=1433
GAME_DB_MUONLINE=MuOnline
GAME_DB_USERNAME=muonline_game_web
GAME_DB_PASSWORD=replace-with-a-strong-runtime-password-123!
GAME_DB_ADMIN_USERNAME=
GAME_DB_ADMIN_PASSWORD=
GAME_DB_READONLY=true
GAME_WRITES_ENABLED=false
~~~

**APP_DB_ADMIN_\*** is a temporary provisioning connection. On the first
**./docker/compose up --detach --build**, the application container
automatically:

1. Creates **MuOnline_Web** when it does not exist.
2. Creates the restricted **muonline_web_app** SQL login and database user.
3. Grants data access and only the schema permissions Laravel migrations need
   inside **MuOnline_Web**.
4. Creates the separate restricted **muonline_game_web** login and user inside
   the existing database selected by **GAME_DB_MUONLINE**.
5. Grants only the object permissions required by the supported game adapters.
6. Installs the selected profile's idempotent schema extension when required.
7. Runs application migrations and the idempotent public seeders.

When the game database is on the same SQL Server, empty
**GAME_DB_ADMIN_\*** values reuse **APP_DB_ADMIN_\*** only during bootstrap. If
it is on another SQL Server, set **GAME_DB_ADMIN_USERNAME** and
**GAME_DB_ADMIN_PASSWORD** to temporary administrator credentials for that
server. The game runtime password must contain at least 16 characters with an
uppercase letter, lowercase letter, number and symbol.

The container fails closed if any step fails. The **sa** login is never used as
Laravel's normal connection and receives no application request.

After the app log reports that **MuOnline_Web** is ready, immediately change:

~~~dotenv
APP_DB_BOOTSTRAP=false
APP_DB_ADMIN_USERNAME=
APP_DB_ADMIN_PASSWORD=
GAME_DB_ADMIN_USERNAME=
GAME_DB_ADMIN_PASSWORD=
~~~

Then recreate the services so the administrator secret is removed from every
container environment:

~~~bash
./docker/compose up --detach --force-recreate
~~~

Keep the application and game runtime credentials; future starts then use only
their restricted logins. The SQLCMD templates under **docker/sql** remain
fallbacks for environments where automatic bootstrap is intentionally
disabled.

## Creating the first administrator

Website administrators are application-owned identities and are separate from
MuOnline game accounts. After the application migrations have completed,
create the first full administrator interactively inside the application
container:

```bash
./docker/compose exec app php artisan admin:create
```

Existing identities can be inspected and maintained through the manager-facing
CLI without SQL edits:

```bash
./docker/compose exec app php artisan admin:list --json --no-interaction
./docker/compose exec app php artisan admin:update 2 --active=0 --json --no-interaction
./docker/compose exec app php artisan admin:update 2 --active=1 --json --no-interaction
./docker/compose exec app php artisan admin:update 2 --password
```

Replace `2` with an ID returned by `admin:list`. Use `--name` and `--email` for
identity changes. Automation can rotate passwords with `--password-stdin`,
passing one password line through private stdin rather than command arguments.
Use deactivation for identities referenced by audit history. `admin:delete ID`
requires confirmation (`--force` for automation); the last active full
administrator cannot be deleted or deactivated. JSON mutation errors return
`errors` and exit code 1; successful commands return exit code 0.

The `admin:create` command asks for the administrator name, email address, password and
password confirmation. The password is hidden while it is entered and must be
at least 12 characters long with uppercase and lowercase letters, a number and
a symbol. With bcrypt, passwords must fit within 72 bytes (Unicode characters
can occupy multiple bytes); null bytes are rejected. Existing overlong bcrypt
passwords must be reset through the CLI rather than accepted with a truncated
suffix. The email address must not already belong to another website
administrator.

Open `http://localhost/admin/login` and sign in with the new administrator
email and password. On production, use the same command against the running
production `app` container and open `https://mu.example.com/admin/login` with
your configured domain. Do not create administrator records manually in the
database and do not reuse a game-account password.

## Demo mode

A public demonstration can expose the complete read experience while preventing
visitors from changing administration, account identity or payment data. Enable
the restriction and list the disposable game Account IDs that may execute real
player operations:

```dotenv
DEMO_MODE=true
DEMO_ACCOUNTS=test1,test2,test3
```

Account IDs are comma-separated, trimmed and matched without letter-case
sensitivity. Listed accounts may use player operations such as resets, stat
changes, storage, banks, Market and Web Shop. Other authenticated accounts may
view the same pages and request non-persistent previews, but their write
requests receive a localized `403` demo-mode response.

Demo mode always blocks administrator mutations, registration, activation,
password recovery, credential changes, account deletion and payment processing,
including for a listed demo account. Administrator and player login, logout and
language selection remain available. Keep payment providers disabled on a demo
deployment and use only disposable game accounts and data.

After changing either value, clear cached configuration and recreate long-running
containers:

```bash
./docker/compose exec -T app php artisan optimize:clear
./docker/compose up --detach --force-recreate
```

Set `DEMO_MODE=false` for a normal installation. An empty `DEMO_ACCOUNTS` value
allows no account to execute player mutations while demo mode is active.

## Connecting a MuOnline database

Keep fixture mode and both game-write switches closed until the website works
locally:

```dotenv
GAME_DATA_SOURCE=fixture
GAME_DB_READONLY=true
GAME_WRITES_ENABLED=false
```

To test real read-only data, configure the `GAME_DB_*` values in the root
`.env`, set `GAME_DATA_SOURCE=database` and keep writes disabled. If the
restricted game login does not exist yet, enable **APP_DB_BOOTSTRAP=true** for
one app-container start so it is created automatically. Run the compatibility
and privilege check:

```bash
./docker/compose exec -T app php artisan game:doctor
```

Each supported profile validates its own compatible schema and receives only
its adapter-specific SQL permissions. The application does not run Laravel
migrations against game-owned tables; reviewed profile extensions such as the
1.0M **GrandResets** column are installed idempotently during bootstrap. SQL
Server schema fixtures remain test references and must not be passed to Laravel
migrations.

The bootstrap creates a dedicated SQL Server login with only the permissions
required by the supported adapters. The template at
`docker/sql/create-game-web-login.sql` is retained only as a manual fallback.
`game:doctor` rejects `sysadmin`, `db_owner`, unrelated mutation permissions
and missing schema objects.

Only enable writes after all of the following are true:

1. The connection targets a cloned database and dedicated test game server.
2. Native MuOnline and application database backups have been verified.
3. `game:doctor` passes with the restricted login.
4. Read-only pages display the expected accounts, characters, warehouses and
   items.
5. Every enabled mutation has been tested with disposable accounts.

### Enabling live game writes

The safe defaults are a commissioning mode, not the final configuration for a
fully functional website. While either switch remains closed, MuOnline Web can
read live records but rejects game-account registration, password changes,
character actions, moderation, item delivery, market settlement and other
mutations with a service-unavailable result.

After `game:doctor` passes and the checklist above is complete, configure the
operational values:

```dotenv
GAME_DATA_SOURCE=database
GAME_DB_READONLY=false
GAME_WRITES_ENABLED=true
```

`GAME_DB_READONLY` is the connection safety switch and
`GAME_WRITES_ENABLED` is the application-wide mutation switch. Both must permit
writes. The separate rankings connection remains read-only regardless of these
values.

Apply the changed environment to every long-running container:

```bash
./docker/compose exec -T app php artisan optimize:clear
./docker/compose up --detach --force-recreate
```

Do not run `game:doctor` as a readiness check after opening writes. Its startup
diagnostic deliberately expects the commissioning values
`GAME_DB_READONLY=true` and `GAME_WRITES_ENABLED=false`; run it before changing
the switches, and close them temporarily whenever the SQL permissions or game
schema must be re-audited.

## Credits payment providers

Credits packages are managed from **Admin → Economy → Credits packages**. A
provider appears to players only when it is explicitly enabled and all of its
required secrets are present. Keep sandbox mode enabled until checkout,
webhook, replay and refund scenarios pass end to end.

Configure the relevant `.env` values from `.env.example`. Register these public
webhook URLs with the providers:

```text
https://mu.example.com/payments/webhooks/stripe
https://mu.example.com/payments/webhooks/paypal
https://mu.example.com/payments/webhooks/revolut
```

Stripe requires its secret key and endpoint signing secret. PayPal requires an
application client ID, secret and the registered webhook ID. Revolut requires
the Merchant API secret key and the webhook signing secret. Never place these
values in the database or commit them to source control.

See the dedicated [Credits payments configuration guide](payments.md) for the
required provider dashboard steps, webhook events, complete `.env` reference,
sandbox testing and production checklist.

## Production deployment

Production requires a public domain whose DNS points to the Docker host. Ports
80 and 443 must be reachable for the initial Let's Encrypt challenge and HTTPS
traffic.

Create the environment files and configure the root `.env` for production. In
`docker/.env`, set:

```dotenv
DOCKER_ENV=production
APP_DOMAIN=mu.example.com
```

Set at least these root `.env` values before starting production:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mu.example.com
APP_KEY=base64:replace-with-a-generated-key
MAIL_MAILER=log

DB_CONNECTION=sqlsrv
DB_HOST=192.168.0.105
DB_PORT=1433
DB_DATABASE=MuOnline_Web
DB_USERNAME=muonline_web_app
DB_PASSWORD=replace-with-a-long-random-runtime-password
DB_ENCRYPT=true
DB_TRUST_SERVER_CERTIFICATE=false
AUTO_MIGRATE=true

APP_DB_BOOTSTRAP=true
APP_DB_SEED=true
APP_DB_ADMIN_USERNAME=sa
APP_DB_ADMIN_PASSWORD=replace-with-the-current-sa-password

GAME_DATA_SOURCE=database
GAME_DB_CONNECTION=sqlsrv
GAME_DB_HOST=192.168.0.105
GAME_DB_PORT=1433
GAME_DB_MUONLINE=MuOnline
GAME_DB_USERNAME=muonline_game_web
GAME_DB_PASSWORD=replace-with-a-strong-runtime-password-123!
GAME_DB_ADMIN_USERNAME=
GAME_DB_ADMIN_PASSWORD=
GAME_DB_READONLY=true
GAME_WRITES_ENABLED=false
```

These are intentionally safe commissioning values for the first deployment.
They allow schema, permission and read-model verification but do not provide a
fully operational account panel. Complete the production checks below, then
follow [Enabling live game writes](#enabling-live-game-writes) to activate
mutations.

Generate a 32-byte secret in a safe environment if an application key is not
already available. Prefix the generated Base64 value with `base64:` when
placing it in `APP_KEY`, and do not commit it:

```bash
openssl rand -base64 32
```

Build and start the immutable images. The first app container start provisions
and migrates **MuOnline_Web** before PHP-FPM becomes available:

```bash
./docker/compose up --detach --build
./docker/compose logs --follow app
```

After the successful first start, clear all **APP_DB_ADMIN_\*** and
**GAME_DB_ADMIN_\*** values, set **APP_DB_BOOTSTRAP=false**, and recreate the
services as described in
[Automatic SQL Server database first start](#automatic-sql-server-database-first-start).
Run **game:doctor** while game writes remain closed. Before accepting players,
verify the read-only pages and backups, enable live game writes as documented
above, recreate the services, and complete the disposable-account mutation
tests.

The production Nginx container requests the first certificate and redirects
traffic to HTTPS. Certificate renewal runs twice daily and reloads Nginx only
after a successful renewal. The queue and scheduler containers start with the
same immutable application image.

### Deployment behind an existing VPS reverse proxy

When the host already owns ports 80/443 and terminates TLS, use the external
Compose mode instead of the standalone production Nginx topology. Configure
`docker/.env` with the shared Docker network and a stable container name:

```dotenv
DOCKER_ENV=external
APP_DOMAIN=mu.example.com
NGINX_CONTAINER_NAME=muonline-web-nginx
EXTERNAL_DOCKER_NETWORK=server1_net
```

The external mode uses the production application, queue, scheduler and Redis
services, but replaces the TLS-enabled Nginx image with an HTTP-only internal
image. It publishes no host ports, exposes port 80 only to Docker networks and
joins the configured external network with the stable alias
`muonline-nginx`. The VPS reverse proxy should forward HTTPS requests to:

```nginx
proxy_pass http://muonline-nginx:80;
proxy_set_header Host $host;
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header X-Forwarded-Proto https;
proxy_set_header X-Forwarded-Host $host;
proxy_set_header X-Forwarded-Port 443;
```

Create the external network once if the VPS reverse-proxy stack has not already
created it. Do not recreate an existing shared network:

```bash
docker network inspect server1_net >/dev/null 2>&1 \
    || docker network create server1_net
```

Deploy with the same commands used for production. Docker Compose will combine
`docker-compose.prod.yaml` with `docker-compose.external.yaml` automatically.
The public certificate and renewal process remain exclusively in the VPS
reverse-proxy stack.

Use `/up` for process liveness and `/ready` for deployment readiness. The
readiness endpoint checks the application database, cache and read-only game
connection without exposing connection details.

## Scheduler and queue

Do not add host cron entries for Laravel. The Compose stack runs
`php artisan schedule:work` in a dedicated scheduler container. Production also
runs `php artisan queue:work` in a dedicated queue container. These services
handle expiring listings, auction settlement, VIP expiry, online-time Credits
collection and recovery retries. Do not enable Online Time Rewards without a
healthy scheduler: `credits:collect-online-time` creates the bounded account
checkpoints every minute, while `credits:sync-item-exchanges` safely resumes
interrupted item exchanges.

Check them after every deployment:

```bash
./docker/compose ps
./docker/compose logs --tail=100 scheduler queue
```

## Backup and restore rehearsal

The **MuOnline_Web** application database and **MuOnline** game database have
different owners and must be backed up independently, even when they share one
SQL Server instance. The restricted web logins must not receive SQL Server
backup or restore permissions.

Use a SQL Server administrator outside MuOnline Web to create separate native
**COPY_ONLY** backups of **MuOnline_Web** and **MuOnline** with checksums. Store
both files off-host, run **RESTORE VERIFYONLY**, then restore both into separate
rehearsal databases. The **app:backup** command remains available only for
fixture/development installations that deliberately use SQLite.

For an application restore rehearsal:

1. Use a disposable deployment with both game-write switches closed.
2. Stop its app, queue and scheduler containers.
3. Restore the **MuOnline_Web** and **MuOnline** backups under rehearsal
   database names and point the disposable deployment at them.
4. Start the stack and run **migrate:status**, **about** and **game:doctor**.
5. Smoke-test public, account and administrator authentication.

Never rehearse a restore over the only production copy. Record the Git commit,
backup checksums, migration state, test results and rollback decision for every
production release.

## Removing the application database from Windows Manager

The opt-in CLI boundary for SQL cleanup is:

```bash
php artisan app:database:uninstall --force --no-interaction
```

The manager must invoke it with argument arrays and send one JSON object through
private stdin, then close stdin. Credentials must not appear in command-line
arguments, process environment, saved files or logs:

```json
{
  "host": "sql-host",
  "port": 1433,
  "database": "MuOnline_Web",
  "login": "muonline_web_app",
  "admin_username": "sa",
  "admin_password": "<temporary password from the manager password field>",
  "confirmed": true
}
```

The host, port, database and login must exactly match the effective Laravel
`database.connections.sqlsrv` configuration. A nonempty `DB_URL` override is
refused; use explicit connection fields. Both `--force` and boolean
`confirmed: true` are required. `APP_DB_BOOTSTRAP` does not need to be enabled.
The temporary account must be `sysadmin` so metadata inspection cannot silently
miss mappings into other databases; `sa` is not the only accepted admin login.
Existing bootstrap credentials in the environment are never used as a fallback.

Preflight refuses system/game databases, the configured game/ranking/BattleCore
logins, privileged or shared runtime logins, non-web schemas and web databases
containing other SQL users. It requires the bootstrap application role and user
and checks for application/game table signatures. If the login exists, mappings
and database ownership are inspected across every other database. If a database
is offline or metadata cannot be inspected, removal fails closed. Game data,
game login permissions and profile-added game schema are deliberately retained.

The command always emits one JSON result and exits 0 only on complete success:

```json
{"ok":true,"code":"uninstalled","database":"removed","login":"removed","retryable":false}
```

Resource statuses are `not_checked`, `retained`, `removed`, `already_absent` or
`unknown` after an uncertain SQL failure. Refusals and SQL failures exit 1, with
a bounded code such as `confirmation_required`, `invalid_input`,
`target_mismatch`, `protected_database`, `protected_login`, `sysadmin_required`,
`privileged_login`, `shared_login`, `shared_database`, `cannot_verify_shared_login`,
`unverified_web_database`, `database_unavailable` or `sql_operation_failed`.
No raw SQL exception or credentials are returned. A repeated call rechecks the
target and finishes any remaining resource; two absent resources are a success.
DROP operations are not transactional: a removed database is never described as
rolled back merely because login removal failed.

Manager sequence:

1. Obtain explicit confirmation of the displayed SQL endpoint and exact web
   database/login. Offer backup before irreversible database removal.
2. Stop web requests, scheduler and queue workers so they cannot reconnect.
3. Run this CLI in a one-shot application container with `--entrypoint php`,
   bypassing the bootstrap entrypoint; provide private JSON stdin. Keep the
   application configuration and network available for this step and retries.
4. If exit code is nonzero, JSON is missing/malformed or `ok` is not true, retain
   the installation and show the refusal/progress; do not remove WSL/Docker yet.
5. Only after successful SQL cleanup remove the manager-owned runtime resources.
   Removing unrelated Docker/WSL installations is outside this command's scope.

## Updating

Before updating, read the release notes, create both backups and keep game
writes closed. Check that no tracked files have been modified locally:

```bash
git status --short
```

Investigate and preserve any reported changes before continuing. To update an
installation that follows the latest development, switch to `main` and perform
a fast-forward-only pull:

```bash
git switch main
git pull --ff-only origin main
```

To update a production installation to a published release, fetch the tags and
switch to the selected newer tag instead. Replace `v1.2.4` with the release
being deployed:

```bash
git fetch origin --tags --prune
git switch --detach v1.2.4
git describe --tags --exact-match
```

After selecting either `main` or a tag, rebuild the images, run `game:doctor`,
apply application migrations and restart the stack:

```bash
./docker/compose build
./docker/compose run --rm app php artisan game:doctor
./docker/compose run --rm app php artisan migrate --force --no-interaction
./docker/compose up --detach
```

For the complete module list, see the [feature catalogue](features.md). For
architecture and development conventions, see the
[current implementation state](current-state.md).
