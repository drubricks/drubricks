# Access

Who may see, change or delete which records: access control handlers, query access alters, route requirements,
labels of referenced records, delete blockers. Read for any change to entities' access, lists / counts / Views /
references, routes, or record-level rules. Cache metadata of access results: see also [caching.md](caching.md).

## Checklist

- Single records: the access control handler (own / any, record-level rules ANDed, cache metadata).
- Lists, counts, Views, references: `hook_query_ENTITY_TYPE_access_alter` (via `QueryAccess`), never conditions
  in controllers / handlers / templates. Revision queries (`allRevisions()`) must be covered too.
- Labels of referenced records only with `view label` access (`ScreenHandler::referenced()`).
- Records that must stay (`deleteBlockers()`): "delete" forbidden with the reasons in the access control handler,
  never only in a form; actions forbidden with a reason are shown disabled with it.
- Routes: `_entity_access`, `_entity_create_access`, `_permission: 'a+b'`, entity route parameters
  (`entity:{entity_type_id}`) instead of loading / checking in the controller.

## Cases

**Own / any rule in lists**
- Don't: add `->condition('uid', $account->id())` by hand in `ScreenHandler::query()` and a dashboard trait —
  autocomplete, reference options, board filters and Views missed it (a "view own" user saw others' records).
- Do: one `#[Hook('query_alter')]` for every `ENTITY_TYPE_access` tag of a Drubricks type (`RecordAccessHooks`);
  pass another account as query metadata `account`.
- Core: `node/src/Hook/NodeDatabaseHooks.php::queryNodeAccessAlter()`; tags added in
  `Entity/Query/Sql/Query.php` and Views' `Sql::alter()`.

**Record-level alters must find the right table**
- Don't: look only for the base table — `allRevisions()` queries use the revision table, the alter returned early
  and the query showed other projects' tasks.
- Do: `QueryAccess::alias()` (base / data / revision tables, `base_table` metadata), `account()` / `operation()`
  from metadata, `bubble(['user'])`.
- Core: `NodeDatabaseHooks` (table mapping, `base_table`, cache context to the render context).

**Referenced records in screens**
- Don't: `$entity->get('company')->entity?->label()` — leaks names of records the user may not see.
- Do: `ScreenHandler::referenced()` / `referenceLabel()` (`view label` access).
- Core: `EntityReferenceLabelFormatter`, `EntityReferenceFormatterBase::getEntitiesToView()`.

**Records that must not be deleted**
- Don't: check "delete blockers" (e.g. contacts at a company) only in the delete forms — bulk actions, Views
  operations, APIs and admins went around them.
- Do: the access control handler forbids `delete` with the reasons, for everyone (`AccessResult::forbidden($reason)`
  with the cache metadata of the check — the list tags of the counted types, collected through an optional
  `CacheableMetadata` argument like `Token::replace()`'s `BubbleableMetadata`). Core then does the rest: Delete
  routes 403, operations disappear, the views bulk form skips the record with its "No access to execute …"
  message (so no custom bulk confirmation is needed). UI: an action forbidden *with a reason* is shown disabled
  (`aria-disabled`, focusable) with the reason (`Url::access(NULL, TRUE)` + `AccessResultReasonInterface`).
- Core: `language/src/LanguageAccessControlHandler.php`, `views/.../field/BulkForm.php`,
  `Entity/Form/DeleteMultipleForm.php`.

**Route access**
- Don't: load the entity and call `->access('update')` in the controller; a custom access check for a static
  "any of these permissions" rule.
- Do: route parameter `type: 'entity:{entity_type_id}'` + `_entity_access: entity.update`; `_permission: 'a+b+c'`.
- Core: `comment.routing.yml`, `DefaultHtmlRouteProvider`.

**Rule through a chain of records (Lingua School test: a teacher sees the clients of their lessons)**
- Don't: filter clients by "has a lesson with me" in the client screen / dashboard / form options.
- Do: one rule service (`TeachingAccess`, like PM's `ProjectAccess`) used by the access control handlers and by
  `hook_query_ENTITY_TYPE_access_alter` for every type in the chain (teacher → lesson → client) with subqueries;
  the record whose save changes visibility lists the other types in `list_cache_tags` (Lesson:
  `lingua_client_list`, Teacher: lesson + client lists) and the single-record result carries the same list tags.
  An "office" bypass as its own permission (like `bypass node access`), not only the admin permission.
- Core: `NodeDatabaseHooks::queryNodeAccessAlter()`, `EntityType::getListCacheTags()`. Lingua School test app.

**"view label" is "view" unless the handler says otherwise (3English test project)**
- Don't: rely on `$entity->access('view label')` reaching your `checkAccess()` as `view label` — core's
  `EntityAccessControlHandler::access()` turns it into `view` while `$viewLabelOperation` is FALSE, so a rule
  "lecturers see only their own profile, but every lecturer's name" hid the names too (`ScreenHandler::referenced()`
  asks `view label`).
- Do: `protected $viewLabelOperation = TRUE;` in the access control handler that answers `view label` itself.
- Core: `Core/Entity/EntityAccessControlHandler.php` (`access()`), `CommentAccessControlHandler`.

**Record-level rule across several entity types (3English test project)**
- Do: one service with the rule (`SchoolAccess`: subqueries "lecturer profiles of the account", "clients the
  account sees") used by one access control handler for every type and by `query_TYPE_access_alter` hooks;
  `list_cache_tags` of the types the rule reads (a lesson decides which clients a lecturer sees, so lessons
  invalidate the client and package lists). Default value of "my" reference fields with
  `setDefaultValueCallback()`; references to other records are refused by core's `ValidReference` through the
  same alter.

**Bulk actions offered to users who may not run them**
- Don't: hide the bulk form in a template / with CSS by role, or by checking permission names in a preprocess —
  and don't leave core's default either: a checkbox on every row and every action, refused only after submit
  ("No access to execute …").
- Do: extend core's `BulkForm` field plugin (like core's `NodeBulkForm`) and ask, before rendering, the same question
  core asks on submit — `$action->getPlugin()->access($entity, $account)` per row; swap the plugin for the entity
  types in `hook_views_data_alter` (+ config schema `views.field.<plugin>: type: views.field.bulk_form`). Rows
  without an allowed action get no checkbox, actions allowed nowhere leave the list, a read-only user gets no column
  and no bar (`DrubricksBulkForm`). Kernel test per user (`TableViewTestTrait::bulkFormAsCurrentUser()`).
