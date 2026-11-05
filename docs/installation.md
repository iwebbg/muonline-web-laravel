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
- A supported SQL Server reachable from the containers when using live game
  data.
- A dedicated MuOnline test server before enabling any write operation.

## Local installation

Clone the repository and enter its directory, then create the two environment
files:

```bash
cp .env.example .env
cp docker/.env.example docker/.env
```

The default configuration uses fixture game data, SQLite for application-owned
records and safe game-write switches. Build the frontend and start the stack:

```bash
npm ci
npm run build
./docker/compose up --detach --build
```

Generate the Laravel application key and create the application tables:

```bash
./docker/compose exec -T app php artisan key:generate
./docker/compose exec -T app php artisan migrate --force --no-interaction
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
`.env`, change `GAME_DATA_SOURCE` to the configured live source and keep writes
disabled. Run the compatibility and privilege check:

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
GAME_DB_READONLY=true
GAME_WRITES_ENABLED=false
```

Generate a 32-byte secret in a safe environment if an application key is not
already available. Prefix the generated Base64 value with `base64:` when
placing it in `APP_KEY`, and do not commit it:

```bash
openssl rand -base64 32
```

Build the immutable images, verify the game connection and apply only the
application-owned migrations:

```bash
./docker/compose build
./docker/compose run --rm app php artisan game:doctor
./docker/compose run --rm app php artisan migrate --force --no-interaction
./docker/compose up --detach
```

The production Nginx container requests the first certificate and redirects
traffic to HTTPS. Certificate renewal runs twice daily and reloads Nginx only
after a successful renewal. The queue and scheduler containers start with the
same immutable application image.

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

The application database and MuOnline database have different owners and must
be backed up independently. The restricted web login must not receive SQL Server
backup or restore permissions.

Create and copy an integrity-checked application database backup:

```bash
./docker/compose exec -T app php artisan app:backup --name=pre-deploy.sqlite
mkdir -p backups
./docker/compose cp app:/var/www/html/storage/app/private/backups/pre-deploy.sqlite backups/pre-deploy.sqlite
```

Store the copied backup off-host. A file that exists only in a Docker volume is
not a disaster-recovery backup.

Use a SQL Server administrator outside MuOnline Web to create a native
`COPY_ONLY` game database backup with checksums. Run `RESTORE VERIFYONLY`, then
perform a full restore into a separate rehearsal database.

For an application restore rehearsal:

1. Use a disposable deployment with both game-write switches closed.
2. Stop its app, queue and scheduler containers.
3. Preserve the current SQLite file and place the copied snapshot at the
   configured `DB_DATABASE` path in the application storage volume.
4. Start the stack and run `migrate:status`, `about` and `game:doctor`.
5. Smoke-test public, account and administrator authentication.

Never rehearse a restore over the only production copy. Record the Git commit,
backup checksums, migration state, test results and rollback decision for every
production release.

## Updating

Before updating, read the release notes, create both backups and keep game
writes closed. Then pull the selected release, rebuild images, run
`game:doctor`, apply application migrations and restart the stack:

```bash
git pull --ff-only
./docker/compose build
./docker/compose run --rm app php artisan game:doctor
./docker/compose run --rm app php artisan migrate --force --no-interaction
./docker/compose up --detach
```

For the complete module list, see the [feature catalogue](features.md). For
architecture and development conventions, see the
[current implementation state](current-state.md).
