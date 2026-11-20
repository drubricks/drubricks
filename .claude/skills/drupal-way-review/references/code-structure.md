# Code structure

Hooks and services, dependency injection, time and dates, translatable strings, state on shared objects. Read
for any new or changed PHP class, hook, service, or user-facing string.

## Checklist

- OOP `#[Hook]` classes with dependency injection; no `\Drupal::` in injectable classes (entity classes and
  static helpers excepted).
- Time from `datetime.time`, dates through `DateFormatter` / field `date` properties — no `date()`, `strtotime()`,
  `new \DateTimeImmutable('now')`.
- Translatable strings literal: `t('…')` / `new TranslatableMarkup('…')`, plurals with `formatPlural()`.
- No state on shared handlers / services between calls (read the request where needed).

## Cases

**Time and dates**
- Don't: `date()`, `strtotime()`, `new \DateTimeImmutable('today')`, `substr()` on stored datetime values.
- Do: `datetime.time` (request time), `DateFormatter::format()` / `formatInterval()`, the field's `date` property,
  the user's timezone (`date_default_timezone_get()` after core sets it).

**Translatable labels**
- Don't: constants of English strings passed to `new TranslatableMarkup($variable)` / `t($variable)`.
- Do: literal `new TranslatableMarkup('Lead')` in the allowed-values method; colours / icons in separate maps.

**Interface translations shipped with a module**
- Don't: translate a string core (or contrib) already translates differently — locale stores one translation per
  source string and context, so the import that runs last wins ("Home" became "Start" in the sidebar after
  `drush locale:update`).
- Do: use core's translation for shared strings (compare with `sites/default/files/translations/drupal-*.pl.po`),
  and give strings that need another meaning their own context (`t('Orange', [], ['context' => 'Colour palette'])`
  + `msgctxt` in the `.po`). Found in the 3English test project.

**Method names of ScreenHandler (watch, 3English test project)**
- Watch: a helper named `count()` or `title()` in a screen handler subclass collides with `ScreenHandler`'s
  public methods — a fatal error only when the class loads (the page returns 200 with a PHP fatal in the body).
  Run phpstan before opening the page.

**A system of several modules (ERP-sized projects)**
- Don't: put everything into one huge module, or let modules of one system patch each other's code (an inventory
  module editing the sales module's entity class to add a field or a rule), or depend on each other both ways.
- Do: split by domain — each entity type owned by one module, the others reference it and declare the module in
  `dependencies:` (one way); extend another module's records with hooks: fields via `hook_entity_base_field_info`,
  constraints via `hook_entity_type_alter` (`addConstraint()`), reactions via hooks / event subscribers, menu links
  under its section (`parent:`). Core does it the same way: `path` adds the `path` base field to other modules'
  entities (`PathHooks`), `workspaces` adds the `EntityWorkspaceConflict` constraint
  (`EntityTypeInfo::entityTypeAlter()`). AGENTS.md golden rule 2.
