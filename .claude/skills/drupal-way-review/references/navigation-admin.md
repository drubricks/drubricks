# Navigation, blocks and admin UI

Menus (app sidebar, account menu), core's admin sidebar (Navigation), things placed on pages (dashboard tiles and
panels as blocks). Read for any change to navigation links, the dashboard, block placements, or the admin
sidebar. Field UI / Manage display: see [data-entities.md](data-entities.md); admin views and their recipes: see
[config-recipes-install.md](config-recipes-install.md).

## Checklist

- Navigation lists (sidebar, account menu) are core menu links (`links.menu.yml`, `menu.link_tree`), editable
  in Menu UI — not an own link plugin type.
- Things a site builder places on a page (dashboard tiles, panels) are Block plugins in theme regions, with
  placement settings as third-party settings of the block — not an own plugin type with its own layout keys.

## Cases

**Core's admin sidebar (Navigation) and the current page**
- Don't: switch the admin theme or copy Navigation's templates to get an open active trail (Drupal CMS gets it from
  the contrib Gin theme's `navigation.js`; Gin 5 is not Drupal 12 ready; core's Default Admin has no such script).
- Do: a small `Drupal.behaviors` script that works with core's own markup and events — mark the link whose path is
  the longest prefix of the page's (core only matches the exact URL) with core's classes and `toolbar-active-url`,
  and re-open its submenus with `toolbar-menu-set-toggle` when its section opens (`toolbar-popover-toggled`; core's
  `sidebar.js` collapses them). Top-level sections stay flyouts (core's design), not open on load.
- Core: `navigation/js/toolbar-menu.js`, `toolbar-popover.js`, `sidebar.js`. Commit: navigation active trail.

**Navigation lists (sidebar, account menu)**
- Don't: own plugin types for links (`#[SidebarItem]`, `#[AccountMenuItem]`, `*.drubricks_sidebar.yml`) with own
  `section` / `weight` / `permission` keys, managers and access checks — invisible to Menu UI.
- Do: core menu links in `MODULE.links.menu.yml` (`menu_name`, `parent`, `weight`, `options.icon`, free-form
  `metadata` for what the theme needs, e.g. a counter's entity type); headings as `<nolink>` parents; load with
  `menu.link_tree` + the `checkAccess` / `generateIndexAndSort` manipulators and `build()` (menu config tag, access
  and link cacheability); dynamic links as `MenuLinkDefault` subclasses (like core's `LoginLogoutMenuLink`); core's
  own links (`user.page`, `user.logout`) restyled in `hook_menu_links_discovered_alter`, not duplicated.
- Watch: a menu (`system.menu.*`) is a config entity — a recipe must `import` it (the module install does not);
  `onlyEnabledLinks()` hangs the children of a disabled parent under the previous link — load all and let `build()`
  skip disabled links with their subtrees.
- Core: `core/lib/Drupal/Core/Menu/MenuLinkTree.php` (`buildItems()`), `MenuTreeStorage::treeDataRecursive()`,
  `user/src/Plugin/Menu/LoginLogoutMenuLink.php`.

**Placeable widgets (dashboard tiles and panels)**
- Don't: an own plugin type for things a site builder places on a page — `#[DashboardWidget]` + manager + YAML
  discovery (`*.drubricks_dashboard.yml`) with own `size` / `weight` / `permission` / `route_name` keys and a
  controller looping over them. Nothing of it showed in the Block layout UI; no visibility conditions, no
  contextual links, no Views or content blocks on the dashboard.
- Do: core **Block plugins** (`#[Block]`, category "Dashboard", `blockAccess()`, `build()` for the current user)
  placed in **theme regions** (`block.block.*` in the module's `config/optional`, imported by the App recipe,
  removed with the module); placement-specific settings as **third-party settings** of the block entity
  (`hook_form_block_form_alter` + `#entity_builders`, schema `block.block.*.third_party.<module>`); framing by
  region with `hook_block_view_alter` + `hook_theme_suggestions_block_alter` + `block--REGION.html.twig`; a region
  printed inside a controller's markup is built like `BlockPageVariant` (`getVisibleBlocksPerRegion()` + the block
  view builder, tag `config:block_list`).
- Watch: a block template without a wrapper element (to make the tiles grid items) drops `title_prefix` /
  `attributes`, so admins lose the "Configure block" contextual link; keep one wrapper per block (one tile per
  block) instead of several tiles from one block.
- Core: `block/src/Plugin/DisplayVariant/BlockPageVariant.php`, `BlockViewBuilder::buildPreRenderableBlock()`,
  `block/src/Hook/BlockThemeHooks.php`, `menu_ui/src/Hook/MenuUiHooks.php` (entity builders).

**Dashboard layout: pairs, not gaps**
- Don't: place panels one after another regardless of height (a short panel next to a tall one leaves a hole;
  on a wide screen a third column breaks the pairs).
- Do: the dashboard grid has two columns and the panels of a row share its height (`drubricks-home.html.twig`,
  kit `.panels` with `align-items: stretch`, `grid-auto-flow: dense`); each app places its panels in pairs of a
  similar height by `weight` and makes a panel without a pair `wide` — block placement config, no code.
