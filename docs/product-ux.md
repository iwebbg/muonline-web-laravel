# Product and UX direction

## Product goal

Build a secure, modern and version-aware MuOnline server web application. The
first supported profile is 97d+99i, but feature code must not hard-code that
schema throughout controllers or views.

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
   Alpine.js and Livewire enhance them instead of creating a second frontend
   application and API stack.
8. **Bilingual by design** — every interface surface supports English and
   Bulgarian. English is the default and fallback language, while the visitor's
   explicit selection is preserved in the Laravel session.

## UI technology

- Blade for page composition and server-rendered first response.
- Alpine.js for local interaction, disclosure, tabs, tooltips and lightweight
  previews.
- Livewire for server-backed tables, filters, settings forms and operations
  where real-time server validation adds value.
- Small framework-independent JavaScript modules for inventory and character
  visualisation when a grid/canvas interaction is a better fit than Livewire.
- Laravel events and queued work for long-running operations; WebSocket/SSE
  support may be added later for genuinely live server state.

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
