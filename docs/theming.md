# Theming

The `drubricks` theme is the UI kit inside Drupal. Colours, sizes and fonts are kit **tokens** (CSS custom
properties in `kit/ui.css`); Tailwind utilities map to them (`bg-surface`, `text-ink-2`, `border-line`, `bg-accent`).
Never hardcode colours: use tokens, and token names (`'teal'`) in data.

## Palette and dark mode

- Palette: theme setting `default_palette` ("Colour palette" in Appearance → Settings → Drubricks, default
  `cobalt`), printed as `<html data-default-palette>`; the kit applies it before the first paint. Palettes:
  `cobalt`, `lagoon`, `sage`, `berry`, `indigo`. A site setting: users do not pick palettes.
- Colour scheme: Light / Dark / Auto in the account menu (bottom of the sidebar); Auto (the default) follows the
  system.
- Logo and site name in the sidebar: the theme settings' logo and "Toggle display" (default: the theme's `logo.svg`).

```bash
ddev drush config:set drubricks.settings default_palette lagoon -y
```

## A client sub-theme

```yaml
# web/themes/custom/acme/acme.info.yml
name: ACME
type: theme
base theme: drubricks
core_version_requirement: ^12
libraries:
  - acme/brand
```

```yaml
# web/themes/custom/acme/acme.libraries.yml
brand:
  css:
    theme:
      css/brand.css: {}
```

```css
/* web/themes/custom/acme/css/brand.css — the brand wins over every palette:
   palette rules use :root[data-palette="…"], so match that specificity. */
:root, :root[data-palette] { --accent-rgb: 200 40 60; --bg-sidebar: #f7f3f2; }
:root[data-scheme="dark"], :root[data-palette][data-scheme="dark"] { --accent-rgb: 240 110 120; --bg-sidebar: #1c1314; }
```

If the sub-theme's templates use Tailwind classes that the parent theme does not, give it its own build
(copy `web/themes/custom/drubricks/package.json` and `src/app.css`, pointing `@source` at the sub-theme). New kit
palettes and components go into the theme's `kit/` (and an SDC component in `components/`), then `npm run build`.

## CSS build

```bash
cd web/themes/custom/drubricks && npm install && npm run build
```

`src/app.css` imports Tailwind v4, the kit theme (`kit/tailwind-theme.css`) and components (`kit/ui.css`), and
scans the theme, `web/modules/custom` and `web/profiles/custom` for classes. The result `css/app.css` is committed, so
installs need no Node. Rebuild after adding Tailwind classes anywhere in custom code.

## Layers

The page uses CSS cascade layers `theme, base, components, utilities` (declared by `drubricks_core/css/layers.css`,
because module CSS loads before theme CSS). Put component CSS in `@layer components` so utilities can override it.
CSS that must beat unlayered core CSS (e.g. jQuery UI autocomplete) stays unlayered.
