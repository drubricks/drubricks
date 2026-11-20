# Caching

Cache metadata of everything rendered: records and the records they reference, list tags of counted types, user
context of query alters, metadata of HTMX elements. Read for any change that renders entity / config / user data
(screens, details, dashboard blocks, sidebar counters) or alters queries per user.

## Checklist

- Every render array that prints entity / config / user data carries their cache metadata
  (`ScreenHandler::cacheability()`, `addCacheableDependency()`, list tags of counted types, `untilMidnight()`).
- Query alters that depend on the user bubble the `user` context (`QueryAccess::bubble()`).
- Elements built with core's `Htmx` keep their metadata (`Swap` bubbles it).

## Cases

**Related records**
- Don't: cache a list / details with only the shown type's list tag — renaming a company left contacts stale.
- Do: `ScreenHandler::cacheability($entities)` (records + referenced records) on every screen render array; list
  tags of other counted types.
- Core: `CacheableMetadata::addCacheableDependency()`, `EntityReferenceFormatterBase`.

**Htmx element metadata**
- Don't: keep only `$element['#attributes']` after `Htmx::applyTo()` — drops `#cache` / `#attached`.
- Do: `Swap` bubbles the element's `BubbleableMetadata` to the render context.
- Core: `MetadataBubblingUrlGenerator::bubble()`, `Htmx::urlValue()`.

**Links with a CSRF token outside a plain href (data attributes, JSON for JS)**
- Don't: keep only the cacheability of `Url::toString(TRUE)` (`addCacheableDependency($generated)`) — a route with
  `_csrf_token` (Masquerade's "Unmasquerade") gets a *placeholder* token from core's `RouteProcessorCsrf`, replaced
  only through the generated URL's attachments; without them the link keeps the placeholder and the route answers
  403. And Twig's `json_encode` escapes `/` as `\/`, so a placeholder in JSON would not match either.
- Do: collect `BubbleableMetadata` (`addAttachments($generated->getAttachments())`) on the render array, and
  `json_encode(constant('JSON_UNESCAPED_SLASHES'))` for URLs in data attributes. E2e the real click (navigation.mjs:
  masquerade there and back).
- Core: `Drupal\Core\Access\RouteProcessorCsrf::processOutbound()`, `Renderer::replacePlaceholders()`. Found by
  the 3English test project.

**BigPipe placeholders in HTMX fragments (dashboard blocks missing "sometimes")**
- Symptom: after a cache invalidation, the home page swapped in with HTMX showed no dashboard blocks (13
  `data-big-pipe-placeholder-id` spans); a full load, or a warm render cache, showed them all.
- Cause: core's `HtmxRenderer` returns an `HtmlResponse`, so BigPipe streams the placeholders' content as scripts
  after the content; the shell's swap selects only its element and drops them.
- Don't: re-request the page, render the blocks without placeholders (`#create_placeholder` off, losing their
  cacheability), or set `_no_big_pipe` on the routes (full loads would lose BigPipe).
- Do: decorate core's `placeholder_strategy.big_pipe` (`decorates:` + `decoration_on_invalid: ignore`) and return
  `[]` for HTMX requests — core's chained strategy then renders them in the same response (single flush)
  (`HtmxBigPipeStrategy`, kernel test `HtmxBigPipeTest`). Commit: the HTMX BigPipe fix.
