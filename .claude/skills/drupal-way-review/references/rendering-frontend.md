# Rendering and front end

Templates, theme hooks, SDC components, HTMX, icons, JavaScript behaviors, client-side messages, pagers, theme
settings. Read for any change to templates, render arrays with markup, the theme, `js/` files, HTMX links or
responses, or icons.

## Checklist

- Markup in templates / SDC components / theme hooks, not PHP strings or `inline_template`; templates registered
  as theme hooks, not included by path.
- HTMX only through core's `Htmx` API and `Swap` — never hand-written `hx-*` attributes or HX-* headers.
- Icons through core's Icon API (`icon(pack, id)`, packs in `*.icons.yml` with core extractors); no own icon
  helpers, no extractor plugins (the extractor API is `@internal`).
- JS as `Drupal.behaviors` + `once()`, declared libraries (load order through dependencies); behavior names, once
  ids and marker attributes unique across modules and the theme; content inserted by hand gets
  `Drupal.attachBehaviors()`; messages through `Drupal.Message` / `Drupal.theme.message`.
- Theme hooks as `src/Hook` classes (like `default_admin`), not a `.theme` file; theme settings via
  `form_system_theme_settings_alter` + schema.

## Cases

**HTMX headers and attributes**
- Don't: build `HX-Location` / `HX-Trigger` / `HX-Redirect` JSON by hand, set `data-hx-sync` / `data-hx-trigger`.
- Do: `Htmx::locationHeader(new HtmxLocationResponseData(...))`, `triggerHeader()`, `redirectHeader()`, `sync()`,
  `trigger()`, `pushUrl()`, `replaceUrl()`; detect requests with `HtmxRequestInfoTrait`.
- Core: `core/lib/Drupal/Core/Htmx/`. HTMX 4 pushes HX-Location by default (`push` not needed).

**Client-side messages**
- Don't: scrape messages out of responses and call the kit's `UI.hud()` directly.
- Do: `new Drupal.Message().add(text, {type})`; the theme overrides `Drupal.theme.message` (kit markup, status →
  HUD); on app screens the messages wrapper is a fixed layer (not a grid child of `.app`).
- Core: `core/misc/message.js`, `default_admin/js/messages.js`.

**Theme hooks and settings**
- Don't: procedural `drubricks.theme`; a palette only settable with `drush config:set`; a PHP list of route names
  to style auth pages.
- Do: `src/Hook/ThemeHooks.php` with DI; `#[Hook('form_system_theme_settings_alter')]` checking
  `config_key`; core's `logged_in` / `root_path` html variables in the template.
- Core: `default_admin/src/Hook/*`, `template_preprocess_html()`.

**Pager**
- Don't: `getPathInfo() . '?page={page}'` (no base path, breaks with several pagers).
- Do: links from `PagerManager::getUpdatedParameters()` + `Url::fromRoute($pager['#route_name'])`.
- Core: `Pager/PagerPreprocess.php`.

**Component JavaScript**
- Don't: register theme component scripts with the kit's own `UI.behavior()` / `UI.once()` and run them through a
  shell bridge.
- Do: `Drupal.behaviors.<name> = { attach(context) { once('<id>', selector, context)… } }`; content inserted by
  hand (a popup body) gets `Drupal.attachBehaviors(el, drupalSettings)`. Ordering through libraries: the shell
  depends on the kit and initialises it when it loads, the components' library depends on the shell.
- Teardown of kit widgets stays with `UI.detach()` on exactly the swapped element (see the HTMX detach note in
  [false-positives.md](false-positives.md)); listeners die with their element, so most behaviors need no `detach`.
- Core: `core/misc/drupal.js` (`attachBehaviors`), `core/once`, `core/misc/htmx/htmx-assets.js`.

**`Drupal.behaviors` and `once()` are one shared namespace**
- Don't: give two scripts the same behavior name or once id — the later behavior object replaces the earlier, and
  a shared once id makes the second script skip elements the first one marked. The SDC components `board` /
  `calendar` and drubricks_core's islands both used `drubricksBoard` / `drubricks-board` /
  `[data-drubricks-board]` (harmless only while one of them lived in the kit's separate `UI.once` registry).
- Do: names, once ids and marker attributes unique per script (islands: `drubricksBoardIsland`,
  `data-drubricks-board-island`); grep `Drupal.behaviors.<name>` and the once id before adding one.

**Icons**
- Don't: a Twig function of our own (`kit_icon()`) building `<svg><use>` as a string with its own `?v=` cache
  buster; a sprite pruned to the names in use, so every new app icon means rebuilding the theme.
- Do: an icon pack in `THEME.icons.yml` with core's `svg_sprite` extractor — `{{ icon('kit', 'i-name') }}`; cache
  busting by a content hash in the file name (`kit/icons.<hash>.svg`, found by the pattern `kit/icons.*.svg`);
  apps ship their own packs (`MODULE.icons.yml`, core `svg` extractor) and use `pack:icon` names.
- Don't write an icon **extractor plugin**: in Drupal 12 the extractor API (base classes *and*
  `IconExtractorInterface`) is `@internal` — the public extension point is a pack definition with core's
  extractors (`svg`, `svg_sprite`, `path`). phpstan flags extending internal classes; listen to it.
- Core: `Theme/Icon/*`, `Template/IconsTwigExtension.php`, `Render/Element/Icon.php`,
  `navigation/navigation.icons.yml`.

**Markup in PHP**
- Don't: `#type => inline_template` with HTML, `{% include '@module/x.html.twig' %}` by path.
- Do: theme hooks of the module with templates (overridable by themes).

**SDC props and NULL (3English test project)**
- Don't: pass a possibly NULL value to a component prop typed `string` — with the theme setting "logo" off the
  sidebar passed `brand.logo = NULL` and every page failed with `InvalidComponentException`.
- Do: `|default('')` where the value is optional (or allow `[string, 'null']` in the schema).
- Core: `Template/ComponentsTwigExtension::doValidateProps()`.

**Palettes of the project next to the kit's (3English test project)**
- Don't: add `:root[data-palette="orange"]` and an option "orange" only — the kit's JS (`ui.js`) knows a fixed
  list of palettes and resets `data-palette` to its default (lagoon) on load, so the colours flash and vanish.
- Do: keep the kit untouched; the theme's own CSS (`css/palettes.css` in the `global` library) defines the
  palette on its own attribute, which `ThemeHooks::preprocessHtml()` renders server-side for a non-kit palette
  (`data-theme-palette`); the option is in the theme settings form (palette names with the context
  'Colour palette').

**An optional pane that is always there (the inspector)**
- Don't: render a pane on every screen with an empty-state text ("Nothing selected") — it takes width on lists,
  boards and calendars where it has nothing to show; and don't refresh only one pane of a swap when another pane
  shows data of the same record (the inspector kept the previous record after a row click).
- Do: render the pane only with content (an empty placeholder with the same id keeps the HTMX swap target, without
  the kit class the layout keys on — `.app:has(> .inspector)`), hide its toggle with it, and swap dependent panes
  together with core's `Htmx::selectOob()` (`Swap::link()` for the details level). One control per action: the
  kit toggle replaced a second "Info" action that loaded the same pane.

**A table with filters, bulk actions and row actions in the app (table view)**
- Don't: build a custom table controller with its own filter form, checkboxes and action buttons, or a Views style
  plugin that re-implements what core's table style does.
- Do: a core View (table style, exposed filters, `<type>_bulk_form`, entity operations, full pager) shipped as config,
  embedded by a generic route (`ScreenHandler::table()`); the theme overrides only templates
  (`views-view-table`, `views-exposed-form`, `links--dropbutton`) and replaces core's dropbutton JS with
  `libraries-override` (the kit menu); the bulk count rides on core's tableselect via `libraries-extend`. App-only
  behaviour of operations (modals, `?open=`) in `hook_entity_operation_alter`, keyed on the route option, so admin
  views keep core's links. Commit: the table view task.

**Facts about core's Views markup (watch)**
- The sort indicator's `#style` in `views-view-table` is the order of the *next* click, not the current one
  (`ViewsThemeHooks::preprocessViewsViewTable()`): `aria-sort` is the opposite.
- Drupal 12's `views-view.html.twig` has no `.view-filters` / `.view-content` wrappers: style the exposed form
  (`form.views-exposed-form`) itself.
- A theme script must read `window.UI` when it runs, not when the file loads: an overridden core library keeps
  core's dependencies and may load before the kit.

**Behaviour the UI kit lacks (a draggable modal with a close button)**
- Don't: wrap or patch the kit's API from the outside (a wrapper in `drubricks_core/js/shell.js` that rebuilds
  `UI.modal`'s header), or build a second modal next to it.
- Do: the kit (`web/themes/custom/drubricks/kit/`) is the project's own code: add the option to the kit itself —
  `UI.modal({ closeButton, draggable })` in `kit/ui.js`, its styles in `kit/ui.css`, its text in the kit strings
  (`STR` + `kit/strings.json`, translated through Drupal with context "UI kit") — and use it where the app opens
  modals. (An earlier wrapper in shell.js was removed when the kit stopped being a copy of another repository.)

**One header for every kind of view (list, board, calendar, table)**
- Don't: copy the header markup into each view template (four copies drifted: a count missing on the table, the
  filter inline in the board header, two segmented controls side by side on the calendar, "add" taller than the
  view switch), or move a View's exposed form into the header with JS / CSS.
- Do: one partial (`drubricks-screen-header.html.twig`) embedded with blocks (`subtitle`, `filters`, `controls`),
  the "add" button and the count from one place (`ScreenViews::add()`, `ScreenViews::count()` — a
  `PluralTranslatableMarkup`, never `count ~ 'items'|t`), the second row as an SDC (`drubricks:pane-toolbar`). The
  table's filters go there through core's "exposed form in a block": `setOption('exposed_block', TRUE)` on the
  display and `viewExposedFormBlocks()` from the same View object (what `ViewsExposedFilterBlock` does) — the View
  then leaves the form out. Commit: the shared view header task.

**Kit CSS and overrides (watch)**
- The kit's rules are in `@layer components`, like the modules' and the theme's: an override competes by specificity
  (`.drubricks-calendar-body { padding-top }` lost to the kit's `.content .pane-body { padding }`): style an element
  the kit does not style, or change the kit itself.
- `kit/ui.css` is imported into the theme's `css/app.css`: after editing it run `npm run build` in the theme, or the
  page keeps the old rule.
- A kit component that allows links (`drubricks:seg` with `url`) needs kit CSS for `a` items too: `.seg button`
  left the view switch's links unpadded (`.seg > :is(button, a)`).
- An outline drawn as a box-shadow outside an element (`.cal-scroll`) is clipped by a scrolling parent when the
  element starts at its edge: leave 1px.

**Filters, sorting and paging of a View without a page load**
- Don't: turn on Views' "Use AJAX" (core's legacy Drupal.ajax next to the shell's HTMX), write `hx-*` into the
  Views templates, or rebuild the links in JS.
- Do: the region holding the View is boosted (`Swap::region()`: `hx-boost` + inherited select / target / swap /
  push-url and the inspector out-of-band), the exposed form gets `Swap::filters()` (GET on submit and change, the
  form stays and keeps focus), forms and links that must not be boosted get `Swap::noBoost()` / come from
  `Swap::modal()`. Commit: the HTMX table filtering task.
- HTMX 4 inherits an attribute only with `:inherited`, and core's `Htmx::boost()` takes no modifiers: the region
  sets `data-hx-boost:inherited` itself (in `Swap`, the one place for HTMX). An element's own `hx-boost="false"`
  (the bulk form) is not inherited: the sort links inside it stay boosted.
- A GET sent by core's HTMX script carries its state in the query string (`ajax_page_state`,
  `_triggering_element_*`, and `_wrapper_format` with "only main content"); pagers, `TableSort` and Views copy the
  query into their links. `HtmxSubscriber::onRequest()` takes the script's keys out of the query, like core's
  `ViewAjaxController` (core keeps the parsed state in a request attribute, `AjaxPageState` middleware); a region
  with such links loads the full page (`_wrapper_format` must stay in the query until the response is rendered).

**Fixed panels of the kit next to core's Navigation (admin toolbar)**
- Symptom: in a compact window the app sidebar slid out under the admin toolbar (`left: 8px` from the window, toolbar
  `z-index` 501) and could not be clicked.
- Don't: raise the panel's `z-index` over core's toolbar, or override each fixed panel's position in the Drupal CSS.
- Do: the kit takes the room the host page keeps around the app as `--shell-top/-right/-bottom/-left` (`.app` and its
  slide-over sidebar and inspector); `src/drupal.css` sets them from core's `--drupal-displace-offset-*`
  (`Drupal.displace()`). e2e: `navigation.mjs` "compact window".

**400 for the `locale/translations` JS aggregate (Drupal 12 beta)**
- Symptom: a console error "Refused to execute script … MIME type" and a 400 for
  `js_<hash>.js?libraries=<locale/translations>`, once the site language has a JS translation file; translated JS
  strings are missing.
- Cause: a library alone in its group gets its own `?libraries=` aggregate. That request has no other JS files, so
  `LocaleJs::jsTranslate([], …)` returns NULL, `LocaleThemeAlterHooks::jsAlter()` removes the placeholder, and
  `AssetControllerBase::getGroup()` finds no group. Core tracks the locale side in drupal.org #3232810.
- Don't: patch core, ignore the error in the e2e checks, or turn JS aggregation off for the site.
- Do: `hook_library_info_alter()` sets `preprocess: false` on `locale/translations` (`DrubricksCoreHooks`): the page
  links the per-language file directly. Kernel test: `LocaleJsAssetTest`. Remove the alter when core fixes it.

**Licences of bundled third-party assets (fonts, icons)**
- Don't: ship third-party fonts / icons with only a comment naming the licence (OFL and ISC require the licence text
  and copyright notice with every copy).
- Do: the licence text next to the files (`kit/fonts/OFL.txt`), `THIRD-PARTY-NOTICES.md` in the root, and the
  `license:` key (SPDX name, url, `gpl-compatible`) on the library (`drubricks.libraries.yml`: `kit-fonts`) and the
  icon pack (`*.icons.yml`) — the metadata core reads for its own vendored assets.
- Core: `core.libraries.yml` (`license:` of ckeditor5, jquery …), `LibraryDiscoveryParser` (default GPL licence),
  `IconPackManager` (icon pack `license:`), `navigation/navigation.icons.yml`.

**Charts (contrib Charts, Chart.js) in the kit look**
- Don't: a chart library of our own, chart JS per screen, hex colours in the chart settings or code, a CDN.
- Do: Views with the "Chart" format (or `#type chart` in code); the kit look in one place — `hook_chart_alter()`
  (library, accessible table) and `hook_chart_definition_alter()` (Charts' default palettes → kit tokens, no fixed
  title / tick / grid colours, set height → `maintainAspectRatio: false`), a global Chart.js plugin that resolves
  tokens from CSS variables and repaints on `data-scheme` / `data-palette` changes (`config.clearCache()` + a full
  `update()`, or bars keep their old colour). Chart.js from Composer (asset-packagist, `web/libraries`), CDN off.
- Core / contrib: `charts.api.php` (`hook_chart_alter`, `hook_chart_definition_alter`), `Chartjs::preRender()`.
  drubricks_core `ChartHooks`, `js/charts.js`.
