# Data and entities

Content entities, their fields and displays, Field UI, validation rules, revisions, loading related records. Read
for any change to an entity type, its fields, form / view displays, constraints, or how records are loaded.

## Checklist

- Content entities on `DrubricksEntityBase`; fields as base fields (or configurable fields as config); choice
  lists with literal `TranslatableMarkup` in the allowed-values method; revisions on every entity.
- Field UI on every entity (`field_ui_base_route`), default displays as config; designed screens follow Manage
  display (`shows()`, `displayFields()`).
- Rules as entity constraints (`#[Constraint]`, registered in `constraints:`), never only in a form.
- Related records: load with entity queries / storage, preload in one query; no direct SQL for listing.

## Cases

**Fields added in the UI (Field UI) and designed screens**
- Don't: hand-built details that ignore Manage display (fields added in the UI never show, hidden ones stay);
  entity types without `field_ui_base_route`; displays only at runtime (nothing to export, Manage display empty).
- Do: `field_ui_base_route` → an admin route of the module (like core's `entity.user.admin_form`); default form /
  view displays as config in `config/install`; designed screens ask the view display (`EntityViewDisplay::
  collectRenderDisplay()`) — `shows()` before placing a field, `displayFields()` (the display's `build()` minus the
  placed fields, with its cache tags) below them; forms are `ContentEntityForm`s, so the form display applies.
- Watch: a route from a **link template** counts for core's Navigation as a record page (`EntityRouteHelper` takes
  every link template path but `collection`), so a type-level admin page there got a stray page-actions menu —
  route it in `routing.yml` instead. Widgets with extra settings (subclasses of core widgets) need
  `field.widget.settings.<id>` schema, or exporting a display fails the schema check. Drupal 12 Manage display
  opens on a view-mode overview (`/display`), the editor is `/display/default`.
- Core: `user/user.routing.yml` (`entity.user.admin_form`), `field_ui/src/Routing/RouteSubscriber.php`,
  `navigation/src/EntityRouteHelper.php`, `Core/Entity/Entity/EntityViewDisplay.php`.

**Revisions**
- Do: every change of a record is a new revision (entity-level rule, covers board / calendar / API saves);
  `preSaveRevision()` keeps the log message on saves without a new revision — **set it on `$record`**, which the
  storage maps before calling `preSaveRevision()`.
- Core: `Node::preSaveRevision()`, `SqlContentEntityStorage::saveRevision()`.

**Entity method names**
- Don't: name a getter after a `ContentEntityBase` method — `Teacher::getLanguages()` (codes of a `languages`
  field) replaced core's protected `getLanguages()` and every save died with "getId() on null" in
  `ContentEntityStorageBase::invokeFieldMethod()`. Likewise `status` is the base class's "Active" flag: a domain
  status field needs another name (`state` in the examples).
- Do: domain names that cannot collide (`getLanguageCodes()`, `state`); check `ContentEntityBase` / `EntityBase`
  / `DrubricksEntityBase` first. Lingua School test app.

**Base fields in Views without a usable filter**
- Don't: leave decimal base fields with core's `standard` filter (no value form), reference base fields with a
  `numeric` id box, date base fields with a text `string` filter — or name an exposed filter's identifier `value`
  (the exposed form loses it and the filter applies an empty `>=`, the table comes back empty).
- Do: `DrubricksViewsData` maps them to core's `numeric`, `entity_reference` (select through the selection handler,
  access-aware) and `datetime` plugins — what core gives configurable fields; pick other identifiers (`min_value`).
  `TableViewTestTrait` fails on both.

**Aggregating by month / day in Views (charts)**
- Don't: a controller or SQL of our own summing per period, or grouping by the raw datetime (one row per day /
  second).
- Do: a Views field plugin whose formula is core's — `$query->getDateFormat($query->getDateField(…), 'Ym', TRUE)`
  (views.date_sql, every database) — with the same formats as core's datetime sort granularity (`Y`, `Ym`, `Ymd`),
  so "sort by the date, granularity month" orders by the grouped expression. Datetime fields grouped with the
  timezone offset, date-only fields as stored. drubricks_core `DatePeriod` ("<date> (period)", `DrubricksViewsData`
  also gives base datetime fields core's `datetime` sort).
- Core: `datetime/src/Plugin/views/sort/Date.php`, `views/src/Plugin/views/sort/Date.php` (granularity formats),
  `views/src/Plugin/views/query/DateSqlInterface.php`.
- Watch: core's list filter (`ManyToOne`) with operator `not` on a base field of the entity table fails
  (`ManyToOneHelper::ensureMyTable()`); use `or` with the allowed values.
