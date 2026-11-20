# Drubricks — notes for AI agents (Claude Code and similar)

Drubricks is a starter for **business applications on Drupal 12**. A developer describes an app in a prompt
("a CRM with companies, contacts and deals; deals have a stage and a value; …") and an agent builds it as a
**Drupal module** on top of `drubricks_core`, with the look of the UI kit. Follow this file for every such task.

## Golden rules

0. **Everything the Drupal way — don't invent.** Use what Drupal core (and established contrib) already has:
   entity API and handlers, plugins, hooks / event subscribers, Views (built in the Views UI and shipped as config
   in the module's `config/install`), core actions, recipes, config. If you don't know how Drupal does something,
   find out first: look at how core does it in `web/core` (modules, `core/recipes`, tests) and research drupal.org /
   api.drupal.org / change records — then follow that. **No helper scripts, generators or standalone PHP files**
   (no `scripts/*.php`, no code that writes config files): ship config and code in the module. No update hooks in
   the starter (reinstall instead).
1. **Copy the examples, don't invent.** New apps are modelled on the example modules:
   - `web/modules/custom/drubricks_examples_crm` — the reference app: entities, screens, sidebar, validation.
   - `web/modules/custom/drubricks_examples_project_management` — a second independent app (sidebar section with
     its own weight, an account-menu link, a board view);
   - `web/modules/custom/drubricks_examples_booking` — a calendar view as the main view, a rule across records (no
     double booking, opening hours) as an entity constraint.
   Start by reading the CRM example end to end; reuse its structure, naming and patterns.
2. **The system is the project's own module(s)** under `web/modules/custom/<prefix>_<name>/` (the project's prefix,
   not `drubricks_`). A small system is one module; a larger one (an ERP: customers, sales, purchasing, inventory,
   HR …) is **several modules working together**, split by domain — e.g. `<prefix>_base` with the shared records
   (customers, products) and `<prefix>_sales`, `<prefix>_inventory` on top of it:
   - each entity type belongs to one module; the others reference it (`entity_reference`) and declare the module
     in `dependencies:` of their `*.info.yml` — dependencies go one way, no cycles;
   - a module extends another's records the Drupal way — hooks / event subscribers, its own fields or entities,
     constraints added in `hook_entity_type_alter` / `hook_entity_base_field_info`, menu links under the other's
     sidebar section (`parent:`) — so each module can be switched on or left out on its own;
   - each module has its permissions, tests and translations; recipes: an App + Demo pair per module that can be
     installed on its own (a module's App recipe includes the recipes of the modules it needs), or one App recipe
     for the whole system when its parts never go apart.
   Never edit `drubricks_core` or the theme to add an app — apps plug in with entity handlers and plugins. If the
   core really lacks something, change the core in a separate, general way.
   In a project started from Drubricks (`starter_source` in the `*.info.yml`, see `docs/client-projects.md`) the
   starter code is the project's own code — no `git pull` from the starter; the machine names (`drubricks`,
   `drubricks_core`) stay, because modules, templates and config use the theme's `drubricks:<component>`.
3. **Data = content entities** extending `Drupal\drubricks_core\Entity\DrubricksEntityBase` (one entity type per
   domain object). No nodes, no taxonomy for app data.
4. **The UI kit is the design system.** Markup uses kit classes / SDC components of the `drubricks` theme
   (`docs/components.md`, live catalogue: module `drubricks_design_system` → `/apps/design-system`). Colours are
   kit tokens (`'teal'`, `var(--teal)`), never hex. Accessibility rules of the kit apply.
5. **Run `scripts/check.sh` (phpcs, phpstan, PHPUnit) before every commit**; it must pass. Add kernel tests for
   entities, access and validation, and an e2e check (`tests/e2e/`) for the user path.
6. **Review every task with the `drupal-way-review` skill** (`.claude/skills/drupal-way-review/`) before the last
   commit, and **add what the task taught to its catalogue** — new violations with their Drupal-way fix (core
   pointer, commit) in the matching `references/<topic>.md`, checked false positives in
   `references/false-positives.md` (index of the topic files in its `SKILL.md`). The catalogue grows with the project.

## A new system from a prompt (the usual start)

The usual request: *"Based on Drubricks (<repository URL>) build me a system to manage the teachers and clients of a
language school"* — or a car workshop, a bakery making cookies with customers' logos, … Then:

1. **Ask first: 3–7 questions in one message**, only those whose answers change what you build, e.g.:
   - who uses it (roles) and who may see / edit what (all records, own records, e.g. a teacher only their groups);
   - the main records, how they relate, the fields that matter (choice lists, statuses and their order);
   - the main views: lists, a board (work through statuses), a calendar (bookings, schedules), dashboard numbers;
   - rules the system must enforce (no double booking, limits, approvals, required fields);
   - UI language, currency, the system's name, logo and colour palette;
   - demo data, or existing data to import later.
   Then sum up the plan in a few lines (entities, screens, roles, rules) and build it — no further round of questions
   unless something blocks you.
2. **Start the project** from the repository URL the user gave (it may change; else the one in `README.md`):
   `git clone <url> <project-dir>`, `cd <project-dir>`, `./launch-drubricks.sh --own-project --apps=` — the code
   becomes the project's own (`starter_source`, a new git repository), **base only: no example app installed**.
3. **The example apps are references, not features.** Read `drubricks_examples_crm` (and the project management /
   booking examples) to copy their structure and patterns; **never enable them or apply their recipes on the
   project's site**, and build only what the user asked for (no CRM because the starter has one). They stay in the
   code as references (the functional tests install them on test sites); their e2e files test the examples, so on
   the project run your app's e2e (`npm run test:e2e -- tests/e2e/<app>.mjs`) and reinstall with
   `scripts/reinstall.sh -y --apps=<your_app>` (the default `all` would install the examples too).
4. **Build in stages — each one installed and checked before the next**, so errors show up early, not at the end:
   1. one entity at a time: the entity class with its fields, its screen, form, sidebar link and permissions →
      install it (`ddev drush en <module> -y` at first, then the reinstall below) → open it in the browser: list,
      details, add / edit in the modal (an invalid then a valid save), delete → a short kernel test → commit;
   2. the next entity the same way (its references to the earlier ones included);
   3. then the rules (constraints), access per role (check as each role), the extra views (board, calendar), the
      dashboard blocks, the admin views / Field UI pages, the recipes with demo data, translations and branding —
      each a stage of its own, reinstalled and checked, committed.
   Never write the whole system first and test it at the end.
   A deployment is the project's own module(s) with the project's prefix (`web/modules/custom/<prefix>_<name>`;
   one for a small system, several cooperating ones for a larger one — golden rule 2), each built step by step as
   below, with App and Demo recipes (the starter code changes only when something general is missing). In a
   multi-module system build the module the others depend on first. The base install has no entities, screens or
   views of the project: once a module is written, **reinstall with it** —
   `scripts/reinstall.sh -y --apps=<prefix>_<name>[,<prefix>_<other>…]` (the App recipes, comma-separated) —
   and check the result: the sidebar section, the screens, the dashboard blocks, and in the admin sidebar
   **Application** → Entity records (the admin views) / Entity structure (Field UI) / Settings.
   Branding is settings: the site name, logo and colour palette (Appearance → Settings → Drubricks; set them in the
   App recipe's config actions when the user gave them). A palette of the project's own colours goes into the
   theme's `css/palettes.css` (an "Orange" example is there). The login pages (Gin Login) have their own logo and
   brand image (`gin_login.settings`) and the admin theme its accent colour (`default_admin.settings`) — set them
   in the App recipe too, or the login page keeps the Drubricks look.
5. **Finish** like every task: `scripts/check.sh`, the app's e2e, the `drupal-way-review` skill, a commit in the
   project's repository.

## Building an app from a prompt — step by step

Turn the prompt into: entities (fields, relations, rules), screens (list / details / inspector, extra views),
forms, sidebar entries, permissions, demo data — and, for a larger system, which module owns which of them (golden
rule 2). Then, for each module:

1. **Module skeleton** (copy from the CRM example): `<module>.info.yml` with **`package: Drubricks`** (the project's
   modules are listed with the starter's under "Drubricks" in Extend — the examples use "Drubricks examples"),
   dependencies `drubricks_core:drubricks_core` + field modules you use (`drupal:datetime`, `drupal:options`,
   `drupal:link`, …), and `<module>.permissions.yml` (an admin permission).
2. **Entities** in `src/Entity/<Name>.php`: `#[ContentEntityType(...)]` exactly like `Contact.php`:
   - entity keys `id, uuid, label => name, owner => uid, published => status, revision => revision_id` (the base
     class provides name, owner, status, colour, created, changed and the revision log fields);
   - **revisions — the recommended configuration for every entity** (all examples use it): `revision_table:
     '<base_table>_revision'`, the `revision` entity key, `revision_metadata_keys` (`revision_user`,
     `revision_created`, `revision_log_message`) and `->setRevisionable(TRUE)` on every field you add. The base class
     then saves each change of a record (form, board / calendar move, API) as a new revision by the current user,
     so earlier versions are never lost; a save without changes adds none. Kernel-test it with
     `RevisionsTestTrait` (`assertRevisionable()`, `assertEditCreatesRevision()`, see `CrmTest::testRevisions()`).
   - **no update hooks in the starter** (`drubricks_core`, theme, examples, installer — no `hook_update_N`, no
     post-updates): after changing entities or fields reinstall (`scripts/reinstall.sh -y`, drops the database; the
     demo data comes back with the apps). Update hooks start only in a concrete app built on the starter once it runs
     with real data;
   - handlers: `access` → `DrubricksAccessControlHandler`, `views_data` → `DrubricksViewsData`,
     `drubricks_screen` → your `ScreenHandler` subclass, `form` add/edit → `DrubricksEntityForm`,
     delete → `DrubricksDeleteForm`, `route_provider.html` → `DrubricksRouteProvider`;
   - links: `collection`, `add-form`, `canonical`, `edit-form`, `delete-form`, `drubricks-inspector` under
     `/apps/<app>/<things>` (see the CRM entities);
   - fields in `baseFieldDefinitions()` with form and view display options (`setDisplayConfigurable(…, TRUE)`).
     Relations: `entity_reference` with `target_type`. Choice lists: `list_string` with an
     `allowed_values_function`. Money: `decimal`. Dates: `datetime` (`datetime_type: date` for days).
   - **Fields in the admin UI (Field UI)** — every entity: `field_ui_base_route: 'entity.<type>.settings'` and that
     route in `<module>.routing.yml` (path `/admin/app/structure/<app>/<things>`, controller
     `EntitySettingsController::page`, default `entity_type_id`, the admin permission, `_admin_route`; copy the CRM
     entries — not a link template: core's Navigation would take its path for a record page). drubricks_core links it
     under Application → Entity structure (records stay under Entity records) and adds its "Settings" tab; Field UI
     adds Manage fields / form display / display (and "Create a new field" in the admin top bar). Ship the default displays as config: `core.entity_form_display.<type>.<type>.default`
     and `core.entity_view_display.<type>.<type>.default` in `config/install` — save them once on the site (Manage
     form display / Manage display, or `getFormDisplay()` / `getViewDisplay()` → `save()`) and export them with
     `ddev drush config:get` (without `uuid` / `_core`). Base fields stay in code; fields added in the UI are config
     (export them into the module when the app needs them). Kernel test: `FieldUiTestTrait::assertFieldUi()`.
   - Permissions are generated: `create X`, `view|edit|delete own|any X` (own = `uid`).
   - **Record-level access (who sees which records)** — e.g. "users see only projects they are members of":
     follow `drubricks_examples_project_management` (`src/Access/`, `src/Hook/ProjectAccessHooks.php`), the Drupal
     way, never filtering in controllers or templates:
     1. put the rule in one place (`ProjectAccess`) and add the data it needs as a field (`members`);
     2. single records: a subclass of `DrubricksAccessControlHandler` that ANDs the rule with the own / any
        permissions and adds cache metadata (`cachePerUser()`, `addCacheableDependency()` of the records it read);
        an admin permission bypasses the rule;
     3. lists: `#[Hook('query_ENTITY_TYPE_access_alter')]` adding the same condition — core tags every
        `accessCheck(TRUE)` entity query and every View of the entity with `ENTITY_TYPE_access`, so lists, sidebar
        counters, boards, calendars, dashboard blocks, autocomplete and reference options follow automatically;
        reference fields then reject other records through core's `ValidReference` constraint;
     4. if a record's visibility depends on another entity, add that entity's list tag to `list_cache_tags` — along a
        chain (teacher → lesson → client) on every type whose save changes what others see;
     5. kernel test both paths (entity access and entity queries) like `ProjectAccessTest`.
     When others may see a record's **name** but not the record (other lecturers' names on a lesson), the handler
     sets `protected $viewLabelOperation = TRUE;` and answers `view label` itself — otherwise core turns
     `view label` into `view` and the names disappear.
     For roles that see everything without administering the app (an office, a manager), add a "bypass" permission
     of its own (like core's `bypass node access`) instead of handing them the admin permission.
3. **Rules / validation** as entity constraints (`src/Plugin/Validation/Constraint/`, see `DealClosedConstraint`)
   registered in the entity attribute `constraints: [...]`. Field-level rules: field settings / constraints.
   Errors appear inline in the form (also in modals) and come back as 422 JSON from API endpoints.
   - **Reference fields:** `options_select` for short lists, `entity_reference_autocomplete` for long ones
     (users, many records) — both work in the modals. **Dependent fields** (choices that depend on another
     field, e.g. Deal → Contact filtered by Company): subclass `DrubricksEntityForm` like
     `drubricks_examples_crm/src/Form/DealForm.php` (HTMX posts the form, only the dependent field re-renders).
4. **Screen** in `src/Screen/<Name>Screen.php` extends `Drupal\drubricks_core\Screen\ScreenHandler`; override only
   what differs:
   - `row()` — title, meta (right), subtitle, text, colour token, `section` (group heading in the list),
     `avatar` (a name: avatar instead of the dot), `pill` (`['label' => …, 'color' => token]` next to the title);
   - `sort()` / `query()` — order, filters;
   - `detail()` — the details pane (default: entity view display "full"). For a richer record, return a theme hook
     of your module (`templates/`, registered with `#[Hook('theme')]`) built from components — `detail-header`,
     `@drubricks_core/drubricks-facts.html.twig` (label / value rows with link, avatar, pill, priority, overdue),
     `minis`, `panel` + `p-row`, `stats`, `progress`, `feed` — and set `'#drubricks_header' => TRUE` so the pane
     drops its plain title (see `ContactScreen`, `ProjectScreen`, `BookingScreen`). Load related records with one
     query (`accessibleQuery()`, `preloadReferences()`), link them with `recordLink()`, never convey meaning by
     colour alone (pill text, "Overdue:" for screen readers). Override `panel()` with a compact variant (no header).
     **Follow Manage display:** ask `$this->shows($entity, 'field')` before placing a field (hidden there → hidden
     in the designed parts too), give facts a `'field' => 'name'` and pass them through `visibleFacts()`, and add
     `'#fields' => $this->displayFields($entity, [the fields you place])` printed as `{{ fields }}` below the facts —
     fields added in Manage fields (and other visible components) with their formatters and labels; it also carries
     the display's cache tags (see `ContactScreen`, `TaskScreen`, `ResourceScreen`);
   - `inspector()` — facts in the right sidebar (default: owner, created, changed, status); return `[]` for none.
     The right sidebar exists only for a shown record with facts: a list without a selection, a form, a board /
     calendar without an open card have none (full width), and it follows the record when another row is clicked;
   - `actions()` — toolbar buttons (edit / delete open modals; add your own);
   - `deleteBlockers($entity, $cacheability)` — reasons why a record must not be deleted (e.g. other records
     reference it); add the list cache tags of the types you count to `$cacheability`. The access control handler
     then forbids "delete" for everyone (admins too) with the reasons: the Delete action shows disabled with the
     reason, Delete operations disappear, bulk deletes skip the record, APIs refuse it.
   Every screen URL works directly and via HTMX (no full reloads, core's `Htmx` API with only the main content):
   see `Drupal\drubricks_core\Htmx\Swap` (levels: app / list / detail / inspector). Use `Swap::link()` /
   `Swap::modal()` for links you add; never write `hx-*` attributes by hand.
5. **Sidebar** — core **menu links** of the `drubricks` menu ("App sidebar") in `<module>.links.menu.yml` (copy the
   CRM entries): a section is a top-level link with `route_name: '<nolink>'`, each screen a child link (`parent:` the
   section) with `route_name: entity.<type>.collection`; `weight` orders sections and items; `options: icon:
   {pack_id: kit, icon_id: i-<name>}` (a kit / Lucide name in the kit sprite, or the app's own pack —
   `docs/components.md`); `metadata: count: <entity type>` for a counter (records the user may see),
   `metadata: color: <token>` for the icon. Access is the route's access (core's `checkAccess` manipulator); an empty
   section is hidden. Dynamic titles or route parameters: a link class (`class:`, extends core's `MenuLinkDefault`,
   like `CurrentUserMenuLink`). Site builders reorder, disable, rename and add links in Menu UI
   (`/admin/structure/menu/manage/drubricks`).
6. **Dashboard** (home `/apps`) — core **blocks** placed in the theme's two dashboard regions, the Drupal way:
   1. a block plugin per widget in `src/Plugin/Block/`: `#[Block(id: '<module>_<name>', admin_label: …, category:
      'Dashboard')]` extending `DashboardBlockBase` (see the CRM example). `build()` (for `$this->currentUser`) returns
      one `drubricks:stat` tile (helper `stat()`; one tile per block) for the stat strip, or panel rows (`p-row`, `bar-row`, `progress`,
      `avatar`, `pill`, `feed-item` …) — the block template adds the panel and its heading. `getUrl()` is the heading
      link / tile target (the block is hidden without access to it), `getLinkTitle()` its label ("Show all"); other
      rules in `blockAccess()`. Query with `accessibleQuery()` / `accessibleAggregateQuery()` (own / any like the
      lists), load related records in one go (`preloadReferences()`), link records with `recordLink()` (HTMX swap),
      return `[]` to hide the block, set `#cache` max-age for anything that depends on today (`untilMidnight()`) and
      add the list cache tags of what you count in `getCacheTags()`;
   2. its placement `config/optional/block.block.drubricks_<app>_<name>.yml` (theme `drubricks`): region
      `dashboard_stats` (tiles, `label_display: '0'`) or `dashboard` (panels: the block label is the heading;
      third-party settings `drubricks_core: size` (`narrow` / `wide` / `full`), `icon`, `color`), `weight` = app
      sidebar weight × 10 + n. Place it in the Block layout UI and export it (`ddev drush config:get
      block.block.<id>` without `uuid` / `_core`) or copy the CRM files; the App recipe's `config: import:
      <module>: '*'` and `drush en` install it, uninstalling the app removes it;
   3. a plain counter needs no code: core's "Record count" block (`drubricks_record_count`) in the stat strip;
   4. kernel test: `DashboardBlocksTestTrait::assertDashboardBlocks()` (placements valid, in order, access, build).
   Site builders rearrange, resize, hide (visibility: roles) or add blocks (Views, content blocks) in the Block layout UI.
7. **Account menu** (button at the bottom of the sidebar) — core's `account` menu: links with `menu_name: account`
   in `<module>.links.menu.yml` (see "My tasks" of the PM example; `metadata: separator_before`, `danger`). It
   already has "View profile" and "Log out" (core's links, styled by `NavigationHooks`) and "Edit profile".
   - **Admin pages** of the application (settings, imports, … in the admin theme): routes under `/admin/app/…`
     with an admin permission, linked as children of `drubricks_core.admin_app` ("Application" in the admin
     Navigation sidebar, above Reports; `/admin/app` shows a panel per child, hidden while empty) in
     `<module>.links.menu.yml`.
   - **Admin view of every entity** (Application → Entities: browse, filter, sort, edit, delete, bulk activate /
     deactivate / delete) — a View shipped as config of the module, the Drupal way:
     1. the entity gets `'list_builder' => EntityListBuilder::class` (edit / delete operations) and the link
        `'delete-multiple-form' => '/admin/app/entities/<app>/<things>/delete'` (bulk delete confirmation —
        core's `DeleteMultipleForm`; records with `deleteBlockers()` are not deletable at all — see step 4);
     2. the view in `config/install/views.view.<entity_type>_admin.yml` (path `/admin/app/entities/<app>/<things>`,
        table, exposed filters, bulk form `<entity_type>_bulk_form`, operations, access = the app's admin
        permission) and core's actions `system.action.<entity_type>_{delete,publish,unpublish}_action.yml`
        (plugins `entity:delete_action:<type>` / `entity:publish_action:<type>` / `entity:unpublish_action:<type>`).
        Copy the CRM files and adapt them, or build the view in the Views UI (`/admin/structure/views`) and export
        it (`ddev drush config:get views.view.<id>` without `uuid` / `_core`) into the module;
     3. the App recipe imports them (`config: import: <module>: '*'`); `drush en` installs them too;
     4. link it in `<module>.links.menu.yml`: parent `drubricks_core.admin_app_entities`, route
        `view.<entity_type>_admin.page_1`, description "<App>: …", weight = app weight;
     5. kernel test: `AdminViewTestTrait::assertAdminView('<module>', '<entity_type>')` (validates the config).
     Edit / Delete in the view open the app forms with `?destination=` and come back to the view.
8. **Board view (kanban)**: declare it in the screen handler — `board()` returns the lane field, lanes
   (`[value => [label, colour token]]`), optional `order`, `sum`, `filter` and `default` (see `DealScreen::board()`,
   `TaskScreen::board()`), add the link template `drubricks-board` to the entity, and point the sidebar item at
   `entity.<type>.drubricks_board` when the board is the main view. Moves are saved by the generic endpoint with
   field access and entity validation (a refused move is reverted with the message). Override `card()` for the card
   content.
   - **Calendar view**: same idea — `calendar()` returns the `start` / `end` datetime fields, optional `resource`
     reference (columns of the Resources view), `view`, `hours`, `days` (business days, ISO weekdays, default
     Monday–Friday; e.g. `[1, 2, 3, 4, 5, 6]` with Saturdays), `step`, `default`; link template
     `drubricks-calendar`; override `event()` / `calendarResource()` (see `BookingScreen`). Drag / resize is saved by a
     generic endpoint with entity validation (a double booking is refused and snaps back), drag on empty time opens
     the add form prefilled (`?start=&end=&resource=`).
   - **Table view** (a core View in the app — filters, sorting, bulk actions, row actions, pager): build the View
     in the Views UI on the entity's table (style "Table", exposed filters, the `<entity_type>_bulk_form` field with
     core's actions, "Operations links", a full pager, no "Use AJAX"; display option "Show contextual links" off),
     export it into the module's `config/install` (`views.view.<entity_type>_table.yml`, see `crm_deal_table`), return
     `['view' => '<id>']` from `table()` (`display`, `default` optional) and add the link template `drubricks-table`.
     The page shows it with the shared view header (the View's exposed form in its toolbar row — core's "exposed form
     in a block", set by the controller); filters, sorting and the pager reload only the table (HTMX, `Swap::region()`
     / `Swap::filters()`, the URL keeps the state); the drubricks theme gives it the kit look (filter bar,
     "N selected" bulk bar, kit table with `aria-sort`, the first operation as a button and the others in the kit
     menu). On app screens Edit / Delete open modals and come back, View opens the record in the side panel
     (`?open=ID`). The bulk form of Drubricks entities (`DrubricksBulkForm`, also in the admin views) offers only
     what the user may do — a checkbox on rows where an action's `access()` allows it, only those actions, and no
     column or action bar for a read-only user; submit keeps core's check. Base fields filter with a value: references (core's `entity_reference` select), dates (`datetime`,
     a date picker), amounts (`numeric`). Never use `value` as a filter identifier (it clashes with the form).
     Kernel test: `TableViewTestTrait::assertTableView()`.
   - **One header for every view** (`drubricks-screen-header.html.twig`, used by the list, board, calendar and table
     templates): title + record count (`ScreenViews::count()`, or the calendar's date range), the view switch and the
     `+` button (`ScreenViews::add()`) on the right, and a `drubricks:pane-toolbar` row below with the search /
     filters at the start and the view's controls (date navigation, calendar mode) at the end. A new kind of view
     embeds the same template — never a header of its own.
   - **UX rule — never lose the user's view:** a record opened from a board / pipeline (or any non-list view)
     opens in the **right-side panel** (`?open=ID`, `ScreenHandler::panel()`) or a modal, never on another page;
     add / edit / delete started there return to the same view. Only list screens show the record in the details
     pane. Keep this for every new kind of view.
9. **Extra views**: other screens are routes rendering `drubricks_screen`-style panes (route option
   `_drubricks_screen: TRUE`), or Views with the "Drubricks list" style (no "Use AJAX"). JS-heavy views (board,
   calendar) are "islands": a mount element + a `Drupal.behaviors` entry calling `UI.board` / `UI.calendar`,
   data from a JSON endpoint built on `Drupal\drubricks_core\Api\EntityApiControllerBase` / `ApiResponse`
   (route option `_drubricks_api: TRUE`, requirement `_csrf_request_header_token: 'TRUE'`).
10. **Recipes**: `recipes/<module>/` (type "App": installs the module, creates the app's roles with config actions —
   `user.role.<id>: createIfNotExists: {label: …}` + `grantPermissions`, never `config/user.role.*.yml`: the Demo
   recipe applies the App recipe again and pre-existing role config fails it — and sets the site name / palette /
   language when the user gave them) and
   `recipes/<module>_demo/` (type "Demo": demo records in `content/`; it includes the App recipe). The installer
   applies the Demo recipe together with the app — there is no separate "demo data" choice. Each has a `composer.json`
   (`"type": "drupal-recipe"`). The installer lists every "App" recipe as an option.
   A recipe installs only the simple config of its modules: the App recipe must import the module's config entities
   (admin views, actions, …) with `config: import: <module>: '*'` — see the example App recipes.
   Demo content: create the records on the local site — in the UI, or for many linked records with a one-off
   `ddev drush php:script` from a scratch file that is **not** shipped (the shipped artefact is the export) — own them
   by demo users, not user 1 (the export would take the admin along), then export them with core's command, one
   entity type at a time, with what they reference (users, related records):
   `ddev drush content:export <entity_type> --with-dependencies --dir=../recipes/<module>_demo/content`.
   drubricks_core leaves users' language preferences and timezone out of the export. Set `extra.drubricks_core`
   in the Demo recipe — `demo_reference_date` (export day; dates move to the install day, `demo_shift: weeks` keeps weekdays)
   and `demo_timezone` (the export site's timezone; times keep their wall-clock value on a site in any timezone,
   so rules like opening hours still pass the import validation). Exported demo users have no password: log in as
   them with `ddev drush uli --name=<user>` (e2e: `login(page, name)` falls back to a one-time link).
11. **Tests**: kernel tests (entities, access own/any, constraints) like `drubricks_core/tests/src/Kernel/`, and an
   e2e file in `tests/e2e/` (plain Playwright, helpers in `tests/e2e/lib.mjs`: `login`, `mark`/`noReload`).
   Functional tests in `drubricks_installer/tests/src/Functional/`: `AppRecipesTest` applies every App recipe (with
   its demo) to a fresh site — add the new app's demo record count and a page to it; `InstallerTest` walks the
   browser installer. `scripts/check.sh` runs unit, kernel and functional tests.
12. **Check it in a browser**: reinstall with the app (`scripts/reinstall.sh -y --apps=<app>`), open the site
    (`ddev launch /apps` — the URL follows the project's folder, `ddev describe`; the e2e helpers find it too), click
    through list → details → add / edit (modal, invalid then valid) → delete, as each role (`ddev drush uli
    --name=<user>`: what a role must not see stays hidden in lists, counters, the dashboard and direct URLs); light
    and dark mode.

## Interface language (e.g. Polish)

- Source strings in English in code (`t()`, `TranslatableMarkup`, Twig `|t`); translations in
  `translations/<module>.<langcode>.po` of each module, declared in its `*.info.yml`:
  `interface translation project: <module>` and
  `interface translation server pattern: 'modules/custom/%project/translations/%project.%language.po'`.
  The starter ships Polish for `drubricks_core` and the theme (incl. the UI kit's strings, context "UI kit").
- The App recipe installs `language` + `locale`, creates the language (`language.entity.<code>: createIfNotExists`)
  and sets `system.site: default_langcode`; the installer imports every project's translations after the apps
  (core's `install_finish_translations()`). On an existing site: `ddev drush locale:check && ddev drush locale:update`
  (or `ddev drush language:add <code>`).
- A string core (or contrib) already translates gets **core's** translation in your `.po` (compare with
  `web/sites/default/files/translations/drupal-*.<code>.po`) — one translation per string, the last import wins;
  a string that needs another meaning gets its own context (`t('Orange', [], ['context' => 'Colour palette'])`).
- Plurals: `formatPlural()`, and the `.po` header's `Plural-Forms` (Polish has three forms).

## Commands

| What | Command |
|---|---|
| First run (creates the local `.ddev/`, not in git) | `./launch-drubricks.sh` |
| A new project from the starter, base only | `./launch-drubricks.sh --own-project --apps=` |
| Start / URLs | `ddev start` → https://drubricks.ddev.site (admin / admin after a reinstall) |
| Full reinstall from code (drops the DB) | `scripts/reinstall.sh -y` |
| Enable a module, clear caches | `ddev drush en <module> -y`, `ddev drush cr` |
| Checks (same as CI) | `scripts/check.sh` |
| End-to-end checks | `npm run test:e2e` (or `node tests/e2e/<file>.mjs`) |
| Theme CSS after new Tailwind classes in templates/PHP or a change in `kit/ui.css` | `cd web/themes/custom/drubricks && npm run build` (commit `css/app.css`) |
| A new icon for the theme sprite (or ship the app's own icon pack) | add a `<symbol id="i-<name>">` to `kit/icons.<hash>.svg`, rename it to a new hash, `ddev drush cr` (`drubricks.icons.yml`) |

## Where things are

- `web/modules/custom/drubricks_core` — base entity, access, screens, HTMX helpers (`src/Htmx`), shell JS
  (`js/shell.js`: `UI.init` / `UI.attach` / `UI.detach`, modals, messages), the sidebar / account menu builder (`src/Navigation`, from core menus),
  API helpers (`src/Api`), Twig `color()`. Icons: core's Icon API — `icon('kit', 'i-name')` (the theme's `kit` pack).
- `web/themes/custom/drubricks` — the app shell (`templates/layout/page.html.twig`), SDC components
  (`components/`), form styling, the UI kit (`kit/`: `ui.css`, `ui.js` = `window.UI`, the icon sprite, strings) —
  the project's own code: a kit change goes there and into the SDC component that wraps it.
- `web/profiles/custom/drubricks_installer` — installer profile (removes itself after install).
- `recipes/` — `drubricks_base` + an App/Demo pair per app (module, or system of modules).
- `docs/` — guides (components, theming, client projects).

## Gotchas

- Branding is site config, not code: the sidebar shows the logo and site name of Appearance → Settings → Drubricks
  (`logo`, "Toggle display"; default: the theme's `logo.svg`, the Drubricks mark) and the theme setting "Colour
  palette" colours the app for everyone. Users choose only light / dark / auto in the account menu.

- Multi-value references (people, records): use the `drubricks_reference_list` widget (drubricks_core
  `ReferenceListWidget`: chosen records one under another, remove buttons, an "Add…" search with keyboard support;
  validation and access stay core's). Core's legacy AJAX "Add another item" of multi-value fields works inside the
  modals (checked: the AJAX request posts the modal form, saving goes through the HTMX submit); file /
  image upload widgets are not checked yet — try them before using them in an app.

- Helper methods of a screen handler must not reuse `ScreenHandler`'s public names (`count()`, `title()` …): a fatal
  error appears only when the page loads — run phpstan first.
- Names the base classes already use: `status` is the "Active" flag (a domain status field is `state` in the
  examples), and getters must not override `ContentEntityBase` / `EntityBase` methods (a `getLanguages()` on an
  entity broke every save) — check the parents before naming.
- A multi-value base field lives in its own table `<base>__<field>`, column `<field>_value` — that is the table /
  field key in an admin view (Views gives choice lists a select filter there too).
- `scripts/reinstall.sh` passes the site name "Drubricks"; the App recipe's `system.site` name (applied after the
  installer's form) is the one that stays.
- Kit layout CSS needs the panes as **direct children** of `.app` (`.app > .column`): no wrapper elements.
- Module CSS is aggregated before theme CSS; the cascade-layer order is declared by `drubricks_core/css/layers.css`.
- Admin routes use the admin theme (Default Admin). `/user/{id}/edit` is switched to the app theme by
  `drubricks_core` (setting `user_edit_in_app_theme`).
- Drupal 12's `drupal/core` dist has no `core/tests`; the project installs core from source (`preferred-install`).
- Don't commit generated junk; do commit `css/app.css` and `kit/`.
