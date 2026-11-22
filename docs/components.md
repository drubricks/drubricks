# Components: the UI kit as Drupal SDC

The drubricks theme wraps every component of its UI kit (`kit/ui.css`,
`kit/ui.js`) in a **Single Directory Component** (SDC). Module templates and
render arrays use these components instead of copying kit markup, so a kit
change is made in one place.

- Components: `web/themes/custom/drubricks/components/<name>/`
- Live documentation: enable `drubricks_design_system` and open
  **Design system → Components** (`/apps/design-system`). Every component is
  shown with live examples, the Twig source of each example and the rendered
  HTML.
- Drupal form elements get the kit look from `templates/form/` (see below).

## How a component maps to the kit

One directory per kit component (or per part of one, e.g. `tabs` + `tab-panel`):

| File | What it is |
|---|---|
| `<name>.component.yml` | Name, group (= the section of the design-system page), description, **props** (JSON schema) and **slots**. |
| `<name>.twig` | The kit markup. Props fill texts and modifier classes; slots take markup. |
| `<name>.js` | Only for components with behaviour of their own (see JavaScript components). Drupal attaches it with the component. |

Rules the components follow:

- **Props mirror the kit modifiers**: `.btn.primary.small` is
  `{label: 'Save', variant: 'primary', size: 'small'}`, `.icon-btn.on` is
  `{on: true}`, `.alert.warning` is `{type: 'warning'}`, `.pill` with
  `--c: var(--green)` is `{color: 'green'}`.
- **Colours are token names** (`teal`, `red`, …), rendered with `color('teal')`
  → `var(--teal)`. Never hex values.
- **Text props are escaped** by Twig. Markup goes into a **slot** (e.g. the
  `text_markup` slot of `comment` for mentions, `content` of `alert`).
- **Every component takes `attributes`** (a Drupal `Attribute`): extra classes
  (Tailwind utilities), `data-*`, `aria-*`, ids.
- **Accessibility** as in the kit's `AGENTS.md`: buttons are `<button>` (or
  `<a>` when a `url` is given), icon-only buttons require `title` (or `tip`),
  form controls get labels or `aria_label`, live alerts take `role`.
- **Icons** use Drupal core's Icon API: the theme's `kit` pack
  (`drubricks.icons.yml`, core's `svg_sprite` extractor on `kit/icons.<hash>.svg`)
  — `{{ icon('kit', 'i-name', {class: 'extra classes'}) }}`; icon IDs are the
  sprite's symbol IDs, `i-<name>`. Component props named `icon` take the bare
  kit name (`'checkmark'`).

### Components by kit section

| Kit section | Components |
|---|---|
| App shell | `pane` |
| Sidebar | `sidebar-section`, `sidebar-item`, `sidebar-account`, `brand` |
| Toolbar | `toolbar`, `pane-toolbar` (the second header row: filters, view controls), `tb-group`, `tb-btn`, `search` |
| Buttons & controls | `button`, `icon-button`, `kbd`, `seg`*, `switch`, `chip`, `avatar-toggles`, `check`* |
| Forms | `form-layout`, `form-row`, `form-item`, `field`, `select`, `textarea`, `checkbox`, `radio`, `fieldset`, `password`* (+ strength meter) |
| List rows | `row`, `list-section`, `empty-state` |
| Grouped lists | `group`, `group-row`, `group-title` |
| Avatars, tags & badges | `avatar`, `avatar-stack`, `badge`, `tag`, `pill`, `tile`, `priority`, `points`, `progress`, `empty-line` |
| Tabs | `tabs`, `tab-panel` |
| Data table & pager | `data-table`*, `pager` |
| Alerts | `alert`* |
| Skeleton & spinner | `spinner`, `skeleton` |
| Comments & feed | `thread`, `comment`, `feed`, `feed-item` |
| Month grid & date picker | `month-grid`*, `date-picker`* |
| Week calendar | `calendar`* |
| Board | `board`* |
| Dashboard | `greeting`, `stats`, `stat`, `panels`, `panel`, `p-row`, `bar-row` |
| Detail view | `detail-header`, `minis`, `mini`, `composer`; inspector label: `toolbar` with `kind`; inspector empty state: `empty-state` with `icon` |
| Menus & popovers | `menu-button`*, `context-menu`*, `popover-button`* |
| Popups & HUD | `modal-button`*, `hud-button`* |
| Tooltip | `tooltip` (and the `tip` prop of `button`, `icon-button`, `tb-btn`, `pill`, `avatar`) |

\* has a `<name>.js` behaviour.

Not mapped, on purpose: the kit's *Foundations* (colours, typography, icons —
tokens and core's `icon()` cover them), *Fragments*, *Routing*,
*Internationalisation*, *Helpers*, *JavaScript API* and *State classes* are
JavaScript APIs or reference, not markup. Text helpers (`.dim`, `.link`,
`.overdue`, `<mark>`, `.mention`) are plain classes — use them directly.

## Using a component

### In a Twig template

```twig
{# Props only: include. "only" keeps the caller's variables (e.g. its attributes) out. #}
{% include 'drubricks:button' with {label: 'Save'|t, variant: 'primary', icon: 'checkmark'} only %}

{# As a function, e.g. inside {% set %} or a table cell: #}
{% set stage = include('drubricks:pill', {label: deal.stage, color: deal.color}, with_context = false) %}

{# Slots: embed and fill the blocks. #}
{% embed 'drubricks:alert' with {type: 'warning', title: 'Two documents are missing'|t} only %}
  {% block content %}{{ 'Upload them before <b>Friday</b>.'|t }}{% endblock %}
  {% block actions %}{% include 'drubricks:button' with {label: 'Upload'|t, size: 'small', variant: 'primary'} only %}{% endblock %}
{% endembed %}

{# Extra classes / attributes: #}
{% include 'drubricks:button' with {label: 'Wide', attributes: create_attribute({'class': ['w-full'], 'data-x': '1'})} only %}
```

Always pass `only` (or `with_context = false`): without it the caller's
`attributes` variable leaks into the component. Don't pass `null` for a typed
prop (`{initials: null}` fails validation in development); leave it out or use
`|default('')` / `|filter(v => v is not null)`.

### In PHP (render arrays)

```php
$build['save'] = [
  '#type' => 'component',
  '#component' => 'drubricks:button',
  '#props' => ['label' => $this->t('Save'), 'variant' => 'primary'],
];
$build['notice'] = [
  '#type' => 'component',
  '#component' => 'drubricks:alert',
  '#props' => ['type' => 'info', 'title' => $this->t('Heads up')],
  '#slots' => ['content' => ['#markup' => $this->t('Any render array.')]],
];
```

Props are validated against the schema when assertions are on (DDEV): a wrong
type or enum value throws an exception naming the component and the prop.

## Drupal form elements → kit look

`web/themes/custom/drubricks/templates/form/` overrides core's form templates
for **every** form rendered with the drubricks theme; `src/forms.css` does the
layout. No classes are needed in form arrays.

| Drupal | Kit |
|---|---|
| `<form>` | `.form` stacked layout (labels above, 12px rhythm, max 640px). Opt out: `$form['#attributes']['data-drubricks-form'] = 'plain'` (or `form--inline` / `container-inline`). |
| textfield, email, password, number, date, search, tel, url, select, textarea | `.field` (`.invalid` + `aria-invalid` with an error) |
| checkbox / radio | `.checkbox` / `.radio`, label next to it; a checkbox with `#attributes['class'][] = 'switch'` is a kit switch |
| submit / button | `.btn`; `#button_type: primary` → `.primary`, `danger` → `.destructive` |
| label, `#required`, `#description`, errors (inline_form_errors) | kit label, red `*`, `.field-hint`, `.field-error` under the field |
| fieldset, radios, checkboxes | small legend over the options |
| details | a kit `.group` with a summary row and chevron |
| datetime | a form item (no `<h4>`), date and time side by side |
| password_confirm | the strength meter and messages in kit colours |
| container with class `form-row` | two columns |

The design system page has a Form API form with all of these (*Drupal forms*).

## JavaScript components

The kit's own behaviour (tabs, tooltips, rows, sidebar, search clear, account
menu, theme buttons) is delegated from `document` and needs nothing. Components
that mount a kit widget or listen to their own element have a `<name>.js` with a
regular Drupal behavior (`Drupal.behaviors` + core's `once()`):

```js
((Drupal, once, UI, Kit) => {
  if (!UI || !Kit) return;
  Drupal.behaviors.drubricksBoard = {
    attach(context) {
      once('drubricks-board', '[data-drubricks-board]', context).forEach((root) => {
        const o = Kit.options(root);              // JSON props from data-options
        UI.board(root.querySelector('[data-board]'), { … });
      });
    },
  };
})(Drupal, once, window.UI, window.DrubricksKit);
```

- `drubricks_core`'s shell (`js/shell.js`) initialises the kit (`UI.init()`) as
  soon as it loads; `drubricks/kit-components` depends on it, so component
  behaviors always find the kit ready — on the first page and on every
  HTMX-swapped fragment (core attaches behaviors after the swap).
- Teardown: a kit widget (board, calendar, date picker, context menu) registers
  its `destroy()` with `UI.onDetach`; the shell runs `UI.detach()` on exactly the
  element an HTMX swap replaces (core's `htmx:drupal:unload` passes the *parent*
  of that element to `Drupal.detachBehaviors()`, which would also tear down
  siblings that stay). Listeners on the element go away with it, so most
  component behaviors need no `detach`.
- Content you insert yourself (e.g. a popup body): `Drupal.attachBehaviors(el,
  drupalSettings)` — it runs the kit and the component behaviors there.
- Options travel as JSON in `data-options`, written by Twig (escaped).
- Shared helpers: `js/kit-components.js` (`window.DrubricksKit.options()`,
  `.emit()`, `.menuItems()`); a component's library depends on it with
  `libraryOverrides: dependencies: [drubricks/kit-components]` in its YAML.
- Components report what happened with **cancellable DOM events** that bubble:

| Event | From | `detail` |
|---|---|---|
| `drubricks:menu` | menu-button, context-menu | `{ value, label }` (cancel = skip the item's `url` / `hud`) |
| `drubricks:modal` | modal-button | `{ value }` (action value, `true`/`false` for a confirm, `null` for Esc) |
| `drubricks:undo` | hud-button | `{}` |
| `drubricks:date` | date-picker, month-grid | `{ date }` (cancel = keep the old day) |
| `drubricks:calendar` | calendar | `{ type: open \| create \| move \| resize \| date \| resource, id, range }` |
| `drubricks:board` | board | `{ type: open \| move \| create, id, lane, index, fromLane, title }` |
| `drubricks:seg` | seg | `{ value }` |
| `drubricks:check` | check | `{ checked, value }` |

Saving a board move on the server from a module behaviour:

```js
document.addEventListener('drubricks:board', (e) => {
  if (e.detail.type !== 'move' || !e.target.closest('#my-board')) return;
  fetch(`/api/tasks/${e.detail.id}`, { method: 'PATCH', body: JSON.stringify({ lane: e.detail.lane }) })
    .catch(() => { e.detail.board.update(previousItems); UI.hud(Drupal.t('Not saved')); });
});
```

Call `e.preventDefault()` in a listener to reject a change (the card / event
snaps back); editable components otherwise apply it locally.

A Drupal form in a popup: `modal-button` with `form_url` (drubricks_core loads
the form with HTMX; validation errors re-render it inside the popup, a save
closes it).

## Adding a component for a new kit component

1. **Write the markup and CSS** in the kit (`kit/ui.css`, behaviour in `kit/ui.js` when it needs any), with the kit's
   tokens, in light and dark.
2. **Create a Single Directory Component** like any Drupal SDC (see core's SDC documentation and the existing
   components): `web/themes/custom/drubricks/components/<name>/<name>.component.yml` (`$schema`, `name`, `props`,
   `slots`) and `<name>.twig` with the kit markup — icons as `icon('kit', 'i-…')`, colours as `color()`, the caller's
   `attributes` on the root element, kit modifiers as an `enum` prop.
3. **Edit**: one prop per text or state, `enum` for modifiers, slots for free
   markup, loops for repeated markup. Quote YAML titles and descriptions that
   contain `:` or `,` inside `{ … }` — invalid YAML in a component breaks every
   page. Check: `ddev drush cr` and open a page.
4. **Behaviour** (only if the kit needs JavaScript for it): add `<name>.js` as
   above and `libraryOverrides` for its dependencies.
5. **Document it** in the design system module: add a Twig example in
   `web/modules/custom/drubricks_design_system/templates/examples/<section>/<name>.twig`
   and list it (and the component name) in `sections.yml`. The page shows the
   file's source as the snippet, so write it the way a developer should copy it.
6. **Rebuild** (below) and run `node tests/e2e/design-system.mjs` — it fails
   when a component is not rendered in any example.

## Tailwind classes and rebuilds

- The theme CSS is built by the Tailwind v4 CLI from `src/app.css`, which scans
  the theme templates, `components/`, `js/` and **all of `web/modules/custom`**
  (templates, PHP, YAML, examples). Utilities used in a module template or a
  render array therefore work after a rebuild:

  ```sh
  cd web/themes/custom/drubricks && npm run build   # → css/app.css (committed)
  ```

- **Icons of an app.** Either a kit / Lucide name that is in the kit sprite, or
  the app's **own icon pack** — no theme change: `MODULE.icons.yml` with core's
  `svg` (or `svg_sprite`) extractor and the module's SVG files (example:
  `drubricks_core_test.icons.yml`), then `pack:icon` wherever a name is
  expected — sidebar items, toolbar actions, dashboard panels
  (`icon: 'my_app:handshake'`) — or `icon('my_app', 'handshake')` in the app's
  templates. A new name in the theme's sprite: add a `<symbol id="i-<name>">` to
  `kit/icons.<hash>.svg` (e.g. the SVG paths of a Lucide icon, ISC licence) and
  rename the file to a new hash, so its URL changes with its content
  (`drubricks.icons.yml`); then `ddev drush cr`.
- **Tailwind utilities in an app.** The theme CSS is one build for the project and
  scans every custom module, so new utility classes in an app's templates need the
  theme CSS rebuilt (`npm run build`, commit `css/app.css`). Prefer kit classes and
  the SDC components; CSS only an app needs can be a library of the module (kit
  tokens: `var(--teal)`, `var(--label-2)`, …).
- After adding or changing a component: `ddev drush cr` (SDC discovery and
  Twig cache).

## Charts

Charts are not a theme component: they are the contrib [Charts](https://www.drupal.org/project/charts) module with
its Chart.js library (installed by the base recipe; the JS files are in `web/libraries`, from Composer — no CDN).
Build a chart in the Views UI (format "Chart", usually with aggregation; the "<date> (period)" field groups by year,
month or day) or in code as a render array of `#type chart`. drubricks_core (`ChartHooks`, `js/charts.js`) gives
every Chart.js chart the kit look:

- colours: Charts' default colours (its palettes, by position) become kit colours; a series `#color` (and the
  `#colors` of a pie / donut) may be a kit token name (`'teal'`) — a hex colour picked in the chart settings stays;
- the kit font, text and line colours, the tooltip; light / dark and palette changes repaint the charts;
- a visually hidden data table under the chart for screen readers (Charts' "accessible table");
- a height set in the chart settings (dimensions) is the chart's height.

Examples: the design system page (section Charts), the dashboard blocks of the examples, CRM → Revenue (the chart
view of deals with filters, `ScreenHandler::chart()`).

## The design system module

`drubricks_design_system` (optional, `ddev drush en drubricks_design_system`):

- Route `/apps/design-system` (`_drubricks_screen: TRUE`), permission
  `access drubricks design system` (granted to authenticated users on install),
  sidebar section *Design system* (weight 90) with *Components*.
- `sections.yml`: the sections in their order, their lead text,
  the components they document and their examples. `templates/examples/**.twig`:
  one file per example — rendered as the preview, shown as the Twig snippet.
- The page: a table-of-contents column (deep links `#section`, scroll spy,
  filter), the docs in the content pane (light / dark / auto switch), a sample
  inspector, a Form API demo form and a reference table of every component
  with its props and slots.
- Check: `node tests/e2e/design-system.mjs`.
