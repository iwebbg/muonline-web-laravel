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
./docker/compose exec -T app php artisan test --compact
./docker/compose down
```

### Automatic SQL Server application database first start

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
~~~

**APP_DB_ADMIN_\*** is a temporary provisioning connection. On the first
**./docker/compose up --detach --build**, the application container
automatically:

1. Creates **MuOnline_Web** when it does not exist.
2. Creates the restricted **muonline_web_app** SQL login and database user.
3. Grants data access and only the schema permissions Laravel migrations need
   inside **MuOnline_Web**.
4. Runs application migrations and the idempotent public seeders.

The container fails closed if any step fails. The **sa** login is never used as
Laravel's normal connection and receives no application request.

After the app log reports that **MuOnline_Web** is ready, immediately change:

~~~dotenv
APP_DB_BOOTSTRAP=false
APP_DB_ADMIN_USERNAME=
APP_DB_ADMIN_PASSWORD=
~~~

Then recreate the services so the administrator secret is removed from every
container environment:

~~~bash
./docker/compose up --detach --force-recreate
~~~

Keep **DB_USERNAME**, **DB_PASSWORD** and **AUTO_MIGRATE=true**; future starts
then use only the restricted runtime login. The fallback SQLCMD template is
**docker/sql/create-application-database-97d+99i.sql** for environments where
automatic bootstrap is intentionally disabled.

## Creating the first administrator

Website administrators are application-owned identities and are separate from
MuOnline game accounts. After the application migrations have completed,
create the first full administrator interactively inside the application
container:

```bash
./docker/compose exec app php artisan admin:create
```

The command asks for the administrator name, email address, password and
password confirmation. The password is hidden while it is entered and must be
at least 12 characters long with uppercase and lowercase letters, a number and
a symbol. The email address must not already belong to another website
administrator.

Open `http://localhost/admin/login` and sign in with the new administrator
email and password. On production, use the same command against the running
production `app` container and open `https://mu.example.com/admin/login` with
your configured domain. Do not create administrator records manually in the
database and do not reuse a game-account password.

## Connecting a MuOnline database

Keep fixture mode and both game-write switches closed until the website works
locally:

```dotenv
GAME_DATA_SOURCE=fixture
GAME_DB_READONLY=true
GAME_WRITES_ENABLED=false
```

To test real read-only data, configure the `GAME_DB_*` values in the root
`.env`, set `GAME_DATA_SOURCE=database` and keep writes disabled. Run the
compatibility and privilege check:

```bash
./docker/compose exec -T app php artisan game:doctor
```

The first supported profile expects the DarksTeam 97d+99i schema. The
application does not migrate or modify game-owned tables. `script.sql` is a
reference export only and must not be passed to Laravel migrations.

Use a dedicated SQL Server login with only the permissions required by the web
adapter. The template at `docker/sql/create-game-web-login.sql` is the starting
point for the current profile. `game:doctor` rejects `sysadmin`, `db_owner`,
unrelated mutation permissions and missing schema objects.

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

After the successful first start, clear both **APP_DB_ADMIN_\*** values, set
**APP_DB_BOOTSTRAP=false**, and recreate the services as described in
[Automatic SQL Server application database first start](#automatic-sql-server-application-database-first-start).
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
handle expiring listings, auction settlement, VIP expiry and recovery retries.

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
