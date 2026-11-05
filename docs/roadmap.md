# Delivery roadmap

This roadmap assumes one primary developer working with AI assistance and
access to a cloned database plus a dedicated test game server. Production is
not used for exploratory writes.

## Status snapshot

| Phase | Status | Current interpretation |
| --- | --- | --- |
| 0 — Foundation | Complete | Laravel 13/PHP 8.5 containers, SQL Server drivers, health checks and safety boundaries are established. |
| 1 — Design system | Operational, polish ongoing | Public, account and admin shells plus reusable dialogs, forms, cards, tables and item grids are implemented with Tailwind CSS v4. |
| 2 — 97d+99i read model | Implemented | Real accounts, characters, guilds, rankings, server status, inventory and warehouse reads use the version adapter. |
| 3 — Authentication | Implemented, manual mail testing ongoing | Player/admin authentication, registration, activation, recovery, security and Game Master permissions are present. |
| 4 — Character operations | Implemented, real-server validation ongoing | Preview and execute paths exist for resets, stats, PK clear and movement with admin-managed rules. |
| 5 — Inventory and warehouse | Implemented, UI polish ongoing | Item codec, item catalog, equipment, bags, multi-vault grids, tooltips and guarded exact-item mutations are present. |
| 6 — Content/economy/community | Broad functional baseline | Content, Credits, VIP, Votes, Zen/Jewel banks, Web Vault, Market, Auction, Items Shop, Item Generator, Mystery Boxes and Rewards exist; payment-provider integrations remain. |
| 7 — Production readiness | In progress | Docker/Nginx/TLS, readiness, scheduler, backup and least-privilege tooling exist; final rehearsal and security review remain. |

For implementation details and mandatory continuation patterns, read
`docs/current-state.md`.

## Phase 0 — Foundation and reference baseline

- Record approved references and security boundaries.
- Create Laravel 13 / PHP 8.5 application skeleton.
- Add local and production container structure.
- Install Microsoft ODBC 18 and `pdo_sqlsrv`/`sqlsrv` 5.13+.
- Establish code quality, tests and dependency auditing.
- Define game-owned versus application-owned database objects.

Exit condition: the application boots in its PHP 8.5 container and a health
endpoint passes without requiring a game database connection.

## Phase 1 — Design system and interactive shell

- Establish visual tokens and responsive application shell.
- Build public, account and admin navigation patterns.
- Create reusable cards, dialogs, forms, data tables, feedback and skeleton
  loading states.
- Prototype the character profile and inventory interaction.
- Add accessible motion and reduced-motion behaviour.

Exit condition: representative public, account and admin screens work with
fixture data on mobile and desktop.

## Phase 2 — 97d+99i read model

- Add separate `game` and `web` database connections.
- Implement version adapter and capability contracts.
- Map accounts, characters, guilds, online state and rankings.
- Create sanitized fixtures and integration tests.
- Connect staging with read-only credentials.

Exit condition: real test-server data can be viewed without any database write
permission.

## Phase 3 — Authentication and account experience

- Implement legacy-compatible game account authentication.
- Add modern session security and rate limiting.
- Create separate secure administrator identities and permissions.
- Deliver account dashboard, character switcher and profile visualisation.

Exit condition: players only see their own private data and administrators use
separate, strongly hashed credentials.

## Phase 4 — Settings and character operations

- Build typed settings and setting-change audit history.
- Implement reset and grand-reset preview calculators.
- Add transactional reset/grand reset operations.
- Add stats, reset stats, PK clear and move/unstuck.
- Add operation idempotency, offline enforcement and before/after logs.

Exit condition: the complete operation suite passes automated tests and manual
tests against dedicated characters on the test game server.

## Phase 5 — Inventory and warehouse

- Implement tested 97d+99i item codec and round-trip fixtures.
- Deliver interactive equipment, inventory and warehouse visualisation.
- Add multi-vault support where present.
- Introduce guarded item mutations only after binary safety tests pass.

Exit condition: decoding and re-encoding representative inventory blobs is
byte-for-byte stable when no change is requested.

## Phase 6 — Content, economy and community modules

- News, downloads, guides and events.
- Wallet ledger, credits, Zen bank, Jewel bank and VIP.
- Vote rewards and web storage.
- Market, followed by auction as a separate high-risk milestone.
- Items Shop, version-aware Item Generator, Mystery Boxes and durable Rewards
  inventory.
- Add a separate Game Master role with granular permissions instead of sharing
  unrestricted administrator access.
- Add audited moderation tools for account and character bans, blocks and
  sanctions.
- Add permission-gated item grants only after the version item codec and
  inventory round-trip safety tests from Phase 5 are complete.

Exit condition: every balance/item transfer is atomic, traceable and protected
against retries and concurrent purchase attempts.

## Phase 7 — Production readiness

- Security review and least-privilege SQL account.
- Backup and restore rehearsal.
- Performance, concurrency and failure testing.
- Nginx, TLS, queues, scheduler, monitoring and deployment runbook.
- Staged read-only production connection, followed by explicitly enabled write
  capabilities.

Exit condition: deployment and rollback have been rehearsed and the production
database is never accessed using `sa` or `db_owner` credentials.

## Immediate continuation backlog

1. Manually verify every character and economy mutation against dedicated test
   accounts on the live 97d+99i test server.
2. Close remaining responsive and interaction defects without changing the
   established Blade/Alpine/Tailwind CSS v4 stack.
3. Add automated vote-provider callbacks.
4. Implement payment-provider-backed Credits purchases for PayPal, Revolut and
   Stripe with signed webhooks, idempotency, refunds and immutable audit records.
5. Design the remaining optional Web Event capabilities around the established
   version and module boundaries.
6. Stress concurrent bids, shop purchases, reward openings, storage moves and
   scheduler retries.
7. Complete backup/restore, rollback and least-privilege production rehearsals.
