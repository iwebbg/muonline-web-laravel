# Product and UX direction

## Product goal

Build a secure, modern and version-aware MuOnline server web application. The
supported `darksteam_97d99i`, `darksteam_1_0m` and `muemu_season_6` profiles
prove the adapter boundary; feature code must not hard-code any one schema
throughout controllers or views.

The application is an interactive product, not a collection of old-style PHP
forms. Every important action must provide immediate feedback while preserving
server-side validation and transactional safety.

## Experience principles

1. **Interactive by default** — filters, previews, counters, validation,
   confirmations and status changes update without full-page navigation where
   this improves the task.
2. **Server-authoritative** — UI responsiveness never replaces authorization,
   validation, offline checks, transactions or audit logging in Laravel.
3. **Preview before mutation** — reset, grand reset, stat allocation and economy
   operations show their calculated result and cost before confirmation.
4. **Game-native visual language** — dark fantasy presentation, restrained
   glow, metallic surfaces and clear rarity colours without sacrificing
   readability or responsive behaviour.
5. **Accessible interactions** — keyboard operation, visible focus, semantic
   controls, reduced-motion support and no information conveyed by colour alone.
6. **Responsive by design** — public pages, account tools and administration
   remain usable on mobile, tablet and desktop.
7. **Progressive enhancement** — core actions remain regular Laravel endpoints;
   Alpine.js enhances them instead of creating a second frontend application
   and API stack.
8. **Bilingual by design** — every interface surface supports English and
   Bulgarian. English is the default and fallback language, while the visitor's
   explicit selection is preserved in the Laravel session.

## UI technology

- Blade for page composition and the server-rendered first response.
- Tailwind CSS v4 with CSS-first tokens and reusable component classes in
  `resources/css/app.css`.
- Alpine.js for local interaction, disclosure, tabs, tooltips and lightweight
  previews.
- Small framework-independent JavaScript modules for inventory and character
  visualisation when a grid/canvas interaction is a better fit than Alpine.js.
- Laravel events and queued work for long-running operations; WebSocket/SSE
  support may be added later for genuinely live server state.

The established stack is Blade, Alpine.js and Tailwind CSS v4. Do not introduce
a second SPA frontend or replace this stack without an explicit product-level
decision. Livewire is not currently installed; adding it is a dependency and
architecture decision that requires approval.

## Implemented interface patterns

- The sticky public header, account navigation and administrator navigation use
  the same dark-fantasy token system and responsive shell.
- Account-wide modules have dedicated routes such as `/account/credits`,
  `/account/votes`, `/account/jewel-bank`, `/account/zen-bank` and
  `/account/security` rather than being appended below character actions.
- Character selection and active character tabs use URL query state so refresh,
  redirects and browser history reconstruct the same workspace.
- Account Vaults combine game warehouses and exact-item Web Vault storage in a
  responsive desktop split; Jewel Bank remains a separate module and its
  custody items are not duplicated in Web Vault.
- Focused changes and item actions use teleported Alpine dialogs with backdrop,
  Escape handling and a z-index above inventory grids and tooltips.
- Server-backed selectors may submit a normal GET request. Their form URL uses
  a stable section anchor and `scroll-mt-28` so a desktop reload returns to the
  changed controls.
- Preview cards show current value, resulting value, cost, requirements and
  block reasons inline. Internal action or transaction names are never used as
  player copy.

## Tailwind CSS v4 conventions

- Keep CSS-first configuration through `@import 'tailwindcss'`, `@source` and
  `@theme`; do not create a legacy Tailwind configuration file.
- Reuse the existing semantic palette: `void`, `panel`, `mist`, `silver`,
  `steel`, `gold` and `crimson`.
- Prefer shared classes such as `shell`, `panel-card`, `button`, `field-group`,
  `segmented-control`, `status-pill` and the item-grid primitives before adding
  screen-specific CSS.
- Build mobile-first, then use responsive breakpoints to place related panels
  side by side on large screens. Do not leave a narrow mobile-width panel in a
  mostly empty desktop canvas.
- Use `gap` for sibling spacing, retain visible focus, and keep controls
  keyboard operable.
- Item rarity, excellent state and special item colour come from decoded item
  metadata. Raw item coordinates or codes are not player-facing labels.

## Main application areas

### Public site

- Server presentation, status, online population and event countdowns.
- News, downloads, guides and server information.
- Interactive rankings with class filters, search and pagination.
- Public character and guild profiles.

### Account panel

- Account summary and security state.
- Character switcher with visual class cards.
- Character profile: equipment silhouette, stats, progression, resets, PK,
  location and online state.
- Inventory and warehouse grid with Mu item tooltips.
- Reset/grand reset calculator and preview.
- Interactive stat allocation with remaining-points calculation.
- Web storage, wallet, transaction history and other enabled modules.

### Administration

- Operational dashboard and server health.
- Searchable account, character and guild management.
- Typed settings with live previews and validation.
- Module/capability toggles by server profile and game version.
- News, downloads, event schedules and content management.
- Economy, market and moderation tools.
- Immutable audit trail for settings and game-data operations.

## Character visualisation

The initial implementation uses layered 2D presentation:

- class artwork or avatar;
- equipment slot silhouette;
- equipped item images decoded from the inventory blob;
- stat/progression cards and animated-but-restrained state changes;
- online/offline, VIP, guild and PK indicators.

This delivers a rich character view without requiring a 3D engine. A later
version may support WebGL models if suitable licensed game assets and model
formats are available.

## Visual direction

The previous Laravel prototype is the starting mood reference, not the final
component library. Preserve useful ideas such as the dark background, server
summary, ranking panels and Mu item art. Replace the dense generic admin theme
with a focused design system shared by public, account and admin surfaces.

The design system will define colour, typography, spacing, surfaces, borders,
rarity colours, motion and reusable interactive states before feature screens
diverge.
