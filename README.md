# Drubricks

**Open-source starter kit for building solid web apps with your AI agent.**

Tell your AI agent what the system is for — a language school, a car workshop, a dental clinic — and it builds one
from proper Drupal parts: data, access rules, screens, demo data and tests. Free, on Drupal 12.

Drupal 12 · PHP 8.5 · HTMX 4, no React or Vue · 75 UI components · GPL — [drubricks.com](https://www.drubricks.com)

> ⭐ If you find Drubricks useful, please **star the repository** — it helps more people discover it.

## Start with one prompt

Open Claude Code, Codex, OpenCode or any coding agent in an empty folder and paste:

```text
Based on Drubricks (https://github.com/drubricks/drubricks) build me a system to manage the teachers and clients of a language school.
```

Change the last sentence to describe your business: a car workshop, a cookie bakery, field service, property
management, a recruitment agency … The agent asks a few questions, builds the system, installs it and gives you a
login.

## See it in action

[![Drubricks in action — watch on YouTube](https://img.youtube.com/vi/UUjX7ufU64o/maxresdefault.jpg)](https://www.youtube.com/watch?v=UUjX7ufU64o)

A walk through the starter: the apps, the screens and how an agent builds on it
([watch on YouTube](https://www.youtube.com/watch?v=UUjX7ufU64o)).

## A real app on day one, not a blank page

The starter comes with three example apps: a CRM with a deals pipeline, project management with a task board, and
booking with a resource calendar. Your system gets the same shell, the same components and the same screens —
dashboard, lists with details, boards, calendars, tables, modal forms, light and dark mode, colour palettes — in its
own records. Screenshots: [drubricks.com](https://www.drubricks.com).

## How it works

One prompt, four steps, your own system. The agent follows [`AGENTS.md`](AGENTS.md); you answer a few questions and
review the result.

1. **You describe the system.** The agent asks 3–7 questions before it writes any code: who works in it and what each
   role can see, the records and their fields, the main views (lists, a board, a calendar, a dashboard), the rules
   that must never break, the language, currency, name, logo, colours and demo data.
2. **It starts your own project.** It clones the starter and runs `./launch-drubricks.sh --own-project --apps=`: a
   new git repository, the base installed, the example apps kept in the code as references only.
3. **It builds your modules.** Your system is written as modules of its own (`web/modules/custom/<prefix>_<name>`),
   modelled on the bundled examples: one for a small system, several cooperating ones (sales, inventory, HR) for an
   ERP-sized one. Each comes with recipes, demo data and tests, and the agent installs it and checks it.
4. **You open the site.** Log in (`ddev launch`) and it works. Later prompts change it the same way — "add invoices",
   "teachers see only their lessons" — and the owner can add fields in the admin UI without code; the screens show
   them.

The starter's code becomes your project's code: there is no upgrade path from Drubricks and nothing to keep in sync.

## Why a starter

Agents are great builders. They need a frame to build in. On a blank framework an agent invents the structure from
scratch every time; Drubricks gives it Drupal's slots to fill, so the same kind of request produces the same kind of
code.

| Blank framework + agent | Drubricks + agent |
|---|---|
| A new architecture with every prompt | Entities, fields, access handlers, plugins, recipes: defined slots |
| Login, roles and record access written by hand, again | Access checks, CSRF, escaping and SQL safety come from core |
| Front end, back end and an API between them to keep in sync | One app: PHP renders the screens, HTMX swaps them in place |
| No admin UI: every field change is a code change | Roles, Field UI, Views and menus without code |
| Works in the demo, gets harder to change every month | `AGENTS.md` and a review skill keep each change the Drupal way |

## Why Drupal

Boring where it matters. Business systems live for years, hold data that matters and get changed by many hands —
more and more of them AI agents. Drupal was built for that.

- **Secure.** A dedicated security team, coordinated advisories and long-supported releases; banks, governments and
  universities run on it. Access checks, CSRF protection, output escaping and SQL injection safety are part of core,
  not something each app has to get right again.
- **A simple stack.** PHP renders, HTMX swaps. No headless split, no second build and deploy, no client state to keep
  in sync.
- **Runs anywhere.** PHP with MySQL, MariaDB or PostgreSQL, from a cheap shared plan to Kubernetes. No Node.js server
  in production (Node only builds the theme CSS, and the built CSS is committed).
- **A harness for agents.** The framework limits what an agent can get wrong. Every entity gets the same admin UI,
  and the same tests run — so a system built by prompt can be developed further for years, by other people and other
  agents.
- **Admin UI included.** Roles and permissions, Field UI, Views, menus, block layout, revisions, translations: the
  owner changes things without a developer.
- **Proven for 20+ years.** Developed since 2001 by one of the largest open source communities, with thousands of
  modules for single sign-on, workflows, search and integrations.
- **Multilingual, accessible.** The interface is translated into 100+ languages, and WCAG accessibility is a
  requirement for core changes.
- **No lock-in.** GPL, no licence fees, no platform to rent. The code and the data are yours.

## What is inside

Everything a business app needs before the first feature is written.

- **`drubricks_core` — the app engine.** Base entity with owner, dates, status, colour and revisions; own/any
  permissions and record-level access; the list + details + inspector screen with modal forms, boards, calendars
  and tables (core Views with filters and bulk actions in the kit look); HTMX helpers; the sidebar and account menu
  from core menus (editable in Menu UI); dashboard blocks; Field UI for every entity (fields added in the UI show in
  the forms and the details); JSON API helpers for JS islands; demo-content date shifting; per-record logs.
- **`drubricks` theme — 75 components.** The UI kit's app shell as single-directory components (SDC), Drupal forms in
  the kit look, the logo, site name and colour palette from the theme settings, light / dark / auto.
- **Examples** — references for the agent; in your own project they are not installed (in the starter itself the
  installer offers them, each with its demo data):
  - `drubricks_examples_crm` — companies, contacts, deals: a validation rule, a dependent field, delete blockers;
  - `drubricks_examples_project_management` — projects with members, tasks, a drag-and-drop board; users see only
    their own projects and those projects' tasks;
  - `drubricks_examples_booking` — rooms, cars, equipment and people in a week / resources calendar, no double
    bookings, opening hours;
  - `drubricks_design_system` — the component catalogue of the UI kit (`/apps/design-system`).
- **[`AGENTS.md`](AGENTS.md) — the agent's manual.** How to start a new system from a prompt, the conventions, and the
  `drupal-way-review` skill that checks every change.
- **Installer and recipes.** `drubricks_installer`: base recipe, then pick apps, each with its demo data; the profile
  removes itself after installation.
- **Admin tools.** Core Navigation and the Default Admin theme, Gin Login, Masquerade (see what a user's role sees),
  Config Log (every configuration change logged), Project Browser. Coffee and Automated Logout follow once they
  support Drupal 12.
- **Checks.** phpcs, PHPStan, PHPUnit and Playwright, plus an install with every app in CI.

## Stack

Current versions, all the way down: a new project starts on the newest releases of everything it stands on.

| | | |
|---|---|---|
| Drupal | 12.0 (beta 2) | the framework: entities, access, admin UI, recipes |
| PHP | 8.5 | the language |
| Symfony | 8.1 | routing, HTTP kernel and services under Drupal core |
| HTMX | 4 | in Drupal core: swaps screens without page reloads |
| MariaDB | 11.8 | the database in DDEV; MySQL and PostgreSQL work too |
| PHPUnit | 12.5 | unit, kernel and functional tests |

## Quickstart

Let the agent do it ([Start with one prompt](#start-with-one-prompt)), or run it by hand to look around the examples
first:

```bash
git clone https://github.com/drubricks/drubricks.git my-project
```

```bash
cd my-project && ./launch-drubricks.sh
```

```bash
ddev launch
```

You need [DDEV](https://ddev.com/get-started) 1.25 or newer, Docker (Docker Desktop, OrbStack or Colima) and Git.
Nothing else on the host: PHP 8.5, Composer and the database run in DDEV.

## Install by hand

The quickstart step by step — what the agent does in step 2 of [How it works](#how-it-works).

### Prerequisites

- [DDEV](https://ddev.com/get-started) 1.25 or newer, with Docker (Docker Desktop, OrbStack or Colima) running.
- Git. Nothing else on the host: PHP 8.5, Composer and the database run in DDEV.
- Node.js only if you want to run the end-to-end checks or rebuild the theme CSS (the built CSS is committed).

### Steps

**Step 1**: Install [DDEV](https://ddev.com/get-started) and start Docker.

**Step 2**: Clone the repository (into a folder named after your project):

```bash
git clone https://github.com/drubricks/drubricks.git my-project
```

```bash
cd my-project
```

**Step 3**: Run the launcher — it configures and starts DDEV and installs the Composer dependencies:

```bash
./launch-drubricks.sh
```

**Step 4**: Say how you use the code (the launcher asks): **your own project** — the theme and the modules become
your code (stamped with `starter_source`), the starter's history goes and a new git repository starts — or
**contributing to Drubricks** (keeps everything). See [docs/client-projects.md](docs/client-projects.md).

**Step 5**: Choose how to install (the launcher asks):

1. **Everything** — every example app (CRM, project management, booking, design system), each with its demo data;
2. **Base only** — no apps, add them later;
3. **Browser installer** — opens `/core/install.php`, where you set the site name and the admin account and pick
   the applications (an app brings its demo data).

Options 1 and 2 create the account **admin / admin** (change it after the first login).

Without questions:

| | |
|---|---|
| `./launch-drubricks.sh --own-project --apps=` | your own project, base only — the start of a system built from a prompt |
| `./launch-drubricks.sh --own-project` | your own project with every example app installed |
| `./launch-drubricks.sh -y` | the starter itself (contributing): every example app, starter files kept |

**Step 6**: Open the site (`ddev launch`): log in and you land on `/apps`.

### Install from the command line

```bash
ddev drush site:install drubricks_installer --account-name=admin --account-pass=admin -y drubricks_installer_apps.apps=drubricks_examples_crm,drubricks_examples_project_management
```

`drubricks_installer_apps.apps` takes recipe names from `recipes/` (type "App"); leave it empty for the base only.
Each app is installed with its demo data (`recipes/<app>_demo`). `scripts/reinstall.sh` wraps this (all apps by
default, `--apps=…`) and **drops the database** first.

### After the install

| | |
|---|---|
| App | https://drubricks.ddev.site/apps (the URL follows the DDEV project name) |
| Admin pages | the left admin sidebar, or `/admin` |
| Add an app later (with demo data) | `ddev drush recipe ../recipes/<app>_demo` |
| Add an app without demo data | `ddev drush recipe ../recipes/<app>` |
| One-time login link | `ddev drush uli` |
| Start over | `scripts/reinstall.sh -y --apps=<your apps>` (drops the database; the default `all` installs the examples too) |

### Troubleshooting

- **"Docker is not running"** — start Docker Desktop / OrbStack / Colima and run the launcher again.
- **Port or name conflicts** — the launcher picks a free DDEV project name (`drubricks-2`, …) when another
  folder already uses `drubricks`; the site URL changes accordingly (`ddev describe`).
- **A white page or 500 after pulling changes** — `ddev composer install && scripts/reinstall.sh -y`. The starter has no
  update hooks: entity and field changes are applied by a reinstall (it drops the database).
- **Styles look broken after editing templates** — rebuild the theme CSS (see `docs/theming.md`).

## Add an app to your project

Describe it to your agent, e.g. *"Add an inventory app: products (name, SKU, price, stock, category) and stock
movements (product, quantity, date, reason). Stock may not go below zero. Sidebar section 'Inventory' after the
school."* The agent creates `web/modules/custom/<prefix>_inventory` modelled on the examples, with recipes, tests and
demo data — see [`AGENTS.md`](AGENTS.md) for the exact steps it follows.

## Docs

| | |
|---|---|
| [`AGENTS.md`](AGENTS.md) | how a system is built from a prompt, step by step (for agents and people) |
| [`docs/components.md`](docs/components.md) | SDC components, mapping to the UI kit, adding a component |
| [`docs/client-projects.md`](docs/client-projects.md) | a client project: own code from the starter, site recipe, own modules, branding, updates |
| [`docs/theming.md`](docs/theming.md) | palette, tokens, sub-theme, CSS build |

## Checks

```bash
scripts/check.sh
```

```bash
npm install && npm run test:e2e
```

`scripts/check.sh` runs phpcs, phpstan and PHPUnit (the same as CI). The e2e checks (Playwright) click through the
apps on the local site; `tests/e2e/installer.mjs` walks the browser installer and only runs with
`DRUBRICKS_INSTALL_TEST=1` because it drops the local database. In your own project the examples are not installed:
run your apps' checks (`npm run test:e2e -- tests/e2e/<app>.mjs`).

## FAQ

**Do I need to know Drupal?** Not to get started. The agent follows `AGENTS.md` and models its modules on the bundled
examples. The code it writes is standard Drupal, so any Drupal developer can review it or take it over later.

**Is it really free?** Yes. Drubricks is open source under the GPL, like Drupal. There is no paid tier and no licence
fee.

**Which AI agents does it work with?** It is made and tested with Claude Code. The instructions live in `AGENTS.md`, a
plain file that other coding agents read too.

**How do I update my project when Drubricks changes?** You don't, by design. Once you start, the starter's code
becomes your project's code. Drupal core and contributed modules update with Composer as usual, and your project keeps
receiving Drupal's security releases.

**Where can I host it?** Anywhere that runs PHP with MySQL, MariaDB or PostgreSQL. Locally everything runs in DDEV.

**Is there a React or Vue front end?** No. Drupal renders the screens and HTMX swaps them without full page reloads.
For the odd interactive widget there are JSON API helpers for small JavaScript islands.

**Can I add fields without code?** Yes: Application → Entity structure → Manage fields. The app's forms and record
details show new fields on their own.

## Need a team for AI or Drupal?

[Droptica](https://www.droptica.com) can help:

- [droptica.ai](https://www.droptica.ai): AI implementations in companies,
- [droptica.com](https://www.droptica.com): growth and security for mission-critical Drupal platforms.

## Security

Security issues in **Drupal core or a contributed module** go to the Drupal Security Team: see
[How to report a security issue](https://www.drupal.org/drupal-security-team/how-to-report-a-security-issue).
Issues in the **code of this project** (`drubricks_core`, the theme, the installer, the examples, the scripts): open
an issue in [this repository](https://github.com/drubricks/drubricks/issues). See [`SECURITY.md`](SECURITY.md).

## Licence

Drubricks is free software under the [GNU General Public License, version 2 or later](LICENSE.txt), like Drupal.
Bundled third-party works (fonts, icons) keep their own licences: see
[`THIRD-PARTY-NOTICES.md`](THIRD-PARTY-NOTICES.md).

Drupal is a registered trademark of Dries Buytaert. Drubricks is not affiliated with or endorsed by Dries Buytaert or
the Drupal Association.
