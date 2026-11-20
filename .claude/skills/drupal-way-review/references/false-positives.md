# False positives (checked — not violations)

Suspicions that were checked and turned out to be core behaving as designed, or a deliberate choice. Read before
reporting a finding in any area; each entry says why it is fine.

- **"The logo / site name in the sidebar needs the theme settings' cache tags".** Saving `system.theme.global` or
  `THEME.settings` invalidates `rendered` (core's `ConfigCacheTag`), like for core's `SystemBrandingBlock`; only
  `system.site` (the name) is added as a dependency.
- **`Url::toString()` without `TRUE` "drops cacheability".** Inside a render context (controllers, hooks,
  preprocess) core's `MetadataBubblingUrlGenerator::bubble()` bubbles it. `toString(TRUE)` matters for URLs that
  end up outside render arrays (responses, headers).
- **"The first save in the Views UI will rewrite the shipped views".** Checked: saving `crm_contact_admin` in the UI
  produced no diff against the module's YAML.
- **"Replace `ApiExceptionSubscriber` with `_format: json` + core's `ExceptionJsonSubscriber`".** Ours keeps the
  API envelope and readable, translated messages the board / calendar show; core would return raw exception text.
- **`<project>/recipes` path "hard-coded".** It is core recommended-project's `drupal-recipe` installer path and
  core resolves recipes by directory (`RecipeConfigurator::getIncludedRecipe()`); reading composer.json would be
  invented code.
- **"Core's HTMX integration detaches the wrong element".** `htmx-assets.js` (`behaviorTarget()`) passes the
  *parent* of the swapped element to `Drupal.detachBehaviors()` / `attachBehaviors()` on purpose, so selectors from
  the context also match the target itself. Consequence, not a bug to patch: a behavior's `detach` must not tear
  down everything under its context (siblings stay on the page) — kit widgets are torn down on exactly the swapped
  element in `htmx:before:swap` (shell.js).
- **Revision user / time in `preSave()`.** A deliberate entity-level rule (board / calendar / API saves must create
  revisions too); core sets them in the form only.

- **Direct SQL in record-level access subqueries** (e.g. `TeachingAccess::teachesClient()`). Not a listing: an
  access condition on base tables, like core's node access grants and the PM example's `memberProjects()`; entity
  queries inside an access check would recurse into the same `_access` alters.
- **Demo records created with a one-off `drush php:script` from a scratch file** (not shipped, deleted after the
  export). The shipped artefact is core's `content:export` output, as with records typed in the UI; AGENTS.md
  allows it for many linked records. Lingua School test app.

- **A record label built from a referenced record without `view label`** (3English: a lesson's label is its
  client's name). Fine when the record itself is only visible to users who may see that reference (a lecturer
  sees a lesson only if they teach it, and then the rule shows them its client).
- **A count not access checked** (`Package::usedLessons()` counts all lessons of a package): a number the same
  for everyone, no records; the constraint must count them all anyway.

**Polish "List additional actions" = "Pokaż dodatkowe kolumny" (core's translation)**
- Not our bug to fix in the .po: one translation per source string. The kit dropbutton uses its own string
  ("More actions" → "Więcej akcji") instead of overriding core's.

**`{% embed '@drubricks_core/drubricks-screen-header.html.twig' %}` (a template included by path)**
- Fine for a partial shared by several theme-hook templates (core themes include partials by namespace too); the
  "theme hook, not a path" rule is for what PHP renders. The partial still renders kit SDCs.
