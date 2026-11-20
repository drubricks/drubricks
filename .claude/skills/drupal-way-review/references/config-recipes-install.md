# Config, recipes and install

Config entities shipped by modules (Views, actions, displays, menus, blocks), config schema, settings forms,
recipes and demo content, the installer, the starter becoming a project's own code, scripts and generators. Read
for any change to `config/`, `*.schema.yml`, settings forms, `recipes/`, the installer profile, `scripts/`, or
the launcher.

## Checklist

- Config entities (Views built in the Views UI, actions, displays) in the module's `config/install`; the App
  recipe imports them (`config: import: <module>: '*'`).
- Settings forms: `ConfigFormBase` + `#config_target`; side effects of a setting on config save
  (`ConfigEvents::SAVE`), not in the form submit.
- **No update hooks in the starter** — reinstall. **No helper scripts, generators or standalone PHP files** — ship
  config and code; use core / drush commands (`content:export`, `generate-theme`, `config:get`).
- Recipes read with core's `Recipe` object; demo content via core default content + `PreEntityImportEvent` /
  `PreExportEvent`.

## Cases

**Starter code becoming the project's code**
- Don't: rename the starter theme with core's `generate-theme --starterkit` when modules, templates and config use
  its SDC components (`drubricks:<component>`, 79 files) and block placements name the theme — the components get
  the new machine name and every reference breaks. Don't tell projects to `git pull` a starter that has no update
  hooks either.
- Do: keep machine names and place (like a site template that ships components; core's starterkit docs, Mercury);
  make the code the project's own: stamp `starter_source` in every `*.info.yml` (Drupal ignores unknown info keys),
  drop the starter's `.git`, start a new repository (`scripts/start-own-project.sh`, from the
  launcher), like Open Intranet's launcher. Branding through theme settings, not a renamed theme.
- Core: `core/themes/starterkit_theme/starterkit_theme.starterkit.yml`, `Drupal\Core\Command\GenerateTheme`.

**Generators and scripts**
- Don't: `scripts/admin-view.php` writing Views YAML, `scripts/demo/generate.php` making demo records,
  `scripts/sdc-from-kit.mjs` scaffolding components, Python post-processing of exports.
- Do: Views built in the Views UI, exported (`drush config:get`) into `config/install`; demo records created on a
  site and exported with core's `drush content:export … --with-dependencies`, site-specific fields left out with
  `PreExportEvent::setExportable()`; SDC components written by hand.

**Config schema constraints**
- Don't: `Choice: [narrow, wide, full]` — kernel tests installed the config fine, `recipe:apply` failed with
  "$options must be an associative array" (recipes validate config, kernel installs only check types).
- Do: `Choice: { choices: [...] }` (or `callback:`), like `system.schema.yml`; the functional `AppRecipesTest`
  applies every recipe and catches it.

**Module config entities in recipes**
- Don't: rely on `drush en` behaviour — a recipe installs only simple config of its modules; views / actions from
  `config/install` were missing after a reinstall.
- Do: `config: import: <module>: '*'` in the App recipe; optional config of core modules imported explicitly
  (`user: [views.view.user_admin_people]`).

**Settings side effects**
- Don't: rebuild the router in a settings form submit (misses `drush cset`, config import, recipes).
- Do: `ConfigEvents::SAVE` subscriber checking `isChanged()`; the form with `#config_target`.
- Core: `NodeAdminRouteSubscriber::onConfigSave()`, `RegionalForm`.

**Installer**
- Don't: parse `recipe.yml` with Symfony YAML; grep `type: 'App'` in shell; an extra `drupal_flush_all_caches()`
  task.
- Do: `Recipe::createFromDirectory()` (`type`, `name`, `description`, `getExtra()`); `apps=all`; rely on
  `keep_profile: false` (core uninstalls the profile and rebuilds the router).
- Core: `Recipe.php`, `install_finished()`.

**Multi-value choice lists in Views**
- Don't: ship an admin view whose filter on a multi-value `list_string` base field is a text box (core's
  `EntityViewsData` gives its dedicated table column `NAME_value` a plain `string` filter), or leave the filter out.
- Do: `DrubricksViewsData` sets `list_field` (+ `field_name`) on the dedicated table too, like
  `OptionsViewsHooks::fieldViewsDataAlter()` does for configurable list fields. The view's field / filter key is
  `NAME_value` on table `<base>__NAME`. Kernel check: `drubricks_core` `ViewsDataTest`.

**Roles of an app (Lingua School and 3English test projects)**
- Don't: ship `user.role.<id>.yml` in the App recipe's `config/` and grant permissions with an action — the Demo
  recipe includes the App recipe, the second apply finds the role with permissions and fails with
  `RecipePreExistingConfigException` (the installer applies App and Demo recipes in one go; `config: strict: false`
  hides it only while the config is identical).
- Do: `user.role.<id>: createIfNotExists: {label: …}` + `grantPermissions` in the App recipe's actions — safe to
  apply again, and a site may change the role later.
- Core: `Config/Action/Plugin/ConfigAction/CreateIfNotExists`, `Recipe/ConfigConfigurator`, core's
  `content_editor_role` recipe (a role created by a recipe).

**A language from an App recipe and its translations (3English test project)**
- Don't: expect `locale` to import translations when a recipe adds a language — it imports only when a module
  is installed outside the installer into a site that already has the language, or when the site is installed
  in that language; an App recipe applied by the installer in English got Polish as default and English UI.
- Do: the recipe installs `language` + `locale`, creates the language (`createIfNotExists`) and sets
  `system.site: default_langcode`; modules ship `translations/<module>.<lang>.po` with `interface translation
  project` / `interface translation server pattern`; the installer profile runs core's own
  `install_finish_translations()` after the apps (skipped when core does it itself). On an existing site:
  `drush locale:check && drush locale:update`.
- Core: `install.core.inc` (`install_finish_translations()`), `locale/src/Hook/LocaleExtensionHooks.php`,
  `LocaleSource::sourceBuild()` (local server pattern).

**A View written by hand into config/install**
- Don't: ship a View YAML typed (or copied and edited) by hand — Views computes dependencies and cache metadata on
  save, so the installed config differs from the file and a recipe applied again (the Demo recipe includes the App
  recipe) fails with `RecipePreExistingConfigException: … does not match the recipe's configuration`
  (`InstallerTest` caught it for `crm_deal_table`).
- Do: save the View on the site (Views UI) and export it: `ddev drush config:get views.view.<id>` without
  `uuid` / `_core`.
- Watch: swapping a Views handler plugin in `hook_views_data_alter` (e.g. `bulk_form` → `drubricks_bulk_form`)
  makes Views add the plugin's module to every View's `dependencies` on save — re-save and export all affected
  Views, or `InstallerTest` fails the same way.
