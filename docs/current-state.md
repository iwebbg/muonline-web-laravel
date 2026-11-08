# Current implementation state

This document is the handoff entry point for a new development thread. Read it
together with `AGENTS.md`, `docs/coding-standards.md`, `docs/product-ux.md` and
`docs/roadmap.md` before changing application code.

## Product baseline

MuOnline Web is a Laravel 13 monolith running on PHP 8.5. It supports the
DarksTeam `darksteam_97d99i` and `darksteam_1_0m` game and event profiles. The
frontend is server-rendered Blade enhanced with Alpine.js and Tailwind CSS v4;
it is intentionally not a separate SPA or Next.js application.

English is the default and fallback locale. Every player-facing or
administrator-facing string must also have a Bulgarian translation. Do not
show framework, database-table or internal transaction terminology to players.

## Required development workflow

1. Read `AGENTS.md` and `docs/coding-standards.md` before editing code.
2. If `.ai/rules` exists, read its index and every path-matching rule.
3. Activate the relevant project skill from `.claude/skills` for Laravel,
   Tailwind CSS, testing or deployment work.
4. Inspect sibling files and reuse the established action, request, component
   and test patterns.
5. Use `apply_patch` for manual file edits and Artisan generators for new
   Laravel classes, migrations and tests.
6. Run the narrowest meaningful tests. Broad test runs are reserved for
   cross-cutting or release-level changes; small UI-only changes do not need the
   complete suite.
7. Run `vendor/bin/pint --dirty --format agent` after changing PHP.
8. Keep commits focused and frequent so each completed behavior is traceable.

## Runtime and containers

- Laravel `^13.17`, PHP `^8.5`, PHPUnit `^12.5`.
- Tailwind CSS v4 through `@tailwindcss/vite`, Vite 8 and Alpine.js 3.
- Nginx owns local port 80 and proxies to the PHP-FPM application container.
- Redis backs cache, locks and scheduler coordination.
- Microsoft ODBC Driver 18 and `sqlsrv`/`pdo_sqlsrv` connect to MuOnline SQL
  Server.
- `./docker/compose` selects the local, standalone production or external
  reverse-proxy topology through `DOCKER_ENV`. `APP_DOMAIN` supplies the public
  hostname, while external mode also accepts `EXTERNAL_DOCKER_NETWORK` and a
  stable `NGINX_CONTAINER_NAME`.
- Laravel and game settings stay in the root `.env`; do not create additional
  environment files for each service.

Start the local stack with:

```bash
cp docker/.env.example docker/.env
./docker/compose up --detach --build
```

Useful verification commands:

```bash
./docker/compose exec -T app php artisan migrate --force
./docker/compose test tests/Feature/RelevantTest.php
./docker/compose exec -T app php artisan view:clear
vendor/bin/pint --dirty --format agent
```

Automated tests run only in a disposable container with no network access. The
container uses SQLite in memory, mounts the source read-only and is removed as
soon as PHPUnit exits. It is not part of the long-running application stack.

SQL Server adapters have a separate integration workflow. It starts an internal
SQL Server 2022 Developer container, loads the sanitized 97d+99i schema from
`tests/Fixtures/SqlServer/97d99i-schema.sql`, runs only `tests/Integration`,
then removes its containers and database volume:

```bash
./docker/compose test-integration
```

The 1.0M adapter has an equivalent isolated workflow backed by
`tests/Fixtures/SqlServer/1.0m-schema.sql` and `tests/Integration1_0M`:

```bash
./docker/compose test-integration-1.0m
```

## Database ownership and write safety

The application database and MuOnline database have separate ownership. A live
deployment normally hosts both on one SQL Server instance as **MuOnline_Web**
and **MuOnline**, with distinct restricted logins:

- Laravel migrations modify only application-owned tables.
- A one-time **APP_DB_BOOTSTRAP=true** container start may use temporary
  administrator credentials to create **MuOnline_Web** and its runtime login.
  Those credentials are removed immediately after provisioning.
- The exported `tests/Fixtures/SqlServer/97d99i-schema.sql` and
  `tests/Fixtures/SqlServer/1.0m-schema.sql` files, plus every live MuOnline
  schema, are external legacy contracts and are never migrated by Laravel.
- Version adapters under `app/Game/Versions` and repositories under
  `app/Game/Repositories` isolate legacy table and binary-layout details.
- All game mutations pass through typed actions and repository contracts.
- Ownership, offline state, current values, available space and exact item
  bytes are rechecked at the write boundary.
- Multi-column operations are transactional and idempotent where a browser or
  scheduler can retry them.
- `GAME_DB_READONLY=true` and `GAME_WRITES_ENABLED=false` are the safe defaults.
  Both switches must permit writes before any game mutation runs.
- A fully operational live account panel therefore uses
  `GAME_DB_READONLY=false` and `GAME_WRITES_ENABLED=true` only after the
  read-only `game:doctor` commissioning check and disposable-account tests pass.

## Architecture patterns

### Version profiles

Character classes, class progressions, base stats, supported attributes, item
catalogs and legacy mappings belong to a `GameVersionProfile`. Controllers and
views consume profile contracts and must not spread 97d+99i or 1.0M class codes
and schema names across the application. Every future version adds a new profile
and adapter implementation without rewriting existing profile behavior.

### Actions and previews

Controllers authorize the request, delegate to a typed action and translate a
safe domain result into a response. Character operations use separate preview
and execute boundaries. Reset, grand reset, stat allocation, stat reset, PK
clear and move-to-town show requirements, cost and resulting values before a
write is confirmed.

### Module isolation

Player-facing features are represented by `App\Modules\ServerModule` and can be
enabled or disabled independently from Administration. A disabled module is
removed from navigation and its route returns `404`, while recovery and audit
data remain intact. New modules should follow the same manager, middleware,
navigation and configuration pattern.

Current switches cover registration, password recovery, account security,
Credits, vote rewards, VIP, Web Storage, Jewel Bank, Zen Bank, Market, Auction,
Web Shop, Mystery Boxes and Rewards.

### Exact-item custody

Market, Auction, Web Vault and Jewel Bank preserve the original adapter-owned
item bytes, including serial and option bytes. Items are never recreated from a
name when an original item is in custody.

`web_storage_items.storage_context` separates module ownership:

- `web_vault` records appear in the account Vaults workspace and include direct
  deposits plus safe Market/Auction fallbacks.
- `jewel_bank` records appear only in Jewel Bank balances and history.

Do not filter these modules only by item name or item code. A jewel delivered
from Market may legitimately belong to Web Vault, while a Jewel Bank record
must not be exposed through the generic Web Storage withdrawal endpoint.

Jewel Bank groups identical jewels by type and level while retaining every
physical item as an individual custody record. Withdrawal asks for quantity and
destination: Main Vault or a selected Character Inventory. Destination
ownership and free rectangles are verified again before delivery. Insufficient
space leaves the item protected for scheduler retry.

## Implemented product areas

### Public site

- Database-backed current server and external server directory.
- Public `/server/status` endpoint, remote status polling and ten-minute cache.
- Real online/offline detection rather than a hard-coded badge.
- Interactive home page event countdowns using database server time when
  available and a safe application-clock fallback.
- Full event schedule with configurable DarksTeam custom events.
- Rich rankings with pagination, search, class filters, ordering, PK ranking,
  character portraits and decoded guild marks.
- Public character and guild profile pages.
- Bilingual News, Guides, Downloads and general content pages.
- Seeded legal pages, cookie policy/banner, privacy policy and terms of use.
- Database-driven footer server name and InterWeb Ltd. attribution.

### Authentication and account lifecycle

- Legacy-compatible `MEMB_INFO` player authentication with configurable
  encrypted or plain legacy password mode.
- Separate Laravel administrator identities and permission-aware Game Masters.
- Registration writes the required game account records without exposing table
  names to the player.
- Optional Google reCAPTCHA v3 credentials from `.env`.
- Email activation, password recovery and account credential updates.
- Account deletion request with email-code confirmation.
- Separate account pages for Credits, Votes, Security, Jewel Bank and Zen Bank.

### Character and storage experience

- Version-driven character cards, artwork, stats and progression names.
- Query-string state such as `?character=...&tab=...` survives reloads and
  mutations.
- Equipment silhouette, inventory bag and multi-vault warehouse rendering.
- Catalog-backed item images, dimensions, rarity names and option tooltips.
- Large desktop layouts place related item grids side by side and collapse them
  responsively on smaller screens.
- Item tooltips and dialogs use teleported, high-z-index overlays so they do not
  render behind grids or sticky sections.
- Web Vault and Jewel Bank are account-scoped rather than character-scoped.
- Reset, grand reset, stats, stat reset, PK clear and move-to-town use previews
  and admin-controlled requirements.

### Economy and community

- Credits wallet and immutable transaction history.
- VIP plans and purchases.
- Vote destinations, claims and administrator review.
- Recoverable Zen Bank ledger.
- Physical-item Jewel Bank with grouped deposits and withdrawals.
- Exact-item Web Vault.
- Fixed-price Market and timed Auction with escrow, held bids, settlement,
  seller payout and Web Vault fallback when game storage is full.
- Items Shop offers, a version-aware Item Generator and configurable Mystery
  Boxes purchased with Credits.
- Mystery Boxes release either one weighted random item or every configured
  item, and clearly show that policy without exposing their contents.
- Account Rewards preserve immutable purchase/grant snapshots and independent
  delivery retry state for every released item.

### Administration and operations

- Separate administrator login and permissions.
- Game Master management with granular capabilities.
- Module configuration, reset rules, server directory and event schedules.
- Bilingual News/Guide content and Downloads management.
- Wallet, VIP, vote, market, moderation and item-grant tools.
- Web Shop offer and Mystery Box pool management with direct account grants.
- Shared immutable audit log and economy recovery screens.
- Readiness/liveness endpoints, application backup command, least-privilege SQL
  template, scheduler/queue services and production Nginx/TLS configuration.

## UI and design system

The visual implementation lives primarily in `resources/css/app.css`, Blade
components under `resources/views/components`, and Alpine modules under
`resources/js/muonline.js`.

### Tailwind CSS v4 rules

- Use CSS-first Tailwind v4 configuration with `@import 'tailwindcss'` and
  `@theme`; do not add `tailwind.config.js` or legacy `@tailwind` directives.
- Reuse tokens such as `void`, `panel`, `mist`, `silver`, `steel`, `gold` and
  `crimson` instead of inventing near-duplicate colours.
- Prefer existing component classes: `shell`, `panel-card`, `button`,
  `button-primary`, `button-ghost`, `field-group`, `segmented-control`,
  `kicker`, `status-pill` and item-grid classes.
- Start with the mobile layout and add `sm`, `lg`, `xl` or `2xl` behavior only
  when the content benefits from it.
- Use `gap` for sibling spacing and keep visible focus states.
- Fixed-header anchors use `scroll-mt-28`.

### Interaction rules

- Use regular named Laravel routes as the authoritative action.
- Alpine.js may control tabs, modals, local previews, selectors and disclosure,
  but it must not duplicate authorization or mutation rules.
- Persist meaningful workspace state in query parameters, not fragile browser
  memory. Reloading a deep account URL must reconstruct the same tab and
  character.
- For filters that require a page load, use a stable section anchor so a desktop
  user is returned to the changed controls rather than the top of the page.
- Teleport dialogs to `body`, include an opaque overlay, close on backdrop and
  Escape, and keep them above inventory tooltips.
- Use modals for focused account changes rather than stacking unrelated forms
  on the character workspace.
- Never show internal labels such as transaction class names, Laravel, database
  tables, escrow byte coordinates or implementation-specific terminology to a
  player.

### Item presentation

- Decode display data through the active version catalog.
- Preserve item width and height in warehouse/inventory grids.
- Excellent item names use the established green tone; special/wing and rarity
  tones come from item metadata rather than hard-coded per screen.
- Tooltips show the friendly item name, level, durability, luck, skill,
  excellent options and ancient option where supported.
- Raw identifiers such as `12:5` or `8:3` are diagnostic data and must not be
  shown in player UI.

## Current next priorities

1. Continue manual end-to-end testing against the dedicated 97d+99i and 1.0M
   SQL Server databases and test game servers, one player operation at a time.
2. Finish UI polish and responsive review after functional defects are closed.
3. Add real vote-provider callbacks; the current workflow supports controlled
   administrator verification.
4. Certify the implemented signed and idempotent PayPal, Revolut Pay and Stripe
   Credits integrations against each provider's sandbox and live checklist.
5. Design the remaining optional Web Event capabilities around the established
   version and module boundaries.
6. Run concurrency and failure-recovery tests for Market, Auction, Web Shop,
   Rewards, Jewel Bank, Web Vault and Zen Bank before production.
7. Complete the production security review, backup/restore rehearsal and
   least-privilege deployment checklist.

## Starting a new thread

Give the new thread the repository path and ask it to read, in order:

1. `AGENTS.md`
2. `docs/current-state.md`
3. `docs/coding-standards.md`
4. `docs/product-ux.md`
5. `docs/roadmap.md`
6. `docs/reference-sources.md` only when legacy behavior or assets are needed

The working tree should be clean before new work begins. Inspect the latest Git
commits and preserve existing user changes. Do not restart the architecture or
replace Blade/Alpine with another frontend stack unless the project owner asks
for that decision explicitly.
