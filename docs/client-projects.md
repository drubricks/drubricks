# Client projects

A client project starts from Drubricks and **owns its code**: the theme (`web/themes/custom/drubricks`), the core
module and the example apps (`web/modules/custom/drubricks_*`) become the project's code. Drubricks is a starter, not
a dependency — it ships no update hooks and no upgrade path, so a project is never updated with `git pull` from the
starter. Change the starter code in the project freely (agents do, see `AGENTS.md`).

Usually the agent starts the project from a prompt (`AGENTS.md`, "A new system from a prompt"): your own project,
base only, the example apps kept in the code as references but not installed.

The theme and modules keep their machine names: modules, templates and config use the theme's components as
`drubricks:<component>` and place blocks in the theme `drubricks`. The project's name, logo and colours are site
settings (Appearance → Settings → Drubricks), not a renamed theme.

## 1. Start

```bash
composer create-project drubricks/drubricks client-app --stability=dev --repository='{"type":"vcs","url":"https://github.com/drubricks/drubricks"}'
```

```bash
cd client-app && ./launch-drubricks.sh
```

(or `git clone` the starter). The launcher creates the local DDEV configuration (`.ddev/` is not in the repository)
named after the folder, asks how you use the code and how to install:

- **Your own project** (answer yes, or `./launch-drubricks.sh --own-project`; the step alone:
  `scripts/start-own-project.sh`): every extension of the starter gets
  `starter_source: 'drubricks:<version>+<commit>'` in its `*.info.yml`, the starter's `.git` is
  removed and a new repository with a first commit is created — with `composer.lock`: the starter keeps no lock
  (like Drupal CMS), the project tracks its exact versions from the first install. A later run of the launcher sees
  the stamp and never asks again.
- **Contributing to Drubricks** (answer no, or `-y`): everything stays as it is.

## 2. A site recipe (type "Site")

`recipes/client_site/recipe.yml` — what this client gets:

```yaml
name: 'Client site'
description: 'Everything this client uses.'
type: 'Site'
recipes:
  - drubricks_base
  - drubricks_examples_crm          # or the client's own app recipes
  - client_inventory
config:
  actions:
    system.site:
      simpleConfigUpdate:
        name: 'ACME Operations'
    user.role.authenticated:
      grantPermissions:
        - 'edit any crm_deal'
```

Install it with `ddev drush recipe ../recipes/client_site` after a base install, or add it to the installer's
choices (any recipe of type "App" is listed automatically).

## 3. The client's apps

The client's own modules, `web/modules/custom/<client>_<name>` — one for a small app, several cooperating ones for a
larger system (shared records in a base module, the domains on top of it; `AGENTS.md`, golden rule 2) — modelled on
`drubricks_examples_crm`:
entities on `DrubricksEntityBase`, a screen handler, sidebar menu links with a `weight`, an App recipe and a Demo recipe.
Extra fields that the client edits without code can be added in Field UI on the entity (base fields are
`setDisplayConfigurable(…, TRUE)`).

## 4. Branding

Site settings first: logo, site name and colour palette in Appearance → Settings → Drubricks. For more (fonts, own
templates) edit the project's `drubricks` theme, or add a sub-theme of it (see [theming.md](theming.md)); keep the
CSS build, so Tailwind sees the client's templates.

## 5. Updating

| Part | How |
|---|---|
| Drupal core, contrib | `ddev composer update` |
| Starter (core module, theme, examples) | Not updated: it is the project's code. To take a fix from a newer Drubricks, diff the starter between the project's `starter_source` and the new version and apply what you need (by hand or with an agent). |

After an update: `scripts/check.sh`, `ddev drush updb -y` (Drupal core / contrib), `ddev drush cr`, and the e2e checks.
The starter's own modules ship no update hooks — entity and field changes in them are applied by reinstalling
(`scripts/reinstall.sh`). Once the client app runs with real data, its own modules get normal `hook_update_N()`
for their entity / field changes.
