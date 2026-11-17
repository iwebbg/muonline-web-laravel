# MUDevs Season 21 compatibility and implementation plan

Status: native website bundle selectable, with owner-confirmed character,
storage, shop and bank operations as of 17 November 2026. See the
[current acceptance checkpoint](current-state.md#current-acceptance-checkpoint--17-november-2026)
for completed flows; historical milestones below retain their original evidence.
Specialist events and unverified item families remain gated. The target is the owner-supplied MUDevs S21.1-2 distribution,
including its standard inherited game systems, all playable class families and
their advancements. A read-only panel is an intermediate milestone, not the
definition of complete support.

The implementation must preserve the three existing adapters and use the
current Laravel, Blade, Alpine and Tailwind architecture. Initial item images
may be absent; verified dimensions and readable presentation are required.

## Current staged website surface

The native bundle exposes registration/recovery/security, inventory/vault reads,
all six core character preview/action endpoints, Zen/Jewel Bank, Web Vault,
Credits/Votes, Market/Auction, Shop, Mystery Boxes, Rewards, journaled rename/class
changes, native account VIP purchases and Credits-to-game-currency exchange. All 76 class forms
are represented. The owner has enabled local game writes for supervised client
acceptance checks; this is not a production commissioning approval. The runtime game role now has the narrow
permissions required by the reviewed account/core/item adapters. The historical economy checkpoint passed
1133 isolated tests / 47250 assertions and all four profile smoke runs. Native SQL
Server coverage passes 30 tests / 198 assertions, including native account purchases,
identity services, restricted column grants and application-schema migrations. The
legacy 1.0M SQL integration suite passes 10 tests / 51 assertions. Subsequent
focused checks also cover unknown restored-offline activity and the updated doctor.

Adapter availability is an explicit module/route allow-list combined with
persisted operator switches. Administration includes native item grants, reviewed
economy pages, events, character rules, moderation, module management, Game Masters
and content. Journaled paid reset/point purchases now have native S21 routes.
The old per-character VIP endpoint remains unavailable on S21; account VIP has
its own native purchase flow. Rename and class changes now use native receipts
and reviewed identity references rather than the legacy repository.

The native default and administrator stat limit are 65000. The application-owned
reset_profiles.maximum_stat column was originally SQL Server SMALLINT; a new
forward migration widens it to INT while retaining existing values and the
legacy database default. Its rollback refuses values above 32767. The migration
was tested on disposable SQL Server and applied to MuOnline_S21_Web. Old adapters
retain their existing 32767 profile limit. Only KeepAll inventory policies are
selectable for S21 until dependent-item deletion semantics are implemented.

Enabling the activation route exposed a missing global write guard in the shared
activation action. Activation now leaves both account and token untouched if
writes are disabled or the game connection is read-only; existing enabled-write
activation behavior remains covered by regression tests.

The optional Battle Core account panel now uses the separate read-only
connection. `GAME_BATTLE_CORE_READS_ENABLED` enables recorded registrations and
unclaimed reward quantities; `GAME_BATTLE_CORE_SERVER_CODES=19,20` matches this
pack's CS/main server codes. Every query requires both account ownership and
that realm allow-list, preventing collisions with equal account names from other
realms. Unknown registration states remain unknown; dates retain month/day because
the native rows do not store a year. Missing optional sources degrade to an
unavailable panel. Legacy profiles have no native reader binding.

The source `WZ_BattleCore_CancelToJoin` establishes registered state 1 and cancelled
state 0; reward procedures select unclaimed TookState 0. The website executes only
SELECTs and does not invoke those procedures. Live read-only commissioning succeeds
with zero current registrations/rewards. SQLite privacy tests and native SQL Server
tests cover foreign realms, foreign accounts, claimed rewards, unknown states,
missing sources and escaped stored names.

Runtime SQL bootstrap has now applied the reviewed native write grants: MEMB_INFO
SELECT/INSERT/UPDATE; Character and warehouse/ExtWarehouse SELECT/UPDATE; custody
receipts SELECT/INSERT; native serial procedure EXECUTE/VIEW DEFINITION. All other
reviewed game tables retain SELECT only, including native coins and special-item
state. Receipts cannot be updated or deleted by the application role. BattleCore
remains SELECT-only. The separate commissioning-only profile is unchanged.

A fresh disposable database built from the exact required native column types
and a dedicated non-administrator login verifies actual registration/activation,
serial allocation and journaled item deposit/extraction through this role. The
installer is idempotent; the role is neither sysadmin nor db_owner. SQL integration
coverage is now 24 tests / 137 assertions. Live bootstrap changed permissions only;
no game account, serial counter or item row was mutated during commissioning.

### Unknown item display and custody boundary

Native read-only storage now keeps unknown identity headers as occupied,
non-transferable placeholders instead of failing the whole account storage
request or accidentally enriching null identities as Kris. Read presentation
retains all 25 original bytes internally, omits serial/raw bytes from account
JSON and uses the existing missing-image fallback. Known special items retain
catalog names and dimensions while exposing their unavailable custody state.

The shared native codec still rejects unknown identities in mutation paths;
warehouse/inventory placement cannot treat their unknown rectangles as free
space. Transfer rules depend directly on the native decoder, keeping display
eligibility independent of the shared codec. Native item action buttons and
Credits exchange matching respect this eligibility. Existing adapter payloads
omit the optional native-only fields and retain their prior behavior.

Tests cover mixed recognized/unknown warehouse responses, byte retention,
strict decoding, rejection without a custody receipt and special equipment
presentation. Full-suite/profile and native SQL checks pass. Real special-item
fixtures remain required for extended identities and dependent-table custody;
this change does not certify Pentagram/Errtel, Muun, Artifact or wing-core
transfers. All 16 character families now use the owner-generated 1024×1536 portraits in
`public/images/characters/muonline-web-*.png`, shared by every adapter and
advancement. The previous portraits and source-specific crop offsets have been
removed. Static item artwork is available through the native-table export
documented below.

### Owner-confirmed Web Vault round trip

On 15 November 2026 the owner transferred Excellent Kris +9 from native vault 0,
slot 109 into Web Vault, then withdrew it and confirmed its unchanged tooltip in
the running client. The original serial is 0000000A and the SHA-256 fingerprint
is 522ce95b2f90de9007fd724e4ca2e4769e8bd48598dffd80e24c503ea27d8cbf.

Read-only checks confirmed one available web record after extraction, an empty
source slot and no matching item remaining in the visible game vaults. Withdrawal
completed with one exact copy at vault 0, slot 4, status Withdrawn and a released
escrow key. Both directions have one native receipt, with no synchronization
error. All original 25 bytes matched; +9, Luck, Additional Damage +28, all six
excellent options and durability 255 were retained. The owner then logged in and
confirmed the same item in the game client. This certifies this ordinary excellent
item round trip; special-dependency and full-storage cases remain separate.
The owner also completed a second round trip with Flameberge, native serial
00000021, initially and finally in vault 0, slot 2. All original 25 bytes matched
and exactly one game copy remained. Filled socket bytes [0,29,16,255,255] and
bonus byte 0 were retained. The final client screenshot confirms Level 1 Fire
+1.1, Lightning excellent damage +15, Ice life-on-kill +250 and Bonus Attack +11.
One extraction and one deposit receipt exist, the web record is Withdrawn and
its escrow reservation is released. This confirms this three-seed/bonus fixture;
other seed levels, special dependencies and full-storage recovery remain separate.

The local baselines are stored privately under storage/app/private/s21-live-checks.

### Lucky Coin commissioning discrepancy

The owner successfully purchased and received a Cash Shop item using exchanged
WCoin. Lucky Coin spending has not passed acceptance. The database contains the
exchanged registered balance of 1000, while iwebbg has no physical 14:100 coins.
Delgado opens the client dialog "20th Anniversary Lucky Coin Event" and rejects
its 10-coin exchange with "You are lack of Exchange Items."

Both the supplied server Item.xml and decoded client Item.bmd contain Lucky Coin
14:100 (client model coin7.bmd). Its presence does not establish active creation
or spending support. The documented full /make 14 100 command produces no item
and no response. The same full command syntax successfully creates ordinary
Kris 0:0 after closing NPC interfaces, isolating the discrepancy to the coin or
its runtime handling. The source Lucky Coin monster drop has DropRate=0. No
explicit make-specific block was found in the supplied ItemDropLimit rules.

Physical-item consumption by this Anniversary dialog remains a hypothesis;
neither switching web delivery to physical coins nor claiming the registered
balance is spendable is justified yet. The next evidence required is the running
GameServer/DataServer log or a confirmed runtime creation path for 14:100. No
refund, conversion or modification of the exchanged 1000 has been performed.

### Native identity, account VIP and currency exchange

The private `account/game-services` page offers one-way Credits exchange for
WCoin(C), WCoin(P), Goblin Points, Lucky Coins and character-owned Ruud. Each
currency has a profile-scoped integer ratio, minimum/maximum pack count, enable
switch and immutable administrator revision. Initial ratios are **1 Credit ->
1 coin** for all five currencies; they are deployment defaults, not a claimed
in-game conversion rule. Operators edit them under Economy -> Game coin exchange
rates. For example, 3 Credits -> 10 WCoin(C) per unit means 5 units cost 15
Credits and grant 50 coins. Website Credits remain usable by existing web modules;
exchange is irreversible and adds to the game's actual native balance.

Ruud grants capture CharacterIndex and recheck account ownership inside the
native transaction. Account coins use CashShopData/LuckyCoin, initializing a
missing native row atomically. Signed INT overflow is rejected. Connected,
unknown-session and restored-offline activity block grants. No native coin
purchase was executed against the running server during commissioning.

Native VIP plans have an explicit AccountLevel 1–3, duration and Credits price.
Legacy plans retain null native metadata and their existing character behavior.
Native and legacy plan catalogs are filtered separately; administrators cannot
edit or delete a plan across that boundary, and the legacy purchase action
rejects native plans.
A matching active tier extends its paid expiry; an expired membership starts
from the database clock. Another active tier is rejected rather than discarding
paid time. Dates retain native server wall time and smalldatetime bounds. No
commercial VIP tier/price was invented or enabled locally: an economy manager
must configure the desired account plans in VIP plan administration.

`IWEB_S21_AccountPurchase` commits a native result or definitive rejection with
its immutable key/account/payload hash. The application debit, captured offer
and owned recovery history survive response loss, changed rates and disabled or
archived offers. Uncertain failures remain pending without a speculative refund;
receipt-backed rejection refunds once. Native receipts are SELECT/INSERT only.
The private application migration and native bootstrap have been applied locally;
`GAME_DB_READONLY=true`, `GAME_WRITES_ENABLED=false` and bootstrap=false remain.

Paid rename updates all ten AccountCharacter name/index slots, GameIDC, indexed
name caches and secondary social/party/guild references in one transaction. The
reviewed graph contains 80 columns across 77 native tables, of which
DeathExpRecovery is optional. The supplied SQL's final batch defines that table,
but the running database omits it. Bootstrap skips its grants only when absent;
rename preserves the same behavior and updates its name cache when present.
Historical item/death/hunting/mail gift and inter-realm competition records keep
their original identity. Battle Core main-database rows retain realm filters;
the separate BattleCore database remains read-only. SQL Server application locks
serialize case-insensitive name reservations, independently of native indexes.

Class changes select a supported target stage no higher than the current stage,
including fifth advancement. Equipment slots 0–11 and 236–238 must be empty;
allocated Master, Enhancement and Ability core data must be reset in-game first.
Ordinary skills are cleared with a binary-safe native update; level, stats,
points, quests and advanced counters are retained. Party class caches and skin
presentation are updated. Both identity actions require available Battle Core
reads, no active/unknown registration and no unclaimed rewards. The dashboard
can replay pending rename/class purchases using the original name and key.

Identity grants update only reviewed name/presentation columns, keeping special
item payloads protected. `game:doctor` understands column grants and optional
objects; the running database passes read-only schema/role/permission diagnostics.
Disposable SQL tests exercise all five grants, VIP extension/replay, name locks,
class writes, immutable receipts, optional-table absence and lossless migrations.
The account dashboard and native-service page return HTTP 200 against local data.
In-game balance/identity/skill behavior remains the final owner-led acceptance test.

### Native account membership and Gens verification

The private dashboard now displays account-wide native membership from
MEMB_INFO.AccountLevel/AccountExpireDate. This is independent of character VIP
purchases. The subsequent native account-purchase stage below enables a separate
website VIP module without creating character entitlements. Numeric VIP tiers
retain their native value rather than inventing Silver/Gold/Platinum labels.
Standard, active, expired and incomplete states are represented; the expiry
label explicitly retains game-server time without assuming the Windows SQL
Server time zone.

The source WZ_GetAccountLevel resets AccountLevel when GETDATE() is strictly
later than AccountExpireDate. The website never executes that mutating procedure;
a SELECT CASE uses SQL Server CURRENT_TIMESTAMP (the same database clock) to
project effective membership while preserving stored account data. Only the
signed-in account's row is queried. Null dates and negative tiers remain unknown.
Live read-only commissioning returns HTTP 200 and displays the account's native
standard tier correctly. Tests verify ownership, literal SQL-shaped identifiers,
expiry, read-only queries, unchanged stored values and the legacy dashboard.

A source review of Gens_Duprian/Gens_Varnert exposed an incorrect inherited rank
assumption. This distribution uses ROW_NUMBER ordered by Contribution DESC,
Name ASC within each family, including contributors below 10,000; it does not
use a dense rank or that threshold. The S21 adapter now follows those native
views while joining ownership by CharacterIndex and displaying current character
names. Tests cover tied contributions, cached-name collisions, low contributions,
separate/invalid families and search retaining global rank. Native SQL Server
coverage verifies both corrected readers. S6 behavior remains unchanged.

The complete isolated suite passes 1,076 tests / 19,931 assertions and all four
profile smoke runs; native SQL integration passes 24 tests / 137 assertions.
Pint and the frontend build pass. At that checkpoint paid native services remained
gated; the subsequent journaled progression stage below adds reset/point purchases.
The legacy rename path still references absent MarryName and omits S21 index
caches. No paid operation or live game mutation was performed during commissioning.

### Journaled paid progression without a game-client dependency

Native reset and LevelUpPoints purchases now use a separate S21 implementation,
not the shared legacy mutation repository. Public package validation, operator
Credits prices and immutable application settings snapshots remain in the existing
payment action. The S21 boundary requires a stable operation identity; direct
unjournaled purchase methods reject calls. Rename and class change now share the
native journal through the identity stage described below.

IWEB_S21_CharacterOperation stores the account, payload hash, before/after result
or definitive rejection, and timestamp. The account status lock serializes native
sessions. A successful counter change and receipt commit atomically. A rejected
callback rolls back to a savepoint before its rejection receipt commits; late
concurrent retries therefore cannot grant a purchase that has already been
refunded. Reused keys with changed account, character, kind, quantity or reset
policy are rejected. Matching committed results are returned before checking the
current online state, so acknowledgement never mutates the now-online character.

The shared action opts into this protocol only for the new journaled contract.
Existing adapters retain their original method calls and refund behavior. Native
unexpected persistence failures remain Pending, with no speculative refund;
replaying the original operation acknowledges a committed result or applies an
uncommitted purchase exactly once. The original captured point amount, price and
reset policy survive later settings edits. Native completed audit snapshots retain
Command and every class-supported stat. Operations from another profile are not
replayed or refunded through the S21 boundary.

The private dashboard lists owned pending native purchases with their original
identity and package. A new purchase for that character is refused while its
existing operation awaits confirmation. Replay uses the stored debit idempotency
key; a definitive rejection refunds once. Account/session, public package, offline,
class, signed-INT bounds and KeepAll inventory requirements are checked. Purchased
resets modify only the reviewed level/experience/reset/point/stat fields and retain
inventory, quests, skills, Master/enhancement state, Ruud and Zen.

Tests simulate an exception after the actual native commit, receipt INSERT failure,
immutable rejection replay after online state changes, changed pricing, ownership,
profile mismatch and private pending-purchase rendering. Disposable SQL Server
checks verify two independent sessions grant one point package, rejection savepoint
rollback, schema-extension idempotence, missing receipt permission rollback and
successful reset/point purchases under the restricted non-administrator role.

Validation: 1,092 isolated tests / 20,025 assertions, all four profile smoke runs,
and 29 native SQL tests / 168 assertions pass. Pint and the frontend build pass.
The authorized live bootstrap added only the receipt table and SELECT/INSERT
grants. Runtime UPDATE/DELETE on receipts are absent; the live table has zero
paid operations and the private dashboard returns HTTP 200. Global game writes
remain disabled. No real character or wallet purchase was executed.

## Sources and evidence

The local reference root is
`/home/zdev/Downloads/MUDevs Emulator FULL_BASE_S21.1-2_13.03.2026`.
All pack paths below are relative to that root. These external files remain
untouched and must not be bundled with the application. Use sanitized schema
and minimal representative fixtures for tests, with no accounts or credentials.

The owner reports a working Windows server and client login. Read-only SQL
commissioning confirmed `MuOnline_S21` and `BattleCore_S21` at
`192.168.0.104:1433`; the application database will be `MuOnline_S21_Web`.
The inspected Character schema matches the supplied 5,975-byte inventory
capacity, and the existing character has a stored inventory of that length.
This confirms the blob length, not the record layout or visible cell count.

## Initial implementation

Branch `feat/mudevs-season-21` starts from `origin/develop` at `955c754`.
The first components cover:

- `ClassFamilyMuDevsSeason21`: 16 base identifiers, source keys, base stats
  and normal points per level. Persisted advancement codes remain separate.
- `MapNameMapperMuDevsSeason21`: location labels for all 121 configured maps,
  including safe labels for configured custom locations without adding custom
  events or allowing movement there.
- `GameCurrencyBalances` and `SqlServerMuDevsSeason21CurrencyBalanceRepository`:
  read-only account coins and character-owned Ruud, separate from web Credits.
- Portable tests for class stats, map boundaries, currency ownership, absent
  rows, malformed balances, SQL-shaped account identifiers and absence of writes.

These initial components did not register a game profile. The later activation
stage below now exposes reviewed reads and native balances. Market and Web Shop
remain later compatibility tests.

The first foundation commit is `6dcd812`. The subsequent read-model stage adds:

- `MuDevsSeason21Profile`, containing the 16 SQL-seeded base class codes,
  source stats, portraits and read bindings. It initially remained outside the
  registry; unavailable advancement codes are not guessed.
- Private account character reads plus public rankings, profiles, guilds and
  realm analytics through S21-owned adapters.
- Master and enhancement reads joined by stable `CharacterIndex`, including
  tests where cached names point to another character. Native counters remain
  separate; no assumed base/Master/enhancement total-level calculation is used.
- Nine owner-provided portraits stored unchanged in the version asset folder,
  with class-defined focal points passed to rankings and account/public views.
- Optional Enhancement Points presentation in both languages; legacy DTO
  serialization omits new fields when their default/absent values apply.
- A separate disposable SQL Server read integration suite through
  `./docker/compose test-integration-s21` and the sanitized read-schema fixture.

The owner currently has only the existing base Dark Knight and no editor.
Fourth/fifth advancement codes therefore remain a client commissioning task.
The source seed and last runtime read both identify that character with code
16. Do not change it through SQL to manufacture advancement fixtures.

Read-only probes using the restricted login successfully exercised private
characters, public rankings/analytics and Master reads on MuOnline_S21.
Automated tests never connected to that server. Game coins remain separate
from web Credits; neither exchange nor a native shop-currency policy has been
selected or implemented.

Read-model validation: the full disposable suite passes with 872 tests and
4,269 assertions. All three existing profile smoke runs pass. Native SQL Server
checks pass for S21 reads (3 tests, 17 assertions), 97d+99i (3 tests,
40 assertions) and 1.0M (10 tests, 51 assertions). Pint and Blade compilation
pass. Real-browser portrait framing remains a manual UI review.

## Bootstrap and BattleCore commissioning

The existing application/game database installers provisioned
`MuOnline_S21_Web` with `muonline_s21_web_app`, and the separate
`muonline_s21_game_app` identity for game reads. All 85 application migrations
are applied to the new web database through its runtime login. No game schema
or procedure definitions were altered.

`ReadOnlyDatabaseProfileMuDevsSeason21` grants SELECT on 20 inspected main-game
tables; `BattleCoreDatabaseProfileMuDevsSeason21` grants SELECT on five event
sources. These commissioning-only `GameDatabaseProfile` implementations were
selected explicitly within the bootstrap process, without registering a
partial `GameVersionProfile` or borrowing S6 permission mappings. Runtime
verification confirmed both game database connections, no sysadmin/db_owner
membership, no elevated database permissions, SELECT enabled and UPDATE absent
on Character/BattleCoreReward. Currency reads work with the restricted login.

`config/database.php` now provides an optional read-only `battle_core`
connection using the game runtime identity and `GAME_DB_BATTLE_CORE`.
The active local target is `BattleCore_S21`. Tests replace that target with
isolated memory or clear it in the SQL integration bootstrap.

Active `.env` contains the S21 database targets and independently generated
runtime passwords. `APP_DB_BOOTSTRAP=false`; temporary administrator fields are
blank and game writes remain closed. The active local selection is now
`GAME_DATA_SOURCE=database`, with both game and event profiles set to
`mudevs_season_21`. The S6 selector must not be used with S21 data. The previous local
configuration is preserved in ignored `.env.backup` with restricted file access.

Do not recreate `MuOnline_S21` or
`BattleCore_S21`, import their create scripts into a running server, reuse the
S6 runtime identities, or provision S6-specific permissions against S21.
Game writes must remain closed during commissioning.

The live `BattleCore_S21` schema currently contains `BattleCoreInfo`,
`BattleCoreReward`, `BattleCoreUser`, `CashShopPeriodicItem`,
`DevilSquareFinal_Ranking`, `DevilSquareFinal_Scores`, `GameServerInfo`,
`ItemLog`, `LuckyItem` and `T_PetItem_Info`, matching the separate supplied
script. Its data represents event registration, participants, results and
rewards rather than website content.

Eight inspected live MuOnline procedures retain `BattleCore.dbo` references:
`WZ_BattleCore_AccountUserAdd`, `WZ_BattleCore_AccountUserDataCopy`,
`WZ_BattleCore_AccountUserInfoSelect`,
`WZ_BattleCore_All_GetAndUpdate_RewardInfo`, `WZ_BattleCore_CancelToJoin`,
`WZ_BattleCore_DeleteCharacter`, `WZ_BattleCore_GetRealName` and
`WZ_BattleCore_Set_RewardInfo`. Check bracketed references as well before
preparing a complete repair. The supplied copy/delete procedures also expect
BattleCore Character, AccountCharacter and progression tables absent from this
ten-table schema. Confirm the intended event-server setup before proposing
database changes; changing the web connection alone cannot repair either issue.

Website BattleCore reads need a separate optional connection targeting
`BattleCore_S21`, with narrow permissions on verified ranking/registration
sources. Game-owned copy, registration and reward procedures remain owned by
the game server. The website must not assume it should execute them.

Evidence levels used below:

- **File confirmed**: the supplied configuration or SQL explicitly contains it.
- **Official reference**: Webzen documents the standard game system; this does
  not prove matching MUDevs binary layouts, limits or persistence.
- **Runtime pending**: needs live schema inspection or a controlled client test.

Official references checked alongside the supplied files:

- [Crusader](https://muonline.webzen.com/en/gameinfo/guide/detail/419) documents
  five advancement stages, ending in Temple Guard Commander.
- [Ability Upgrade Cores](https://muonline.webzen.com/en/gameinfo/guide/detail/342)
  distinguishes the third-stage Master tree, fourth-stage enhancement tree and
  fifth-stage ability core.
- [Season 21 update 2](https://muonline.webzen.com/en/events/season21part1-2)
  includes Sanctuary of Aquilas and second Legendary equipment. Its global
  release limits must not override MUDevs configuration.
- [Group Boss Battle](https://muonline.webzen.com/en/gameinfo/guide/detail/305),
  [Protector of Archeron](https://muonline.webzen.com/en/gameinfo/guide/detail/143)
  and [Tormented Square](https://muonline.webzen.com/en/gameinfo/guide/detail/148)
  establish these as standard systems rather than pack-only custom events.

## Class families and advancements

`Data/Character/DefaultClassInfo.txt` and `Data/Scripts/Define.lua` agree on
16 base family identifiers. These are internal base identifiers, not verified
`Character.Class` values for every advancement.

| Base identifier | Pack key | Name in supplied base-stat file |
| --- | --- | --- |
| 0 | DW | Dark Wizard |
| 1 | DK | Dark Knight |
| 2 | FE | Faire Elf |
| 3 | MG | Magic Gladiator |
| 4 | DL | Dark Lord |
| 5 | SU | Summoner |
| 6 | RF | Rage Fighter |
| 7 | GL | Grow Lancer |
| 8 | RW | Rune Wizard |
| 9 | SL | Slayer |
| 10 | GC | Gun Crusher |
| 11 | KM | Kundum Mephis |
| 12 | LM | Lemuria |
| 13 | IK | Illusion Knight |
| 14 | AL | Alchemist |
| 15 | CS | Crusader |

Preserve source aliases in adapter metadata and choose reviewed display names;
pack spelling and Webzen global naming differ. Do not infer class evolution
codes by multiplying the base identifier or borrowing S6 masks.

`GameServer/DATA/GameServerInfo - Common.dat` has disable switches set to zero
for all 16 families and per-family creation/level-up settings.
`Data/Quest/Quest.xml` specifies fourth advancement quests at level 800 and
fifth advancement quests at level 1200. `QuestInstanceManager.xml` names fifth
advancement Nuke, Bolt, AoE and Giant Mount quests. `QuestReward.xml` also
contains corresponding fifth-stage rewards. `Quest4th.xml` separately permits
qualification-zone entry at level 750; zone entry is not quest eligibility.

Required implementation and verification:

1. Enumerate every supported stage per family, its persisted class value,
   display name and predecessor. Families need not have identical stage chains.
2. Compare one client-created representative of each family and each available
   advancement with `Character.Class`, quest state and related records.
3. Populate profile-driven class cards, public/private profiles, ranking
   filters, shop class scopes, base stats and stat availability.
4. Preserve advancement during class changes only where a verified target
   stage and compatible associated state exist. Include fifth-stage ability
   cards, quests and class-bound equipment in that decision.
5. Cover all families and transitions in tests; no selectable class may resolve
   to an unknown-class label. Use neutral artwork where suitable art is absent.

`CharacterClassDefinition` already represents a predecessor and numeric tier.
Extend its consumers only where necessary; do not impose an artificial
three-stage maximum on the new profile.

## Levels and progression

### Complete advancement catalog

The read profile now exposes 76 valid forms across all sixteen families,
including every third/fourth/fifth form. Four families (MG, DL, RF, GL) skip
second advancement; those missing codes remain invalid. Advancement display
names and text indices come from the supplied client's
`Data/Lang.mpr` -> `Eng/Text(Kor).txt`, not an unrelated emulator's labels.
The archive SHA-256 is
`f9243111a740b69b9b93522c2a10e408747b934c88eb15f73cfd18970331dd2a`;
the selected text SHA-256 is
`8247a5e476ee63378c289c10cc0794b290244668cbcf0ee4dd9b5de14036037f`.
Only the sixty advancement records and source text indices are bundled in
`class-advancements.json`; no archive password or whole client text is shipped.

Database identities use the supplied sixteen-family stride and contiguous
promotion offsets (`base + tier - 1`), consistent with MUDevs' separate family
and ChangeUp values, the existing MuEMU family encoding and the owner's runtime
Blade Master (18). Fourth/fifth names are cross-checked against official guides
and the pack's quest requirements. This is a source-backed mapping; a live
character of each advanced form has not been manufactured or independently
client-tested. Predecessors skip missing stages and all forms reuse their
family's existing portrait. Validation: 932 isolated tests / 19,346 assertions
and all four profile smoke runs pass.

Native account registration/activation, credential/recovery, reset/grand reset,
stat allocation/reset, PK clear, move-to-town, moderation, Zen/Lucky Coin and
Gens repositories have now been added separately from S6 implementations. Their
bindings do not open mutation routes or SQL grants. Character-operation tests
cover ownership, online refusal and retention of master/enhance, quest, mount,
skill and inventory data. Native grand reset includes Dark Lord Command in its
before/after stat result. Safe-town preview labels use a version-owned map lookup;
legacy profiles retain their original mapping. S21 Gens joins stable
CharacterIndex rather than potentially stale cached names.

Castle Siege now reads native MuCastle_DATA cycle state using the pack's seven-day
cycle and day-6 20:00–22:00 battle phase. Bootstrap was rerun with temporary local
admin configuration to add SELECT on that table; runtime writes and persistent
bootstrap credentials remain disabled. The real local home responds HTTP 200.

| System | File or schema evidence | Website work and runtime acceptance |
| --- | --- | --- |
| Base progression | `Character.cLevel`, `LevelUpPoint`, `Experience`; per-family settings in Common.dat | Display actual base level, spendable points and experience; verify boundaries per stage. |
| Master progression | `MasterSkillTree`: level, points, bigint experience and skill blob; Common.dat configures a 400 maximum | Read by verified character identity, display separately and define total-level semantics. |
| Enhancement progression | `EnhanceSkillTree`: points, skill and passive blobs; Common.dat configures a 950 maximum | Follow the active nested skill files, map progression storage, show points/tree state and verify its relationship with Master level. |
| Fifth-stage Ability system | `AbilityEnhancementCore.Inventory`; `Data/Skill/AbilityEnhancementCore/Ability*.xml`; Common.dat enables the system from level 1200 | Decode owned core/cards and their levels/options, distinguish character progression from card upgrades, preserve class compatibility. |
| Advancement quests and Giant Mount | Quest XML/rewards/instances; `Character.GiantMountType` and `GiantMountStatus` | Display verified advancement/mount state and preserve it across account services. |
| Stat specialization | `Data/Character/StatsSpecialize.txt` and Lua calculations | Verify which derived values can be faithfully shown; do not label raw stats as final in-game combat values. |

The SQL create script has no fields literally named `EnhanceLevel`,
`EnhanceExperience`, `AbilityLevel` or `AbilityPoint`. The Lua API exposes
`CheckMasterLevel`, `CheckEnhanceLevel` and `CheckAbilityLevel`, which do not
establish separate persisted level columns. Trace live before/after data instead
of inventing a mapping.

`Data/Util/ExperienceTable.txt` comments describe older level ranges. The global
Webzen update describes different release limits. Neither establishes this
pack's effective maximum level. Confirm the active server's boundary and total
level calculation with the client.

Extend the existing `MasterProgression`-only presentation with typed,
profile-owned progression data. Review public ranking order, account cards,
level filters, service previews and admin settings together. Reset, grand reset,
stat reset and paid progression must explicitly define which trees, points,
quests and core data they preserve. Initial adapters must not silently clear
unmapped state.

## Maps and movement

`Data/MapManager.txt` contains 121 configured records. That includes inherited
areas, instances and a record labelled Custom Arena; the count is not a count
of new or exclusively standard maps. Cross-check IDs against
`Data/Move/MapAttribute.xml`, `Gate.xml`, `Move.txt`, map-server routing and
`Data/Scripts/Define.lua`.

The later configured map records include:

| IDs | Pack names |
| --- | --- |
| 91–92, 95–96 | Acheron and Deventer areas |
| 97–99, 102 | Chaos Castle Final, Illusion Temple Final and Devil Square Final |
| 100–101, 110, 112–115 | Urk Mountain, Nars, Ferea, Nixies Lake, Instance Zone, Labyrinth of Dimensions |
| 116–120 | Deep Dungeon floors 1–5 |
| 121–122 | Trial Area, Swamp of Darkness |
| 123–127 | Kubera Mine floors 1–5 |
| 128–130 | Atlans Abyss floors 1–3 |
| 131–134 | Scorched Canyon, Crimson Icarus, Arenil Temple, Ashen Aida |
| 135–138 | Old Kethotum, Blaze Kethhotum, Kanturu Under Area, Ignis Vulcanus |
| 139–142 | Boss Battle Zone, Blood Tarkan, Tormenta Island, Twister Karutan |
| 143–145 | Kardamahal UnderGround Temple, Swamp of Doom, Aquilas Temple |

Names above retain the pack's aliases. Map-manager and drop-file spellings
sometimes differ. Build one reviewed ID-to-name mapping with aliases instead
of treating those names as separate maps. Check every configured record and
distinguish standard destinations from pack-specific areas. A character already
in a custom area still needs a safe location label; this does not authorize a
new custom event or move destination.

Acceptance: all standard configured IDs resolve correctly in profiles and
rankings; move-to-town uses verified gates and coordinates for every class;
instance/event entry restrictions cannot be bypassed by web movement.

## Standard event matrix

Paths in this table begin at `Data/Event/`. Select the active files through
`GameServer/DATA/GameServerInfo - Path.dat` and check the corresponding
GameServerCS file. Merge neither server's enable switches blindly into the
other's schedule. A file proves configuration availability, not a running event.

| Event or family | Configuration source | Website behavior and acceptance |
| --- | --- | --- |
| Blood Castle | `BloodCastle.dat` | Import exact schedule and warning/play phases; verify server host and entry brackets. |
| Devil Square | `DevilSquare.dat` | Exact starts, duration, entry levels and result source when available. |
| Chaos Castle | `ChaosCastle.dat` | Exact schedule, duration and brackets. |
| Chaos Castle Survival | `ChaosCastleSurvival.dat` | Model qualifying days, semifinals and final; do not flatten to a daily timer. |
| Castle Siege | `MuCastleData.dat`, `MuCastle_DATA` | Database-anchored seven-day cycle, registration/battle phases and owner guild. |
| Castle Deep | `CastleDeepEvent.dat` | Verify active host, schedule and duration. |
| Crywolf | `Crywolf.dat`, `WZ_CW_INFO` | Verify phase timing and applicable standard rules for this pack. |
| Doppelganger / Double Goer renewal | `DoubleGoerRenewal/DoppelGanger_EventInfo.xml` and position file | Active path selects renewal XML rather than the root legacy DAT; represent actual entry mode. |
| Illusion Temple and renewal | `IllusionTemple.dat`, `IllusionTempleRenewal/*.xml` | Resolve active variant and avoid duplicate events. |
| Illusion Temple League | `IlusionTempleLeague/*.xml` | Global switch and XML disagree: league XML has Enable=0; resolve runtime status and tournament rules. |
| Imperial Guardian / Fortress of Empire renewal | `ImperialGuardian/ImperialGuardian.dat`, `ImperialGuardianRenewal.xml` | Follow nested active paths; distinguish on-demand entry and timed phases. |
| Acheron Guardian / Protector of Archeron | `AcheronGuardian.dat` | Import configured starts, duration and locations. |
| Arca Battle | `ArcaBattle.xml`, `ARCA_BATTLE_*` and `T_ARCA_BATTLE_*` | Registration, preparation and battle phases; determine authoritative table family. |
| Tormented Square / Devil Square Final | `TormentedSquare/*.xml` | Preserve stage types and final schedule. |
| Tormented Square Battle | `TormentedSquareBattle/*.xml` | Verify relationship with Battle Core and publish correct event identity. |
| Battle Core | `BattleCore.dat`, `DB/BattleCore DB Create Script.sql` | Separate database dependency; only read verified data needed by supported event views. |
| Labyrinth of Dimensions and League | `LabyrinthOfDimension/*.xml`, `T_Labyrinth*` | On-demand content plus league cycle; show league timing without fabricating dungeon starts. |
| Group Boss Battle | `BossBattleEvent.xml` | Standard event confirmed by Webzen; map local timing, entry and map 139. |
| Standard invasions | `InvasionManager.xml` | Review each invasion independently, including Golden and White Wizard; exclude custom entries from the standard profile. |
| Kanturu and Raklion | `Kanturu.dat`, `Raklion.dat` | Distinguish boss-state/respawn mechanics from fixed calendar schedules. |
| World bosses | `WorldBoss/FereaBoss.xml`, `NixiesLakeBoss.xml`, `SwampOfDarknessBoss.xml`, `BossFightSystem.xml` | Validate standard identity and respawn source; random delay is not an exact countdown. |
| Moss Merchant | `MossMerchant.dat` | Display verified availability and duration. |
| Evolution Monster / Evomon | `EvolutionMonster/*.txt` | Verify standard activity identity, activation and trigger; do not invent recurring starts. |
| Seasonal activities and minigames | `20thAnniversary/*`, Common/Event settings, `T_MuRummy*`, `T_BombHunt*` and related tables | Inventory each implemented standard activity and actual activation period; old campaign data must not become permanently active. |
| Season/Battle Pass and Play Time | `BattlePass/BattlePass.xml`, `BattlePass*`, `PlayTimeEvent` | Show verified season/progress/eligibility privately; distinguish game rewards from website online-time rewards. |

Every configured standard event needs an explicit disposition: supported timed
event, supported on-demand activity, disabled by server configuration, or a
named verification blocker. A custom-event exclusion must identify the source
and reason; folder location alone is insufficient.

`EventDefinition` currently supports recurring times, weekdays and intervals.
Tournament stages, season/date ranges, on-demand entry and uncertain boss
respawns require reviewed extensions to the schedule model, admin validation
and UI. Preserve old-profile behavior and database-backed schedule overrides.
Confirm server timezone, weekday numbering, phase anchors and seconds before
converting schedules; the source includes non-round-minute start times.

### Implemented recurring profile

`game_events.profiles.mudevs_season_21` contains 21 source-backed recurring
entries, plus the database-anchored Castle Siege cycle. It preserves the original
11 schedules and adds Doppelganger Renewal, Arca Battle, Crywolf, three monthly
Chaos Castle Survival stages, three monthly Tormented Square stages and Tormented
Square Battle. Monthly stages retain their actual day-of-month restrictions;
the next valid date skips nonexistent dates rather than rolling into another month.
Weekday/calendar intersections and Sofia daylight-saving transitions are tested.

Doppelganger Renewal is **scheduled in this pack**, correcting the initial
on-demand classification: notify 20:25, ready 20:30, start 20:35, at least two
parties of two users. The new phase anchors use duration zero when a complete
active window is not established. They never manufacture an ongoing battle from
a warning/registration time or one round's duration.

Sixteen additional standard-activity cards explain entry, dungeon-state or
campaign availability without inventing countdowns. Imperial Guardian Renewal
requires level 1000 and accepts all sixteen families. Illusion Temple League is
disabled in its selected XML. Anniversary quests end in 2023 and the supplied
Battle Pass periods end on 30 December 2025; at the current date both are shown
as ended. No server campaign dates were altered. Acheron and ambiguous Temple
variants retain an in-game availability check.

The application-owned `days_of_month` JSON column is optional for existing event
overrides. Administrator forms and immutable revisions retain calendar days. The
SQL Server migration preserves old entries and refuses to drop populated calendar
restrictions. Empty calendar restrictions retain old schedule behavior and the
legacy client payload shape.

### Website activation milestone

`GAME_SERVER_PROFILE=mudevs_season_21` selects native repositories throughout the
reviewed website surface. Native WCoin(C), WCoin(P), GoblinPoint, Lucky Coin and
per-character Ruud remain separate from web Credits. Unsupported routes return
404 before unresolved services are constructed. The three legacy adapters retain
their original module and route availability.

The owner matched persisted class 18 to Blade Master. All remaining advancement
names now come from the supplied client's text catalog, with source-backed code
transitions; the class section records evidence and runtime verification limits.
A manual native account/inventory render returns HTTP 200. Automated tests use
only disposable SQLite projections and the internal SQL Server service.

Each definition identifies its switch/host and active source. Blood Castle,
Devil Square, Chaos Castle and Castle Deep use the enabled `GameServerCS` host.
Moss uses `GameServer`'s enabled 120-minute setting; the CS host disables Moss
and has a different 300-minute value, which must not override the active host.
All starts are explicit times: retain Blood Castle's 01:06/15:03 and Chaos
Castle's 11:24/16:50/17:13 rather than replacing them with regular intervals.
Invasions use the enabled GameServer host (GameServerCS InvasionManagerSwitch=0)
and XML IDs 0, 1, 2, 3 and 8, preserving their exact schedules and
converting the configured 600-second duration to ten minutes.

Warning, notify, play and close values are retained in definition provenance;
the current single-duration timer does not expose separate event phases. Client
verification must confirm the meaning of the configured anchor before treating
these recurrences as gameplay phase starts. Server time continues to come from
the existing configured content timezone; Windows timezone needs commissioning.

The remaining standard systems keep these explicit gates instead of fabricated
daily countdowns:

| Systems | Current disposition / next requirement |
| --- | --- |
| Acheron Guardian / Crywolf | Crywolf first-phase anchors are scheduled on ISO Wednesday/Saturday. Acheron timings are listed as entry information; activation remains a client check. |
| Illusion Temple / renewal / League | Both source schedules are available as disabled administrator definitions. Classic starts 01:30/05:30/09:30/13:30/17:30/21:30; Renewal starts 00:00; both last 15 minutes. Enable only the runtime-confirmed mode. League XML explicitly disables League despite the host switch. |
| Castle Siege, Arca Battle, Chaos Castle Survival, Tormented Square / Battle Core | Castle Siege uses its database cycle. Arca and Battle use exact anchors; monthly tournament stages are implemented. Live result/state dependencies and distinct runtime phase interpretation still need commissioning. |
| Doppelganger renewal, Imperial Guardian renewal, Labyrinth | Doppelganger has an explicit daily start. Imperial Guardian and Labyrinth have entry-information cards without recurring timers. Private progression is separate from entry information. |
| Kanturu, Raklion and world bosses | Read native states or verify respawn semantics before publishing an exact next occurrence. |
| Evolution Monster, seasonal invasions/minigames, anniversary, Battle/Season Pass and Play Time | Verify activation periods, triggers and private ownership/reward state. Invasion IDs 4–7 and 9–10 remain excluded pending this review, not relabeled as custom events. |

Custom-event files remain outside this standard profile. Tests exercise the
21 active definitions and two disabled Temple candidates through the normal
reader/calculator, irregular times, midnight rollover, active-host duration and
isolated persisted overrides without reading
or writing the live game database.

## Items, equipment and storage

`Data/Item/Item.xml` has 4,701 item records with unique section/index pairs
across 20 sections: 0–16, 19, 20 and 21. The highest item index is 510.
Do not impose S6's four-bit group or one-byte index limits on native metadata.
Every record has width and height; observed dimensions range from 1×1 to 5×3
and include 1×4 and 2×4 shapes. Preserve exact rectangles even without art.
The XML also exposes class requirements, equip slots, transaction, personal
store, warehouse, discard, sale and overlap metadata. Interpret each flag
against server behavior before allowing an operation.

| Area | Source | Required adapter behavior |
| --- | --- | --- |
| Main inventory | `Character.Inventory varbinary(5975)`, `ExtInventory` | Verify record format, actual DATALENGTH, equipment offsets, bag pages and usable extensions. |
| Main/extended warehouse | `warehouse.Items`, `ExtWarehouse.Items`, both varbinary(6000) | Resolve AccountIndex, active page and visible grid; preserve hidden records. |
| Event inventory | `EventInventory.Items varbinary(800)` | Determine actual record format and slot count independently. |
| Muun | `MuunInventory.Items varbinary(2575)`, `MuunPeriodItem`, nested Muun config | Show Muun equipment, options and periods without confusing it with main inventory. |
| Gremory Case | `GremoryCase.ItemInfo varbinary(25)` with expiry and identity fields | Account/character scope, reward source, expiry and claim state; separate from web Rewards. |
| Pentagram/Errtel and mastery | `PentagramJewel`, `Item/Pentagram - Settings/*` | Decode element, sockets, rank/options and companion records; retain bindings in custody. |
| Earrings, mastery and legendary gear | `EarringItemOption.txt`, `Mastery - Settings/*`, `LegendaryItem.xml` | Show verified grade/options, class restrictions, levels and trade rules. |
| Wing cores and exchange | `WingCoreItem`, `WingExchangeItem`, `WingCore.xml`, `WingExchange.xml` | Preserve referenced core serials, options and dependent data across transfers. |
| Artifacts | `ArtifactSystem`, `ArtifactSystem - Settings/*` | Map artifact groups, slot layout, levels, colors and associated item bytes. |
| Ability cores/cards | `AbilityEnhancementCore` and active skill XML files | Separate storage layout and card options, level, skill and class constraints. |
| Guardians, pets, lucky/periodic and stacked items | Guardian/Lucky/ItemStack/period config and `T_PetItem_Info`, `LuckyItem` | Verify ownership, binding, quantities and expirations; preserve associated state. |
| Ancient, excellent, Harmony, 380 and sockets | Active option, set, Harmony, socket and 380 config | S21-specific validation, tooltip names and lossless byte preservation. |

Several independent item columns are explicitly 25 bytes. `Data/Scripts/Define.lua`
also declares `INVENTORY_SIZE = 239`, 12 main equipment positions, a main boundary
of 76 and successive extension boundaries of 108, 140, 172, 204 and 236.
Pentagram and right/left earrings occupy 236, 237 and 238. These source constants
support the character record count, but do not establish the binary option
layout or how `ExtInventory` unlocks pages. Dividing warehouse capacity by 25
suggests 240 records; its visible grid still requires client verification. Event
inventory uses a different capacity and must be investigated independently.

The bundled `resources/game/mudevs_season_21/items.json` snapshot retains every
attribute from each XML `Section/Item`, keyed by section index and item index;
all native attribute values remain strings. Its source SHA-256 is
`cda914f8392e2bc153f8ceb08466f4f0fd72786bd69f4c48e647ff5ef5020450`.
`ItemCatalogMuDevsSeason21` exposes typed identity, name, dimensions and equipment
slot alongside the complete native attributes, including all 16 class flags,
requirements and operation/stack metadata. Artwork remains null. This catalog
deliberately does not implement the shared `ItemCatalog` contract yet: native
option interpretation must be verified before creating legacy tooltip types.
The separate shared-contract bridge now binds the native catalog and codec to
S21 storage. It supplies the exact source rectangles and native tooltip details;
legacy option categories are not assigned to S21 items. Shared slots accept all
25 bytes and preserve native presentation through rebasing/enrichment. Legacy
encoders explicitly reject these wider records.

The private account inventory/storage endpoint now reads the 239-record
character blob, the unlocked 64 + 32-per-extension bag (maximum five extensions),
and equipment slots 0–11 plus 236–238. Warehouse reads expose the client-visible
120 cells from each complete 240-record main/extended blob and use
AccountCharacter.ExtWarehouse for active-page selection. Foreign characters are
rejected before warehouse reads. Unsupported nonempty identity headers fail
closed rather than appearing as free cells. Inventory and vault tabs are active;
missing artwork uses the existing fallback.

A pure common-header encoder is checked byte-for-byte against eleven saved
client captures, including index 270, ancient, Harmony and socket variants.
It does not allocate serials or write game records. Higher group layouts, the
380 flag and special item dependency persistence are not enabled. The existence
of this encoder does not certify special-item generation or web shop delivery.

Native warehouse and inventory escrow now support ordinary tradable equipment
(groups 0–11 with native Serial, Transaction and StoreWarehouse flags) and the
five standard physical jewels. The Creation capture confirms a 25-byte, level-0
record; whole jewel stacks retain their quantity byte without splitting.
Unknown extension payloads, bound equipment and special item dependencies remain
rejected. Inventory custody preserves all 239 records and never places a rectangle
across the main bag/extension page boundary.

S21 custody requires a stable operation identity. A native receipt in
`IWEB_S21_ItemOperation` commits in the same game transaction as the blob change.
A matching receipt acknowledges retries even after the item leaves its destination;
an empty source cell without a receipt cannot establish a completed extraction.
The shared coordinator preserves the original repository calls for all older
adapters. Web Vault, market, rewards, administrator grants and credit exchanges
pass their existing durable operation identities through that coordinator.

Disposable SQL Server tests cover concurrent delivery (one item/one receipt),
receipt-insert permission failure rollback, exact 6000-byte retention and schema
bootstrap after runtime principals exist. The opt-in S21 schema hook leaves the
production entrypoint order and older bootstrap profiles unchanged. Runtime game
permissions are limited to the reviewed mutation boundaries; global write
switches still control commissioning. Real application-action
tests exercise both inventory and warehouse extraction through Web Vault and back.
Special dependencies, serial allocation and end-to-end market/shop certification
remain pending.

### Native construction and administrator grants

The native serial adapter calls the supplied `WZ_GetItemSerial` procedure and
accepts only positive signed-INT serials. A disposable SQL Server test verifies
consecutive reservations against its sanitized procedure and counter; no live
serials were reserved during development. The common-header encoder and native
warehouse journal now deliver a high-index `7:270` administrator grant through
the actual HTTP endpoint, with one reserved serial on replay.

`construction-options.json` contains 2,718 per-item option declarations from the
active nested `Data/Item/ItemOption - Settings/ItemOption.xml` selected by
GameServerInfo - Path.dat (SHA-256
`daba4898361dbfca771d950864f3be7c75a0ea5d001fc7b3a5a46a169b27683f`).
The source controls skill/luck/additional/excellent choices, tooltip effect values
and construction validation. Kris has no skill. Native armor Zen uses its source
30% rather than a legacy default. Construction rejects special dependencies,
ancient generation, 380 and uncommissioned Harmony generation before serial
allocation. Existing items retain their original options during custody.

Shared grant limits now retain the old 31/31/255 index boundaries and add S21's
511 boundary. Native editor metadata is optional; old catalog payloads and option
labels retain their previous behavior. Shop offer validation and purchase actions
reject unsupported native construction before charging Credits. Automatic generated
item pricing remains unavailable until explicitly configured; operator-priced
shop offers use their configured web Credits, separately from native coins.

### Website economy commissioning

S21 now exposes Credits, Vote Rewards, Web Vault, the physical-item Jewel Bank,
Market/Auction, operator-priced Shop, Mystery Boxes and Rewards through the
existing module switches. Their game boundaries use native S21 inventory,
warehouse and serial adapters; web Credits remain independent of WCoinC/WCoinP,
Goblin Points, Lucky Coins and Ruud. The bank counts original physical records
and returns complete records; it does not split stacks or synthesize jewels.

End-to-end disposable tests cover seller extraction/buyer delivery, auction
settlement, a one-charge/one-serial Shop purchase, unsupported-offer rejection
before charging, online-account Web Vault fallback and grouped jewel custody.
Exact inventory-for-Credits exchange receives one native batch receipt and one
ledger award. Native Harmony/socket/opaque extensions cannot match a basic
exchange signature that has no fields to price those options.

Public/private economy pages and eight administration pages render with S21
bindings. Persisted paid-character-service flags are filtered by route support;
only reviewed native endpoints can become visible through a saved setting. This
filter leaves legacy profiles unrestricted. Native VIP now has its own
account-scoped AccountLevel / AccountExpireDate purchase boundary; the old
per-character entitlement endpoint remains unavailable.

### Client-matched warehouse commissioning

Owner-supplied client screenshots and read-only reads now match ten distinct
25-byte examples in a 6,000-byte main warehouse. Their capture positions are 0, 2,
4, 6, 16, 38, 66, 75, 99 and 110; these fixtures need not reflect later client moves.
Account identifiers are not retained; fixture serial bytes
3–6 are replaced with synthetic values in
`tests/Fixtures/SqlServer/mudevs-season-21-warehouse-items.json`.

A separate eleventh capture,
`tests/Fixtures/SqlServer/mudevs-season-21-fire-seed-sphere.json`, matches a
Fire Seed Sphere tier 1 at native code 12:100, stored position 3, durability 1
and size 1×1. Its packed item level is 0; the sphere tier belongs to its native
item identity, not an item upgrade level. The screenshot shows the Fire
damage/wizardry-per-20-levels effect and +1.1. This capture verifies the standalone
sphere identity, not the encoded seed byte after insertion into equipment or a
general seed-effect formula. It is kept separate from earlier warehouse
captures because its later position overlaps a previously occupied rectangle.
Unit and administrator-preview tests retain the source tier in the name without
inventing Harmony/socket fields for the standalone sphere.

A twelfth, separate capture,
`tests/Fixtures/SqlServer/mudevs-season-21-flameberge-fire-socket.json`, matches
the same Fire sphere inserted into Flameberge's first socket through Seed Master.
The five socket bytes are `00 FE FE FF FF`, with bonus `FF`: zero is a valid
filled seed, FE is an empty socket, and FF is an absent socket position. The
client shows Level 1 Fire, damage/wizardry damage per 20 levels, +1.1. Active
`SocketItemOption.xml` Index=0, Element=1 and Value1=11 corroborate that one
description. `ItemCatalogMuDevsSeason21::socketSeedFor()` names only explicitly
client-matched seed codes; unverified filled codes remain visible as raw codes.
The administrator preview translates the matched Fire description into English
and Bulgarian and retains empty/absent distinctions without dropping zero.
No other element/tier encoding or full damage formula is inferred from this
single insertion, and all original 25 bytes remain unchanged on re-encoding.

A thirteenth capture,
`tests/Fixtures/SqlServer/mudevs-season-21-flameberge-fire-lightning-sockets.json`,
matches a tier-1 Lightning sphere inserted into the second socket of the same
Flameberge. The capture position is 19 and the socket bytes are
`00 1D FE FF FF`; the existing Fire zero is retained, third socket stays empty
and bonus remains FF. Client text is Excellent damage increase +15, corroborated
by active `SocketItemOption.xml` Index=29, Element=5, Value1=15. The native
metadata and bilingual preview now recognize raw seed 29 as that captured
tier-1 Lightning effect. Other unverified codes remain raw; no higher-tier
packing or automatic socket-bonus activation is inferred from this sample.

A fourteenth capture,
`tests/Fixtures/SqlServer/mudevs-season-21-flameberge-three-seeds-bonus.json`,
matches tier-1 Ice inserted into the third socket. The captured position is 2,
the socket bytes are `00 1D 10 FF FF` and the bonus changed from FF to 00.
Client text is life increase on monster destruction +250 for Ice, followed by
Attack +11 as the bonus. Active SocketItemOption.xml Index=16, Element=3,
Value1=250 corroborates the Ice effect. SocketBonusSettings Index=0, item
sections 0–4 and Value=11 corroborates the bonus description and its section
boundary. The bilingual preview now names all three matched seed effects and
bonus 0 while retaining their raw diagnostic codes. An unsupported bonus code
or an out-of-range section does not acquire the Attack +11 description.
This observed insertion confirms that this item acquired a bonus; it does not
establish a universal bonus probability or higher-tier seed encoding.

A fifteenth capture,
`tests/Fixtures/SqlServer/mudevs-season-21-cyclone-fire-tier-seven.json`, matches
Fire tier 7 in Cyclone Sword's first socket at saved position 3. Its first raw
socket byte is 2E (46), and shared byte 8 contains 40 hex (64). The remaining
socket positions are FE FE FF FF, the bonus is FF, and bytes 16–24 remain FF.
Client text shows Fire level 7 with the same per-20-level damage/wizardry effect
at +1.7, corroborating active SocketItemOption.xml Index=0, Value7=17.

This capture corrects the previous treatment of byte 8 as entirely ancient
data: for source-declared socket gear, the observed 0x40 flag is now removed
from the exposed ancient option and retained separately as socket header
context. Other upper bits remain raw/unverified context; the exact original
25 bytes are retained. `socketSeedFor()` matches the captured tuple of header
64, position 0 and raw value 46 instead of inventing a general grade formula.
That byte without the flag, another socket position, unsupported flag
combinations or a non-FF extension tail do not acquire the tier-7 description.
Normal tier-1 and actual ancient-armor fixtures remain valid. Native SQL Server
coverage verifies the shared flag survives loading and exact re-encoding.

### Client item artwork

The matching supplied client now provides static artwork for 4,688 of the 4,701
native catalog identities, using 2,690 distinct BMD models. Runtime artwork paths
are in `item-images.json`; source table/model/texture/output hashes, exact item
mapping, export limits and missing resources are in `item-image-provenance.json`.
The images are transparent WebP, cropped to a maximum dimension of 256 pixels;
source item grid rectangles remain unchanged. Total new image payload is
42,303,324 bytes. The original two pilot PNGs remain available for existing
persisted snapshots.

The authoritative mapping is the client's `Data/Local/Eng/Item.bmd`: 4,727 records,
708 bytes per record, XOR FC/CF/AB reset per record, packed identity at offsets
0/4, model folder at 8, model filename at 268, and English name at 528. Every
one of the server catalog's 4,701 codes and names matches exactly. No filename
index arithmetic or legacy adapter mappings are used to assign artwork.

All 2,690 referenced available models parsed successfully, including BMD versions
10, 12, 14 and 15. The offline decoder uses the public xulek BMD viewer cipher
implementations (FileCryptor, LEA and ModulusCryptor); no client executables were
run and no application dependencies were added. Models are rendered at action 0,
frame 0 using their bone hierarchy and matching OZJ/OZT textures. JPEG wrapper
headers are removed before decoding. Border-black texture backgrounds and black
additive effect backgrounds become transparent. The output represents static
base artwork; exact game lighting, +level glow, option tint and animation remain
outside scope. Several native item identities intentionally share one client
model and one output image.

Thirteen catalog identities keep null artwork and the shared fallback: seven
client records explicitly use Empty/Empty, while six Muun model files referenced
by the table are absent from this supplied client. The Blue Eye Summoner Helm
(7:167) is rendered with its available helmet textures; the client lacks its
secondary HQSkinClass406_hair.tga texture. These limits are recorded explicitly
in provenance rather than substituted with unrelated graphics.

The local extraction tools are retained outside Git at
`storage/app/private/s21-item-artwork/extraction-tools.tar.gz`; the reviewed
contact sheet is `storage/app/private/s21-item-artwork/preview.jpg`. Extract the
tool archive into /tmp, then run prepare-jobs.py, bulk-parse.mjs,
prepare-textures.py, export-images.py, publish-export.py and contact-sheet.py
in that order. They target the documented local client/workspace paths and use
Node 24 plus the existing Python Pillow installation. Raw models and textures
remain outside the repository.

Validation: 1,094 isolated tests / 46,933 assertions and all four profile smoke
runs pass; S21 SQL integration passes 29 tests / 168 assertions. Every exported
image is checked for dimensions, MIME and output SHA-256. Account storage and
admin inspection tests assert the new paths while preserving source geometry.
Nginx serves extracted WebP with HTTP 200. Read-only live commissioning resolves
artwork for all nine currently visible account items. Old adapter catalogs and
images are unchanged; game data was not mutated.

| Item | Native code | Level | Rectangle | Matched option evidence |
| --- | --- | --- | --- | --- |
| Brilliant Knight Helm [S] | 7:270 | 15 | 2×2 | Luck; byte 7 bit 7 supplies index bit 8. |
| Rune Blade | 0:31 | 13 | 2×4 | Skill, Luck, option 1, excellent mask 1. Byte 10 remains uninterpreted. |
| Sword Dancer | 0:25 | 13 | 2×4 | Luck, option 4 (+16 displayed). |
| Thunder Hawk Armor | 8:20 | 13 | 2×3 | Luck, option 1 (+4 defense displayed), excellent mask 1 (Zen). |
| Dragon Knight Armor | 8:29 | 15 | 2×3 | Luck, option 1; stored durability 255 is retained despite lower displayed maximum. |
| Flameberge | 0:26 | 0 | 1×4 | Three empty sockets: bytes 11–15 are FE FE FE FF FF; byte 10 is FF. |
| Kris | 0:0 | 9 | 1×2 | Luck, option 7 (+28 displayed), excellent mask 63. Skill is absent despite requesting it through `/make`; the native definition declares Skill=0. |
| Warrior's Leather Armor | 8:5 | 9 | 2×3 | Byte 8 is 05; variant 1 maps to native set index 1, Warrior's. Client shows stamina +5. |
| Kris with Harmony | 0:0 | 9 | 1×2 | No Luck/excellent/additional option; byte 10 is 11 hex (17), index 1, level 1; client shows minimum attack +3. |
| Flameberge with bonus | 0:26 | 0 | 1×4 | Three empty sockets and byte 10 equal to 00; client shows bonus Increases Attack +11. Zero is a real bonus code, distinct from FF (none). |

`ItemCodecMuDevsSeason21` reads only verified common fields and uses the separate
`ItemRecordMuDevsSeason21` boundary. It retains every byte, including unknown
extensions and unsupported identity flags, and can concatenate complete original
record sequences exactly. It does not implement the shared generation contract,
does not create new items and does not extend the legacy ItemSlot byte limits.
Group-header low-bit flags are currently treated as unsupported identities;
native groups above 15 and advanced flag meanings require additional captures.
All diagnostic snapshots explicitly mark advanced option interpretation unverified.

The active nested `SocketItemType.xml` contributes 231 socket-item identities to
`resources/game/mudevs_season_21/socket-items.json`, all with a maximum of three.
The inspector exposes raw socket positions only for those native socket items,
without calling every occupied seed a verified decoded seed option. Filled seeds,
bonus semantics, other ancient variants/full-set activation, the complete Harmony
effect catalog, 380, expiry and newer option families remain separate verification
tasks. Shared S6 behavior is a comparison source,
not automatic certification of the S21 extension bytes.

`ancient-items.json` joins all 1,356 native `SetItemType.xml` identities to the
three declared variant slots and set index/name from the active nested
`SetItemOption.xml`. Diagnostic records retain the original ancient byte and
resolve only item-supported variants; an ancient flag on a non-set Kris does not
invent a set name. The common low-two-bit variant mapping is client-matched for
the Warrior's example; no full-set stat activation is calculated here. Non-socket
weapon/armor snapshots expose the raw shared Harmony byte and its index/level
nibbles. Socket items use that byte exclusively as their socket bonus, and groups
above 11 do not acquire a guessed Harmony field. The corrected Harmony `/make`
probe, rather than the repeated excellent Kris, supplies the Harmony fixture.

The read-only `game:inspect-s21-warehouse {account} --vault=0 [--json]` command
uses the configured game connection independently of the incomplete selectable
profile. Zero selects main storage; a positive value is an exact native
`ExtWarehouse.Number`. Reads are account-scoped, retain all 240 stored records
and reject malformed/uninitialized blobs. The command is a diagnostic interface,
not a public warehouse endpoint or transfer/generation action. Client-visible
pages and extension unlocking still require independent verification.

The pack documents `/make` in `GameServer/DATA/GameServerInfo - Command.dat`:

```text
/make <group> <id> <lvl> <dur> <skill> <luck> <opt> <exe> <set> <harmony> <extl> <socketcount> <socketbonus> <exp> <count>
/make 0 26 0 255 0 0 0 0 0 0 0 3 255 0 1
/make 0 0 9 255 1 1 7 63 0 0 0 0 0 0 1
/make 8 5 9 255 0 0 0 0 5 0 0 0 0 0 1
/make 0 0 9 255 0 0 0 0 0 17 0 0 0 0 1
/make 0 26 0 255 0 0 0 0 0 0 0 3 0 0 1
```

These samples produced the captured Flameberge, excellent Kris, ancient armor,
Harmony Kris and Flameberge with socket bonus respectively. They establish open socket positions, full classic
excellent/option masks and one ancient/Harmony example each; they do not establish
filled seed encoding or all values of the remaining make arguments.

A paired Dragon Knight Armor probe used identical level/durability and zero
options, with only `extl` changing from 0 to 1. The saved records were identical
except for serial bytes, and both client tooltips lacked additional 380 lines.
This probe does not establish a 380 flag: `extl=1` must not be treated as verified
380 generation. Obtain a client item with an actually applied 380 option before
implementing that interpretation.

### Permissioned web preview

`/admin/s21-warehouse` now renders the native warehouse inspector through a
dedicated read-only controller/request and `WarehousePreviewMuDevsSeason21`.
It requires administrator authentication, ManageSettings permission and the
explicit `GAME_S21_WAREHOUSE_PREVIEW_ENABLED` flag (false by default). The local
commissioning environment enables that flag while retaining its existing fixture
profile and closed game writes. The administrator supplies an account and an
exact native vault number; missing/invalid storage is reported without padding
or substituting another account's data.

The view renders all 240 stored positions, source-backed item rectangles, shared
fallback artwork, native identity/level and confirmed diagnostic option fields.
Hover/focus tooltips and a selectable detail panel preserve unknown option states
without inventing seed/effect names. Serial/native hex fields are omitted. It is
a commissioning preview, not a claim that all 240 cells are client-visible or
that native character equipment/extension pages and public account storage are
complete. English/Bulgarian strings and a conditional administration link are
included. No transfer, sale or creation actions are provided.

Required shared changes identified in the existing implementation:

- `app/Game/Data/ItemSlot.php` rejects every byte length except 10 and 16.
  Extend validation through a deliberate version format boundary, with tests
  retaining rejection of malformed records.
- `ItemCodec::create()` and `ItemSlot` expose S6-era options. Add typed,
  version-aware options where S21 needs them; retaining opaque bytes is
  necessary but insufficient for claiming complete decoded option support.
- `ItemDefinition` lacks the new trade/stack/period/grade metadata. Keep those
  rules authoritative in catalog/grant/transfer boundaries, not just tooltips.
- `account-inventory-panel.blade.php` fixes the bag at 64 cells;
  `equipmentSlotLayout` in `resources/js/muonline.js` fixes equipment placement.
  Supply profile/layout metadata for equipment, pages and special storage.
- Shared missing-art presentation is implemented by `<x-item-image>` and
  `public/images/items/unknown-item.svg`. Inventory/equipment grids, item action
  modals, custody, market, shop, rewards, jewel bank, exchange and administration
  previews use it. Null/empty paths and failed image loads display the fallback;
  an already failed load is checked on Alpine initialization, and a failed
  fallback cannot cause a retry loop. Grid geometry and tooltips stay intact.
  Canonical catalog/snapshot artwork remains nullable.
- Client-art pilot: the supplied `ucrtbased` BMD v15 models and OZJ/OZT textures
  now provide transparent base images for Flameberge (0:26) and Dragon Knight
  Armor (8:29), preserving their 1x4 and 2x3 source geometry. Only these matched
  identities are assigned in the native catalog. The bundled
  `item-image-provenance.json` records model/texture hashes and static-rendering
  limits. This historical two-icon pilot has now been superseded by the
  native-table bulk export described above, including Cyclone Sword and v14 models. Per owner direction, the web
  needs recognizable static icons as in existing versions. Exact 3D fidelity,
  animated effects, +level glow and option-dependent colors are outside the
  artwork scope; models are only an offline source for PNG generation.
- Audit serial types and reservation semantics, custody snapshots, reward/grant
  persistence and schema limits before accepting S21 bytes. The supplied
  `WZ_GetItemSerial` returns an int, while WingCore uses bigint references;
  neither alone proves the native item's complete serial encoding.

Original-item transfers must retain bytes and every required dependent record.
Use explicit per-item operation capabilities: can display, can transfer, can
generate. An unknown dependency blocks that operation until implemented; it
does not remove the system from the completion backlog.

## Accounts, economy and character services

| Area | Confirmed source | Implementation and verification |
| --- | --- | --- |
| Authentication | `MEMB_INFO`, JoinServer `MD5Encryption=0`, password column varchar(10) | Verify deployed mode and case/length behavior; implement matching registration, activation and recovery. |
| Account/character identity | `AccountCharacter.AccountIndex`, `Character.CharacterIndex`, ten named slots and index columns | Use verified ownership joins and update both representations where required. |
| Online sessions | `MEMB_STAT` | Enforce offline conditions inside every write and distinguish offline helper/shop states if applicable. |
| VIP | AccountLevel and AccountExpireDate | Native account purchase/replay implemented; owner confirmed purchased VIP tier and expiry in the client. |
| Zen, Ruud, cash points and Lucky Coin | Character Money/RuudMoney, warehouse Money, CashShopData, LuckyCoin | Separate game balances from web Credits; read privately, add audited writes only for explicit supported workflows. |
| Gens | `Gens_Rank`, `Gens_Left`, `Gens_Reward` | Family/contribution/rank and eligibility semantics verified independently of S6. |
| Reset, Master Reset, stats and PK | Native counters/stats, `MasterResetCount`, `EnhanceResetCount`, ResetTable/PkSystem | Owner confirmed reset, Master Reset counter, allocation/refund and PK clear. S21 presents the shared grand-reset operation as Master Reset while retaining legacy schema support. Normal/Master level remain distinct operation inputs. |
| Rename/class change | Indexed and name-based references throughout game schema | Owner confirmed rename and representative class changes including advancement preservation; skill/equipment guards and journaled recovery remain enforced. |
| Quest progress, hunting and Monster Soul Converter | Quest/MuQuest/QuestGuide config, `MuQuestInfo`, `QuestWorld`, `T_HuntingRecord*`, `MonsterSoulConverter*` | Read verified progress privately and preserve it during services. |
| Web economy | Existing shop/reward/bank/market/auction actions | Bind S21 repositories, capabilities and item rules; test idempotency, exact custody, full-storage recovery and concurrency. |

Standard game mechanics that execute in the game server, such as combat,
crafting and quest completion, remain server-owned. Website support means
accurate presentation, integration and preservation, plus verified web actions
where applicable. Do not create competing web skill/quest execution rules.

## Configuration discrepancies to resolve

1. Active nested enhancement, renewal-event and Muun paths differ from similarly
   named root copies. Read the selected path, not the first filename match.
2. `AbilityEnhancementCore.xml` is referenced by Path.dat but absent at the
   referenced location. Other Ability XML files and the SQL table exist.
3. `EventTimmer.dat` and QuestWorld text paths are referenced but absent in this
   copy. Determine whether they are obsolete, optional or supplied elsewhere.
4. Some path differences are only Windows casing: `Quest4Th.xml` resolves to
   `Quest4th.xml` and `MuQuest.xml` to `MUQuest.xml`. An importer on Linux must
   distinguish case-insensitive matches from genuinely missing files.
5. Illusion Temple League has conflicting enable settings. Confirm effective
   activation on the responsible server process.
6. Base-stat headers and comments sometimes name older updates. In particular,
   the Crusader base-stat file flags some life/mana scaling as unfinished.
   Verify active Lua formulas before exposing derived values or base-stat resets.

## Delivery sequence and acceptance gates

Suggested profile key: `mudevs_season_21`, scoped to this verified distribution.
Do not add it to supported-profile lists until its adapter bundle can resolve.

1. **Schema and runtime baseline.** Confirm SQL endpoint/database and restricted
   access, inspect live columns/types/defaults/procedures against both scripts,
   map account/character indexes and capture sanitized representative records.
   Keep game writes closed and separate the application database. Exit: each
   schema mismatch has a disposition and the format/identity probes are defined.
2. **Profile and complete read models.** Implement all class families and verified
   evolutions, maps, account/realm/guild/ranking reads, progression, balances and
   specialist-system views. Register matched game/event profiles, doctor schema
   and permission requirements, fixtures and profile-matrix smoke coverage.
   Exit: the test account and all class/stage fixtures render correctly.
3. **Standard activities.** Implement the event matrix, active-source imports,
   multi-phase/date/on-demand schedule behavior and admin overrides. Exit:
   every standard configured activity has its supported behavior or a named
   unresolved source/runtime dependency; only fully verified entries are active.
4. **Item format and complete storage presentation.** Implement codec, catalog,
   typed options, all storage layouts and associated read models with no-image
   fallback. Exit: golden fixtures re-encode byte-for-byte, match client option
   displays and prove visible/hidden boundaries on every page.
5. **Account and character writes.** Implement lifecycle and preview/execute
   services, including advanced progression/quest/name dependencies. Exit:
   ownership, offline, range, rollback, audit and retry tests pass; client state
   remains consistent after each operation on disposable accounts.
6. **Item delivery and economy.** Verify serial reservation and simple grants,
   then shop/rewards, Web Vault/Jewel Bank, Market/Auction and complex-item
   dependency custody. Prices are administrator-reviewed; no invented S6 price
   import. Exit: all supported item families survive transfers, expiry rules,
   full storage, retries and concurrent requests without duplication/loss.
7. **Full certification.** Resolve every runtime-pending matrix entry or record
   the exact pack limitation; do not claim complete standard S21 support while
   a required system is merely stubbed or hidden. Run old-profile regression,
   S21 SQL integration, manual commissioning and recovery checks before writes
   are opened for normal accounts.

All user/admin presentation needs English and Bulgarian translations. Reuse
existing module switches, permission checks, audited settings, recovery actions
and query-state navigation.

## Test and client fixture plan

Add a minimal sanitized S21 schema and a dedicated disposable SQL Server
integration workflow following the existing adapter pattern. Do not execute
the supplied full create scripts against a configured game database, and never
run automated tests against `192.168.0.104`.

Automated coverage must include:

- All class families, valid stages, transitions, stat limits and equipment rules.
- Base/Master/Enhance/Ability interpretation, total-level sorting and boundaries.
- Every configured standard map and safe movement eligibility.
- Event source selection, enable precedence, weekdays, phases, dates, timezone,
  midnight and unavailable/uncertain next-start behavior.
- Item catalog keys/dimensions, native binary round trips, malformed input,
  unknown fields, serials and dependent-record preservation.
- Every storage boundary/page, non-overlap, ownership, offline checks and
  full-storage recovery; all existing item/economy and account workflows.
- SQL Server binary binding, locking, transaction rollback and exact-byte
  comparisons, plus interrupted and concurrent operation recovery.

Capture golden fixtures using disposable client characters and known positions:
empty storage; first/last usable cell of each page; additional equipment slots;
each advanced option family; pentagram/errtel; Muun; artifacts; wing cores;
ability cards; guardians; stacked, bound and periodic items. Snapshot dependent
rows as well as blobs. Move only one known item/change one option per probe.
Check both client display and saved state after logout/relogin.

Use `./docker/compose test` for narrow unit/feature coverage during development,
then the full suite and `test-all` profile matrix for shared changes. Run
`test-integration` and `test-integration-1.0m` regressions and the new S21 workflow
before certification. Format modified PHP with Pint. Documentation-only changes
do not require application test execution.
