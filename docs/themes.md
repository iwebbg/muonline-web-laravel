# Blade theme development

MuOnline Web uses directory-based, update-safe themes for public and
player-facing Blade views. The complete default theme lives in
`resources/views/themes/muonline`. Every other direct child directory is an
optional custom theme and needs no PHP class, JSON manifest or duplicated
application logic.

## Theme structure

The directory name is the theme slug. It must begin with a lowercase letter or
number and contain only lowercase letters, numbers and hyphens:

```text
^[a-z0-9][a-z0-9-]*$
```

A custom theme contains only the files that it changes. For example:

```text
resources/views/themes/dark-fantasy/
├── home.blade.php
├── components/
│   └── guild-mark.blade.php
└── layouts/
    └── app.blade.php
```

Direct child directories are discovered automatically. Optional display labels
may be added to `config/themes.php`; otherwise the slug is converted into a
readable title.

## Overrides and fallback

An override uses the same relative path as the default theme:

| Default view | Custom override |
| --- | --- |
| `themes/muonline/home.blade.php` | `themes/dark-fantasy/home.blade.php` |
| `themes/muonline/layouts/app.blade.php` | `themes/dark-fantasy/layouts/app.blade.php` |
| `themes/muonline/news/index.blade.php` | `themes/dark-fantasy/news/index.blade.php` |
| `themes/muonline/components/item-tooltip.blade.php` | `themes/dark-fantasy/components/item-tooltip.blade.php` |

When an override is absent, Laravel renders the matching file from the physical
`muonline` theme. If a selected custom directory is removed, the application
also falls back to `muonline` without changing the stored setting.

The active metadata is available to public Blade views as `$activeTheme` with
the `slug`, `name` and `views_path` keys.

## Themeable and core views

Themes may override the public home, rankings, content, profiles,
authentication, account panel, Market, Shop and Votes views, the public layout,
and public Blade components. The default tree is the authoritative list of
supported relative view names.

The following remain application-owned core views and cannot be overridden by
a public theme:

- `resources/views/admin/**`, including administrator authentication;
- `resources/views/mail/**` and `resources/views/vendor/mail/**`;
- `resources/views/errors/**`;
- administrative Blade components;
- framework pagination templates.

Keep route names, form methods, CSRF fields, authorization behavior and the
expected Blade variables compatible with the default view contract.

## Assets

A theme may continue using the main Vite bundle from the default layout. It may
also place versioned, publicly deployable files under `public/themes/<slug>/`
and reference them with Laravel's `asset()` helper.

Tailwind CSS v4 scans `resources/views` recursively through the existing
`@source '../views'` declaration, which includes every
`resources/views/themes/**/*.blade.php` file in production builds. Themes do
not add another frontend framework or application bundle by default.

## Creating a theme

1. Create `resources/views/themes/<slug>`.
2. Copy only the default Blade files that need a visual change.
3. Preserve the default view contracts and bilingual/accessibility behavior.
4. Select the discovered theme under **Admin → Website identity**.
5. Test public, authentication and account routes in both locales and at each
   supported responsive breakpoint.

Example:

```bash
mkdir -p resources/views/themes/dark-fantasy/layouts
cp resources/views/themes/muonline/home.blade.php \
    resources/views/themes/dark-fantasy/home.blade.php
cp resources/views/themes/muonline/layouts/app.blade.php \
    resources/views/themes/dark-fantasy/layouts/app.blade.php
```

## Update and production rules

- Do not put controllers, models, migrations or application services in a
  theme.
- Do not copy the full default tree unless the design genuinely replaces every
  view.
- After an application update, compare each custom override with its current
  `themes/muonline` counterpart and reconcile contract changes.
- Commit commissioned themes and versioned assets so deployments and rollbacks
  remain reproducible.
- Never move admin, mail or error rendering into a public theme.

After deploying, adding or removing a theme, rebuild assets and refresh cached
configuration and views:

```bash
npm run build
php artisan optimize:clear
php artisan optimize
```

With Docker, prefix Artisan commands with `./docker/compose exec -T app`.
