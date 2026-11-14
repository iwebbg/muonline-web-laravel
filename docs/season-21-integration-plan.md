# MUDevs Season 21 compatibility and implementation plan

Status: foundation in development, 14 November 2026. The S21 adapter is not
selectable yet. The target is the owner-supplied MUDevs S21.1-2 distribution,
including its standard inherited game systems, all playable class families and
their advancements. A read-only panel is an intermediate milestone, not the
definition of complete support.

The implementation must preserve the three existing adapters and use the
current Laravel, Blade, Alpine and Tailwind architecture. Initial item images
may be absent; verified dimensions and readable presentation are required.

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

These components do not register a partial game profile. Native currency reads
are not yet exposed in the account UI. Market and Web Shop remain later
compatibility tests; no existing module settings have been changed.

The first foundation commit is `6dcd812`. The subsequent read-model stage adds:

- `MuDevsSeason21Profile`, containing the 16 SQL-seeded base class codes,
  source stats, portraits and read bindings. It remains outside the selectable
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
blank and game writes remain closed. `GAME_DATA_SOURCE=fixture` keeps the
existing fixture profile usable until the S21 bundle can be registered; this
retained S6 selector must not be used with real S21 data. The previous local
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

`game_events.profiles.mudevs_season_21` now contains 11 source-backed recurring
definitions: Blood Castle, Devil Square, Chaos Castle, Castle Deep, Moss Merchant,
Group Boss Battle, Skeleton King, Red Dragon, Golden, White Wizard and Medusa.
The existing `EventProfileReader` and `EventSchedule` consume this profile without
changes; administrator overrides retain profile/version isolation. The S21 game
bundle is still incomplete and not selectable, and the active environment remains
on its existing fixture/game/event profile. These are configured recurrences,
not evidence of live event execution or a complete standard-activity implementation.

Each definition identifies its switch/host and active source. Blood Castle,
Devil Square, Chaos Castle and Castle Deep use the enabled `GameServerCS` host.
Moss uses `GameServer`'s enabled 120-minute setting; the CS host disables Moss
and has a different 300-minute value, which must not override the active host.
All starts are explicit times: retain Blood Castle's 01:06/15:03 and Chaos
Castle's 11:24/16:50/17:13 rather than replacing them with regular intervals.
Invasions use XML IDs 0, 1, 2, 3 and 8, preserving their exact schedules and
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
| Acheron Guardian, Crywolf | Timed source exists; confirm host/activation, weekday numbering and phase anchors. |
| Illusion Temple / renewal / League | Resolve the selected variant; League XML disables it despite the global switch. |
| Castle Siege, Arca Battle, Chaos Castle Survival, Tormented Square / Battle Core | Implement cycle/stage/phase models and verified native result/state dependencies. Arca XML enables all weekdays at 10:45 with EventType=0; that anchor is not yet interpreted as a battle phase. |
| Doppelganger renewal, Imperial Guardian renewal, Labyrinth | On-demand activity sources; dedicated entry/availability/progression views remain pending rather than recurring dungeon starts. |
| Kanturu, Raklion and world bosses | Read native states or verify respawn semantics before publishing an exact next occurrence. |
| Evolution Monster, seasonal invasions/minigames, anniversary, Battle/Season Pass and Play Time | Verify activation periods, triggers and private ownership/reward state. Invasion IDs 4–7 and 9–10 remain excluded pending this review, not relabeled as custom events. |

Custom-event files remain outside this standard profile. Tests exercise the
11 definitions through the normal reader/calculator, irregular times, midnight
rollover, active-host duration and isolated persisted overrides without reading
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
It is not bound to the selectable profile or generation/transfer services.

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
| VIP | AccountLevel and AccountExpireDate | Account-scoped entitlement; extend product contracts deliberately rather than writing it as character VIP. |
| Zen, Ruud, cash points and Lucky Coin | Character Money/RuudMoney, warehouse Money, CashShopData, LuckyCoin | Separate game balances from web Credits; read privately, add audited writes only for explicit supported workflows. |
| Gens | `Gens_Rank`, `Gens_Left`, `Gens_Reward` | Family/contribution/rank and eligibility semantics verified independently of S6. |
| Reset, grand reset, stats and PK | Native counters/stats, `EnhanceResetCount`, ResetTable/PkSystem | Preview and transactional execution with explicit progression preservation policies. |
| Rename/class change | Indexed and name-based references throughout game schema | Enumerate all affected records, including quests, pets, social/event and specialized equipment state. |
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
