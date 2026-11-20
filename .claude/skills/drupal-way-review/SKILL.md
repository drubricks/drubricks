---
name: drupal-way-review
description: Review Drubricks code (a diff, a module, or the whole project) for "Drupal way" violations — custom code where Drupal core / established contrib already has a mechanism, missing access or cache metadata, scripts or generators instead of config, update hooks in the starter. Use before committing a feature, when asked "is this Drupal way?", and as the last step of every task (then add what the task taught to the matching references/*.md).
---

# Drupal-way review

AGENTS.md rule 0: **everything the Drupal way — don't invent.** This skill turns that rule into a repeatable
review. The depth lives in `references/`: one file per area, each with the area's checklist and the project's
catalogue of concrete cases (Don't / Do / Core pointer / issue or commit). The catalogue grows with every task.

## How to review

1. **Scope.** Default: the current change (`git diff` + untracked files). Or a path / module the user names.
   Read AGENTS.md (architecture), then the reference files for the areas the change touches (index below) and
   always [false-positives.md](references/false-positives.md).
2. **For every piece of new or changed code ask:** does Drupal core (or established contrib) already have a
   mechanism for this? Check before claiming — look in `web/core` (modules, `core/recipes`, `core/tests`,
   `lib/Drupal/Core/...`), and use drupal.org / api.drupal.org / change records when the code does not tell.
   **Every finding must point at the core mechanism** (class, hook, service, file) — no pointer, no finding.
3. **Verify, don't guess.** Read the code path end to end; when in doubt, reproduce (kernel test, `ddev drush
   php:eval`, a request). Several "violations" turned out to be core behaving as designed — see
   [false-positives.md](references/false-positives.md). Mark anything unverified as *uncertain*.
4. **Report** (most important first): `file:line` — what is non-standard — the Drupal way (core pointer) — effort
   (small / medium / large) — security / correctness impact if any. Then a short "fine as is" list.
5. **Fix** when asked (or when it is part of the task): smallest change that uses the core mechanism; add a test
   that would have caught it (kernel for access / entities / config, functional for recipes / installer, e2e for
   the user path); run `scripts/check.sh`, and after entity / config changes `scripts/reinstall.sh -y` + e2e.
6. **Grow the catalogue** — required, not optional (see below).

## Reference index

Load by what the change touches; a change usually touches several areas.

| The change touches | Load | Covers |
|---|---|---|
| Entity types, fields, displays, Field UI, constraints, revisions, loading related records | [data-entities.md](references/data-entities.md) | `DrubricksEntityBase`, base / configurable fields, Manage display in designed screens, entity constraints, revisions |
| Who sees / changes / deletes records; lists, counts, Views, references; routes | [access.md](references/access.md) | Access control handler, `query_ENTITY_TYPE_access_alter`, revision tables, `view label`, delete blockers, route requirements |
| Render arrays with entity / config / user data, per-user query alters | [caching.md](references/caching.md) | Cache metadata of records and referenced records, list tags, `user` context, `Swap` bubbling |
| Templates, theme, SDC, HTMX, icons, JavaScript, messages, pager, licences of bundled assets | [rendering-frontend.md](references/rendering-frontend.md) | Theme hooks, core `Htmx` API, Icon API, `Drupal.behaviors` + `once()`, `Drupal.Message`, theme settings |
| App sidebar, account menu, admin sidebar (Navigation), dashboard, block placements | [navigation-admin.md](references/navigation-admin.md) | Core menu links, Navigation active trail, Block plugins in regions with third-party settings |
| PHP classes, hooks, services, dates, user-facing strings | [code-structure.md](references/code-structure.md) | `#[Hook]` classes + DI, `datetime.time` / `DateFormatter`, literal translatable strings, no state on shared services |
| `config/`, schema, settings forms, recipes, demo content, installer, `scripts/`, starter / own project | [config-recipes-install.md](references/config-recipes-install.md) | Config entities in modules + recipe imports, schema constraints, `ConfigEvents::SAVE`, no update hooks / generators, `Recipe` API, starter becoming the project's code |
| Tests (and every fix) | [tests.md](references/tests.md) | Kernel / functional / e2e — which covers what, installer and recipe tests |
| Any finding, before reporting it | [false-positives.md](references/false-positives.md) | Checked suspicions that are core behaving as designed or deliberate choices |

## Growing the catalogue

After every review or task, add what it taught:

- **A new violation → fix:** add a case at the end of the `## Cases` section of the matching
  `references/<topic>.md` (newest last). Keep it short: **Don't / Do / Core** pointer / issue or commit (optional
  **Watch** for gotchas). If it adds a rule, add a line to that file's `## Checklist` too.
- **A checked false positive:** add it to [false-positives.md](references/false-positives.md) with the reason.
- **No file fits:** create a new `references/<topic>.md` (kebab-case name; same layout: title, one paragraph on
  when to read it, `## Checklist`, `## Cases`) and add a row for it to the reference index above.
