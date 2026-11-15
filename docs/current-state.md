# Current implementation state

This document is the handoff entry point for a new development thread. Read it
together with `AGENTS.md`, `docs/coding-standards.md`, `docs/product-ux.md` and
`docs/roadmap.md` before changing application code.

## Product baseline

MuOnline Web is a Laravel 13 monolith running on PHP 8.5. It supports the
DarksTeam `darksteam_97d99i` and `darksteam_1_0m` profiles plus the isolated
`muemu_season_6` game and event profile. `mudevs_season_21` is now selectable for
reviewed reads, event schedules and content administration; game mutations and
the full storage/economy bundle remain in development. The frontend is server-rendered Blade
enhanced with Alpine.js and Tailwind CSS v4; it is intentionally not a separate
SPA or Next.js application.

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
views consume profile contracts and must not spread 97d+99i, 1.0M or MuEMU
class codes and schema names across the application. Every future version adds
a new profile and adapter implementation without rewriting existing behavior.

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

## MuEMU Season 6 Update 32 profile

The selectable `muemu_season_6` profile was implemented without changing the
existing DarksTeam adapters. Its compatibility baseline is the
[Louis Emulator Season 6 Update 32 release](https://darksteam.net/threads/mu-server-season-6-louis-update-32-no-limits-crack.36704/),
the owner-supplied extracted installation and the exported MuOnline schema in
the protected root reference `script S6.sql`.

Verified profile behavior includes:

- all 18 class evolution codes across the seven Wizard, Knight, Elf, Magic
  Gladiator, Dark Lord, Summoner and Rage Fighter families;
- base stats from `DefaultClassInfo.txt`, Season 6 map names, public rankings,
  guild marks, account-level VIP display and stock Castle Siege state;
- standard `MasterSkillTree` progression in the private workspace and public
  character profile, limited to Master Level, available points and experience;
- standard Gens family, contribution and family rank in private/public profiles,
  plus a capability-gated public Gens leaderboard. Family rank follows the
  verified MuEMU dense-rank rule and leaves contributions below 10,000 unranked;
- account-scoped Lucky Coin balances from `LuckyCoin`. The web currently treats
  this as read-only because reward exchange rules remain server-configured;
- 938 authoritative item definitions parsed from `Data/Item/Item.txt`, with 908
  supplied editor images and safe image fallbacks for the remaining definitions;
- lossless 16-byte item decoding for level, skill, luck, additional, excellent,
  ancient/set, Harmony, socket and 380 option data. Golden fixtures were created
  from items placed in the running MuEMU client, including Flameberge sockets,
  Sacred Glove, Vicious Dragon Armor and 380 Dragon Knight Armor;
- a 3,776-byte character inventory boundary with 12 equipment cells, a 64-cell
  base bag and two 32-cell extensions selected by `ExtInventory`;
- 3,840-byte Warehouse and ExtWarehouse blobs. The real client/editor check
  placed Arrow +3 in the bottom-right cell and proved that player-visible
  storage ends at slot 119 (`8×15`); the remaining 120 native slots are always
  preserved untouched;
- active extended warehouse routing through `AccountCharacter.ExtWarehouse`,
  exact-item Market/Auction custody, Web/Jewel storage delivery and active-page
  Zen Bank projection;
- native `ResetCount` and `MasterResetCount` character operations, ten account
  character slots, plaintext account lifecycle matching `MD5Encryption = 0`,
  moderation, item serial reservation and offline/idempotency boundaries;
- 258 Item Generator entries with explicit prices imported from the supplied
  legacy webshop SQL. Unpriced newer Season 6 items remain available to audited
  administrator grants and configured offers but are not assigned invented
  generator prices. The generator and administrator grant flows expose the
  verified ancient/set, 380, Harmony and five-socket fields without changing
  the older game profiles;
- 12 stock schedules sourced from `Data/Event/*.dat`, plus the database-backed
  seven-day Castle Siege cycle. Pack-specific Battle Royale, GvG, TvT,
  Marathon, King of MU, Quickly, Hide and Seek, Russian Roulette, Kill All,
  PvP Championship and Auction events are intentionally excluded. The supplied
  `Ranking*` tables are also not claimed as a portable Season 6 standard because
  they belong to this pack's custom ranking module;

MuEMU stores VIP entitlement at account level, while the product's purchasable
VIP contract is character-scoped. Public profiles may display an active
`AccountLevel`, but character VIP purchase is deliberately disabled rather than
writing a semantically different field.

Automated coverage uses exact real-item fixtures and portable adapter schemas.
The full disposable suite passes with 774 tests and 3,967 assertions. Before a
production rollout, complete `game:doctor` and disposable-account mutation
checks against a cloned MuEMU database and dedicated test server. The main
warehouse boundary is manually confirmed; creation and switching of every
extended warehouse page remains part of that commissioning checklist.

## Current next priorities

### MUDevs Season 21 integration foundation

The owner requested full standard Season 21 ep1–2 support for the supplied
MUDevs distribution. The [compatibility matrix and implementation
plan](season-21-integration-plan.md) records the inspected source paths,
16 base class families, fourth/fifth advancement evidence, progression,
maps, standard event candidates, item systems and delivery gates.

Development started on `feat/mudevs-season-21` from `origin/develop` at
`955c754`. The initial code provides the 16 native base-family identifiers,
base stats and normal level-up points, the 121 configured map labels, and a
read-only repository for WCoinC, WCoinP, Goblin Points, Lucky Coin and owned
character Ruud. These initial components were not yet bound to a selectable
profile; see the subsequent activation milestone below. The first foundation commit is `6dcd812`.

The next read-model stage initially added `MuDevsSeason21Profile` outside the
registry, private character and public realm/guild/ranking repositories, and
Master/enhancement reads joined through `CharacterIndex`. Its class catalog
contains the 16 SQL-seeded base codes and now includes client-confirmed Blade
Master (18); other evolution codes remain a commissioning task. Nine owner-supplied
portraits live under `public/images/characters/mudevs_season_21`, with
profile-owned focal points for the landscape/full-body sources. Shared DTOs
keep their previous serialized shape when no custom focal point or enhancement
points are present. Enhancement points are separate from native Master counters;
no unverified total-level formula is applied. S21 VIP expiry uses the configured
game content timezone.

The new `./docker/compose test-integration-s21` command checks these readers
against a sanitized seven-table schema projection in its own disposable SQL
Server database. This is read-adapter coverage, not certification of the full
version bundle. Manual read-only probes using the restricted S21 identity also
successfully read the existing base Dark Knight, public rankings, realm
aggregates, Master counters and five native currency types. No private account
amounts were printed.

Validation for this read-model stage: 872 disposable unit/feature tests with
4,269 assertions; the existing three-profile smoke matrix; 97d+99i integration
(3 tests, 40 assertions), 1.0M integration (10 tests, 51 assertions), and S21
read integration (3 tests, 17 assertions). Pint and Blade compilation pass.

Game coins remain independent from web Credits. Conversion policy and shop
currency selection are undecided; no exchange or automatic substitution is
implemented.

The item metadata stage now bundles all 4,701 S21 source records across 20
native groups, including groups above 15 and indices above 255. The typed native
catalog preserves exact rectangles, equipment slots and every source attribute;
it remains separate from legacy item options and the selectable profile until
binary decoding is verified with known client items. Source inventory constants
identify 239 character slots, with pentagram/earrings at 236–238. Warehouse
geometry, extended-page availability and binary option interpretation remain open.

All shared item preview surfaces now use `<x-item-image>` with a neutral SVG
fallback for missing paths and failed loads, including loads that fail before
Alpine initializes. This applies to existing adapters as well as future S21
presentation. Item dimensions, tooltips and nullable persisted artwork are
preserved; no game writes or currency changes are involved.

Validation for this item metadata/presentation stage: `./docker/compose test-all`
passes 880 unit/feature tests with 18,416 assertions and all three existing
profile smoke checks. Pint and Blade compilation pass. Native S21 item decoding,
generation and writes are not enabled by this stage; known client warehouse
items are the next source of binary fixtures.

The independent S21 event metadata stage adds 11 configured recurring standard
events under `game_events.profiles.mudevs_season_21`: Blood Castle, Devil Square,
Chaos Castle, Castle Deep, Moss Merchant, Group Boss Battle and five reviewed
invasions. It preserves exact irregular times and separates enabled GS/CS host
settings, including Moss's 120-minute main-server duration. Shared reader,
countdown calculation and profile/version-scoped administrator overrides are
reused. The environment has not switched to this profile, and native runtime
phase/activation verification remains required. The integration plan records
explicit gates for every remaining standard activity family and seasonal source.
Validation: 884 unit/feature tests with 18,434 assertions, all three existing
profile smoke checks and Pint pass. Automated checks remain network-isolated;
no live event configuration or game data was changed.

The item commissioning stage now matches seven owner-supplied client tooltips
with saved 25-byte warehouse records, including index 270, three empty sockets,
excellent mask 63 and option 7. Serial-anonymized fixtures cover exact full-blob
round trips. The separate S21 diagnostic record/codec preserves all bytes without
binding legacy item services or enabling creation/transfers. A bundled active
socket catalog contains 231 identities; filled seeds and advanced options remain
unverified. `game:inspect-s21-warehouse` reads account-owned main/extended vaults
and reports verified metadata/raw options; its live read matched all seven items.
Native warehouse reads are also covered by the disposable SQL Server workflow,
whose sanitized projection now includes warehouse and ExtWarehouse (nine tables).
Validation for this stage: 896 unit/feature tests with 18,572 assertions,
four native S21 SQL Server cases with 28 assertions, and Pint pass. Automated
tests use only isolated fixtures; the separate live comparison performed reads
and did not create, update or move game items.

Ancient/Harmony commissioning adds two more distinct client-matched fixtures:
Warrior's Leather Armor +9 with native ancient byte 05 and a plain Kris +9 with
Harmony byte 11 hex (minimum attack +3 in the client). A 1,356-entry source-backed
ancient catalog resolves item-specific variant names and set indices without
calculating full-set activation. Diagnostic snapshots expose the ancient byte
and set identity, plus non-socket weapon/armor Harmony byte/index/level. Socket
items retain a separate socket-bonus interpretation. Unknown options/extension
bytes still round-trip unchanged, and advanced-option certification, transfer,
generation and complete S21 profile activation remain pending.
Validation for the ancient/Harmony extension: 897 isolated unit/feature tests
with 18,616 assertions, four native S21 SQL Server tests with 30 assertions,
and Pint pass. A separate read-only probe through the updated inspector matched
the two new client examples; no game data was modified by the application.

The first S21 warehouse web preview is available at `/admin/s21-warehouse`,
behind administrator authentication, ManageSettings permission and the explicit
`GAME_S21_WAREHOUSE_PREVIEW_ENABLED` flag (off by default, enabled locally).
It uses the native read inspector and displays saved positions, exact source
rectangles, fallback artwork and verified diagnostic fields, with tooltips and
a selectable detail panel. Native serial bytes/hex and item write actions are
excluded. The main game/event fixture profile remains unchanged while the full
S21 bundle is incomplete. Local app/nginx/Redis services were started for the
preview; the scheduler was not started.

A tenth client-matched fixture confirms Flameberge socket bonus 0 as the visible
Attack +11 bonus; FF remains the no-bonus sentinel. Paired armor probes with
`extl=0/1` had identical bytes except serials, so 380 support remains unverified.
Validation for the web preview: 906 isolated unit/feature tests with 18,663
assertions, all three existing profile smoke checks, four native S21 SQL Server
tests with 31 assertions, Pint, Vite build and Blade compilation pass. Local
HTTP checks confirm the protected preview redirects to administrator login and
the fallback image/frontend manifest are served successfully.

An eleventh, separate client capture verifies Fire Seed Sphere [Lvl. 1] as
12:100 with packed item level 0, durability 1 and a 1×1 rectangle. Its source tier
is preserved in both native decoding and the administrator preview. This is a
standalone sphere fixture; filled equipment sockets and the seed-effect formula
remain unverified until the owner inserts it in the client. No live game writes
were performed by the application.
The focused codec/preview suites pass 18 tests with 227 assertions, and Pint
passes. No application logic changed for this capture.

A twelfth client-matched fixture now verifies a tier-1 Fire sphere inserted
into Flameberge: socket bytes `00 FE FE FF FF`, bonus FF. The zero-valued first
seed is distinct from empty FE and absent FF. Native metadata and the read-only
administrator preview now name this verified Fire damage/wizardry-per-20-levels
+1.1 effect in English and Bulgarian. Other filled seed codes remain explicitly
unverified. The separate live read/presentation check matched the same effect;
the application did not create or move any game items.
Validation for the filled Fire seed: 911 isolated unit/feature tests with 18,695
assertions and Pint pass. Coverage distinguishes filled code 0, empty FE, absent
FF and a still-unverified nonzero filled code without inventing an effect.

A thirteenth capture verifies tier-1 Lightning in Flameberge's second socket:
`00 1D FE FF FF`, with Fire preserved at zero and no bonus (FF). Raw seed 29
matches the client's Excellent damage increase +15 and the active native socket
option source. The English/Bulgarian administrator preview now names both
captured effects in order; unknown seed codes remain unverified. Native bytes
remain unchanged on re-encoding, and no game writes were enabled.
Validation for Lightning: 913 isolated unit/feature tests with 18,705 assertions
and Pint pass. A separate read-only live presentation check matched both socket
descriptions and their positions.

A fourteenth capture completes the tier-1 Fire/Lightning/Ice Flameberge example:
socket bytes `00 1D 10 FF FF`, bonus 00. Ice matches life increase on monster
destruction +250; the newly acquired bonus matches Attack +11. Native metadata
and the bilingual preview now name both effects. Bonus descriptions are limited
to their configured item-section range, with unknown codes retained as raw
diagnostics. Full probability rules and higher-tier seed encoding remain open.
Validation for Ice/bonus: 916 isolated unit/feature tests with 18,719 assertions
and Pint pass. A separate live read/presentation check matched the three seed
descriptions and Attack +11 without modifying game data.

A fifteenth capture verifies Fire tier 7 on Cyclone Sword. Raw socket byte 46
is accompanied by byte-8 header flag 0x40; treating the whole shared byte as
ancient state was incorrect for this socket item. The native record now retains
socket header context separately and excludes the verified 0x40 socket bit from
ancient options on source-declared socket gear. The preview names only the
matched first-position tier-7 tuple (+1.7), leaves unsupported contexts/tails
unverified and preserves every original byte.
Validation for the tier-7 correction: 920 isolated unit/feature tests with
18,743 assertions, five native S21 SQL Server cases with 38 assertions and Pint
pass. A separate live read/presentation check shows Fire level 7 +1.7 with ancient
option 0 and socket header context 64, preserving the original saved bytes.

The supplied `ucrtbased` S21 client contains 2,930 Item BMD models and their
textures rather than a complete ready-made item icon collection. A local pilot
decoded BMD v15 geometry, frame-zero bone transforms and OZJ/OZT textures and
rendered transparent PNGs for Flameberge (0:26, 1x4) and Dragon Knight Armor
(8:29, 2x3). The native catalog now supplies those two images to the existing
warehouse preview; other identities retain nullable artwork and the shared
fallback. Model/texture hashes and rendering limits are recorded in
`resources/game/mudevs_season_21/item-image-provenance.json`. These are static
base images; game lighting, +level glow and option-dependent colors are not
reproduced. Cyclone Sword's model mapping and bulk extraction remain open;
filenames cannot safely be inferred from item indices. The client files were
read only and no application dependencies were added. Validation: 22 isolated
catalog/warehouse tests with 14,208 assertions and Pint pass.
The owner confirmed that recognizable static icons are sufficient, matching
existing adapters. Exact model fidelity, animated effects and +level/option
colors are outside scope; no 3D rendering is needed in the website.

Read-only commissioning confirmed `MuOnline_S21` and `BattleCore_S21` at
`192.168.0.104:1433`. The existing bootstrap installers created
`MuOnline_S21_Web` and its dedicated `muonline_s21_web_app` login; all 85
application migrations are applied. The separate `muonline_s21_game_app` login
has SELECT access to 20 reviewed main-game tables and five BattleCore event
tables, with no sysadmin/db_owner membership or game mutation permissions.
`GAME_DB_BATTLE_CORE=BattleCore_S21` configures the optional `battle_core`
connection. Bootstrap is disabled and temporary administrator credentials are
removed from active `.env`; its previous values are preserved in ignored
`.env.backup`. Game writes remain closed. The active local environment now uses
`GAME_DATA_SOURCE=database`, `GAME_SERVER_PROFILE=mudevs_season_21` and
`GAME_EVENT_PROFILE=mudevs_season_21` for the reviewed read bundle. Do not use
S6 adapters with S21 data.

The S21 website read bundle is now active: native login, account overview,
Master/enhancement data, owned native balances, public rankings/profiles and
11 reviewed event recurrences. Code 18 is client-confirmed Blade Master and
appears with its own ranking filter; a manual authenticated request with all
six live classes now returns HTTP 200. The website keeps unfinished storage,
character mutations and economy routes unavailable before resolving missing
adapters, and hides their controls. Existing adapters retain their previous
behavior. Event overrides remain permission-checked, audited and scoped to
`mudevs_season_21` / `S21.1-2`; changing website timing does not change the game
server's configuration. Full item artwork extraction is deferred.
Validation: 931 isolated unit/feature tests (18,817 assertions), all four profile
smoke runs, five native S21 SQL Server cases (38 assertions), Pint and Vite build
pass. Separate manual read checks return HTTP 200 for the actual private account,
home and rankings; these checks do not mutate game records.

Eight inspected MuOnline procedures retain unbracketed `BattleCore.dbo` references, and the
BattleCore database contains only ten tables. Resolve the dependencies before
claiming BattleCore events work. No game data or procedure definitions were
changed. Class evolution codes and visible item boundaries remain pending.
Initial item presentation may omit images but must preserve catalog dimensions.

### Character economy milestone

The `feat/character-economy-improvements` branch completes the current
character-economy milestone. In addition to Item Generator markup and
reset/grand-reset rewards, it now provides:

- compact Character Services and Overview Quick Actions for paid Rename and
  Change Class operations, backed by shared teleported confirmation modals;
- version-driven class-family choices: four families for 97d+99i, five for
  1.0M and seven for MuEMU Season 6, preserving an evolution tier only when the
  target family supports it;
- administrator-enabled Buy Resets and Buy LevelUpPoints packages with exact
  Credits calculations and player confirmation;
- audited Paid Services settings and visible revision history;
- administrator-defined exact item exchange offers based on the active item
  catalog. An offer matches group/index, level, skill, luck, additional option,
  exact excellent mask and ancient option before removing a batch from an
  offline character inventory;
- account-level Online Time Rewards with configurable minutes per unit,
  Credits per unit and daily limit;
- explicit online-time claims, per-offer exchange limits and immutable wallet
  transactions;
- durable item-exchange state which survives a crash between game inventory
  extraction and the application Credits award;
- one-minute scheduler commands `credits:collect-online-time` and
  `credits:sync-item-exchanges` for bounded observation and recovery.

The item exchange path intentionally ignores durability and serial when
matching an offer, but it snapshots and verifies the complete original item
bytes at the write boundary. This allows an offer such as Leather Pants +7 +
Luck + Rate + Damage Decrease while preventing another option combination from
being exchanged accidentally. Ownership, offline state, exact bytes, daily
limits and idempotency are all checked server-side.

Online-time accounting reads current `MEMB_STAT` sessions through the active
version profile. The first observation establishes a checkpoint and never
backfills unknown past time. Later observations accept at most a bounded gap,
convert only complete configured units and preserve a small remainder. Stopping
the scheduler therefore pauses earning instead of creating an unverified time
windfall when it restarts.

The branch includes these final focused commits:

- `62dd337 refactor(account): move character services into modals`
- `6306858 fix(account): confirm character service purchases`
- `03e3b86 feat(account): add paid progression services`
- `6662f2c feat(admin): show paid service revisions`
- `5550bde feat(credits): add item and online time exchanges`
- `6904ecf test(credits): update profile schema expectations`
- `8221e53 fix(database): allow rename reference updates`

The disposable main suite passes with 742 tests and 3,795 assertions. The
97d+99i and 1.0M SQL Server integration suites pass. The least-privilege doctor
allows the narrow `Guild` and `GuildMember` updates required to keep renamed
character references consistent, while continuing to reject insert, delete,
control, alter and ownership permissions. Pint, Blade compilation and the Vite
production build pass, and the new application migrations have been applied
locally.

Before enabling either earning system, save the policy under **Admin → Economy
→ Paid services**. Item Exchange additionally needs at least one enabled exact
catalog offer. Online Time Rewards requires the scheduler to remain healthy.
Both systems are disabled by default after migration.

The untracked root files `script 1.0M.sql` and `script S6.sql` are external
reference material supplied by the project owner. Do not add, edit, move or
delete them unless explicitly requested.

### Broader priorities

1. Continue manual end-to-end testing against the dedicated 97d+99i, 1.0M and
   MuEMU Season 6 SQL Server databases and test game servers, one player
   operation at a time.
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
