# Tests

Which kind of test covers what: kernel, functional (recipes, installer), e2e. Read for any change that adds or
should add tests — and for every fix (step 5 of the review: add the test that would have caught it).

## Checklist

- Kernel tests for entities, access (both entity access and queries), constraints, config schema (installing a
  module's config validates it); functional tests for recipes / installer; e2e for the user path.

## Cases

**Recipes and installer**
- Don't: `drush php:eval` one-liners in CI as the only check of recipes / installation.
- Do: functional tests — `InstallerTestBase` (override `setUpSite()` fully when the profile adds its own install
  steps, set `isInstalled` only at the end) and `BrowserTestBase` + `RecipeTestTrait::applyRecipe()`; in CI the
  PHP server starts **in `web/`** (`cd web && php -S … .ht.router.php`).
- Core: `FunctionalTests/Installer/DistributionProfileTest.php`, `Core/Recipe/StandardRecipeTest.php`.

**RevisionsTestTrait and integer fields (fixed)**
- Was: `assertEditCreatesRevision()` compared the in-memory value (`10`) with the stored revision's (`'10'`).
- Now: the trait reads the old value as stored. `DashboardBlocksTestTrait` takes `$per_user` placements (blocks
  only some users see, e.g. "my next lessons") — they must exist; test their visibility in the app.

**PHPStan does not see a variable removed under `??` (watch)**
- `$add['url'] ?? NULL` after `$add` was moved out of the controller passed phpcs and phpstan; the calendar e2e
  ("free slot → add form prefilled") caught it. After extracting a variable into a helper, grep the file for every
  use; keep the e2e step that exercises it.

**Charts built with Views**
- Do: `ChartViewTestTrait` — `setUpCharts()` (Chart.js as the library, like the base recipe),
  `assertChartView()` (route, view switch, "Chart" format), `renderChartView()` executes the View and renders the
  style's chart (not the View's wrapper template, which is the theme's), returning the decoded Chart.js
  definition: assert labels (periods in order), sums and series. e2e: wait for Chart.js's colour animation before
  comparing element colours.
