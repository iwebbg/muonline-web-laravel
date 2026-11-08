# Feature catalogue

This document lists the functionality currently available in MuOnline Web. A
server owner can disable most player-facing modules independently from
**Administration → Modules**. Disabled modules disappear from navigation and
their player routes return `404`, while audit and recovery records are kept.

The current implementation supports the `darksteam_97d99i` and
`darksteam_1_0m` game and event profiles. Availability for another MuOnline
version depends on a dedicated, verified profile and adapter bundle. Additional
integrations are in preparation and can also be requested at
[office@interweb.bg](mailto:office@interweb.bg).

## Public website

- Configurable website identity, logo, titles, metadata, Open Graph image and
  footer ownership.
- English and Bulgarian interfaces with English fallback.
- Live server status, online population and a directory of related servers.
- Home-page event countdowns and a complete configurable event schedule.
- Rankings with search, pagination, sorting, class filters, PK ranking,
  character portraits and decoded guild marks.
- Public character and guild profiles.
- Bilingual news, guides, standalone pages and download listings.
- Public fixed-price Market and timed Auction listings.
- Cookie consent, privacy policy and terms-of-use pages.
- Branded application error pages using the configured website identity.

## Player account

### Account lifecycle and security

- Legacy-compatible MuOnline account authentication.
- Registration, email activation and password recovery as optional modules.
- Credential changes and an email-confirmed account deletion request.
- Optional Google reCAPTCHA v3 protection.
- Laravel sessions with secure production cookies and rate-limited sensitive
  endpoints.

### Characters and progression

- Account-owned character selector with persistent URL state.
- Character class, progression, stats, guild, PK, map and online information.
- Equipment, inventory bag and multiple warehouse vaults.
- Verified item images, dimensions, rarity and option tooltips.
- Reset and grand-reset previews and execution.
- Stat allocation and stat-reset previews and execution.
- PK clear and move-to-town previews and execution.
- Version-aware limits and admin-managed character rule profiles.

### Economy and storage

- Credits balance and immutable transaction history.
- Configurable VIP plans and Credits-funded purchases.
- Vote links, cooldowns, automatic or reviewed rewards and player history.
- Recoverable Zen Bank deposits and withdrawals.
- Physical-item Jewel Bank for jewels declared by the active version profile.
- Exact-item Web Vault deposits, withdrawals and recovery.
- Fixed-price Market sales and purchases.
- Timed Auctions with held bids, automatic settlement and seller payout.
- Safe Web Vault fallback when a purchased or returned item cannot enter the
  game warehouse.

### Web Shop and Rewards

- **Items Shop** for administrator-configured item offers purchased with
  Credits.
- **Item Generator** for searching the verified version catalog, selecting
  supported item options and purchasing the calculated result with Credits.
- **Mystery Boxes** with class categories, optional descriptions and hidden
  contents.
- Each Mystery Box explicitly tells the player whether opening it gives one
  weighted random item or every configured item.
- Purchased, event-issued and administrator-issued rewards remain in the
  account Rewards inventory until they are opened and delivered.
- Each released item has independent delivery and retry state, so one failed
  delivery does not discard the remaining rewards.
- Shop purchases keep immutable offer and item snapshots; later administrator
  edits do not change an already-owned reward.

## Administration

### Dashboard and access

- Separate administrator authentication, independent from game accounts.
- Overview analytics for accounts, characters, guilds and online players.
- Character distribution by class and map with version-aware map names.
- Service-health and active-administrator indicators.
- Game Master identities with granular settings, audit, moderation, content,
  economy and item-grant permissions.

### Game operations

- Individually editable Reset, Grand Reset, Stat Reset, PK Clear and Move to
  Town profiles.
- Immediate, audited configuration activation with before/after revision
  details.
- Event schedule management scoped to the active version and event profile.
- Searchable account and character moderation with protected Game Master
  control codes.
- Verified item grants with account and item autocomplete, version limits,
  skill, luck, additional and excellent options, delivery preview and reason.

### Economy

- Credits wallet adjustments with administrative reasons and ledger history.
- VIP plan management.
- Separate Vote Links and Vote History screens, filters and manual review.
- Market and Auction operation inspection, return and retry tools.
- Web Shop offer management for fixed items and Mystery Boxes.
- Mystery Box reward-pool configuration, weighted selection or all-item mode.
- Direct account reward grants from a configured shop offer.
- Economy recovery screens for interrupted storage and Zen operations.

### Website control

- Website branding, SEO defaults, page-title suffix, Open Graph image and
  footer copyright owner.
- Current and related game-server management.
- Per-version player module switches with immutable configuration revisions.
- Bilingual content management for news, guides and standalone pages.
- Download management.
- Searchable, filterable and paginated administrator audit log with date range.

## Safety and operations

- Separate application and game database connections.
- Safe defaults: `GAME_DB_READONLY=true` and `GAME_WRITES_ENABLED=false`.
- Documented operational transition to `GAME_DB_READONLY=false` and
  `GAME_WRITES_ENABLED=true` after the read-only commissioning checks pass.
- Transactional and idempotent game mutations with ownership and offline-state
  checks repeated at the write boundary.
- Exact preservation of legacy item bytes while an item is in escrow or web
  custody.
- Read-only `game:doctor` checks for schema compatibility and unsafe SQL Server
  privileges.
- Application backup command and documented restore rehearsal.
- Queue and scheduler containers for retries, expiry and settlement work.
- Liveness and dependency-aware readiness endpoints.
- Production Nginx, HTTPS redirection and automatic Let's Encrypt renewal.

## Credits payments

- Administrator-configured fixed Credits packages with immutable purchase
  snapshots.
- Hosted Stripe Checkout, PayPal Orders v2 and Revolut Pay checkout flows.
- Sandbox and live provider modes configured only through environment secrets.
- Signed webhooks, provider-side amount/currency reconciliation and idempotent
  wallet settlement.
- Account and administrator payment history. Refund events are recorded and
  flagged without silently creating a negative Credits balance.

Additional MuOnline versions require dedicated compatibility work before they
can be declared supported.

For implementation details and remaining work, see the
[current state](current-state.md) and [roadmap](roadmap.md).
