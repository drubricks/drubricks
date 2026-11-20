<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Htmx;

use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
 * The swap levels of the app shell. All HTMX attributes go through here.
 *
 * - CONTENT: the whole app shell .app (sidebar click, after a save). Loads
 *   the full page, so it works for any page of the front-end theme.
 * - DETAIL: the details pane (row click).
 * - INSPECTOR: the right sidebar ("info" action).
 * - LIST: the rows of the list column (search, paging).
 * - SIDEBAR: the navigation (counters refresh).
 * - A region (Swap::region()): e.g. the table of a table view, whose pager,
 *   sort links and filters (Swap::filters()) reload only the table.
 *
 * Keeping every HTMX call in this class is the single place to adapt when the
 * core HTMX API changes (Drupal 12 shipped HTMX 4 while it was in beta).
 */
final class Swap {

  public const CONTENT = 'drubricks-app';
  public const DETAIL = 'drubricks-detail';
  public const INSPECTOR = 'drubricks-inspector';
  public const SIDEBAR = 'drubricks-sidebar';
  public const LIST = 'drubricks-list';

  /**
   * Makes a link swap one level of the shell and push its URL.
   *
   * @param array $element
   *   Render array of a link (or any element with #attributes).
   * @param \Drupal\Core\Url $url
   *   URL to load; it is also the URL pushed to the address bar.
   * @param string $level
   *   One of the level constants.
   * @param bool $push
   *   Push the URL to the history (FALSE for the inspector).
   * @param bool|null $main_content_only
   *   Ask Drupal for the main content only. Defaults to TRUE below the
   *   content level; the content level loads the full page, so a sidebar
   *   link works for any page (screen or not) of the front-end theme.
   */
  public static function link(array &$element, Url $url, string $level = self::DETAIL, bool $push = TRUE, ?bool $main_content_only = NULL): void {
    $htmx = (new Htmx())->get($url);
    if ($main_content_only ?? $level !== self::CONTENT) {
      $htmx->onlyMainContent();
    }
    if ($level === self::DETAIL) {
      // The inspector belongs to the record: a click on another row replaces
      // it too (or removes it when the new record has none).
      $htmx->selectOob('#' . self::INSPECTOR);
    }
    $htmx
      ->select('#' . $level)
      ->target('#' . $level)
      ->swap('outerHTML', '', !$push)
      ->pushUrl($push)
      // A second click while the first is loading replaces the first request.
      ->sync('this:replace')
      ->applyTo($element);
    self::bubble($element);
    $element['#attributes']['data-drubricks-level'] = $level;
  }

  /**
   * Live search of a list: typing reloads the list level, the URL keeps ?q=.
   */
  public static function search(array &$element, Url $url): void {
    (new Htmx())
      ->get($url)
      ->onlyMainContent()
      ->select('#' . self::LIST)
      ->target('#' . self::LIST)
      ->swap('outerHTML', '', TRUE)
      ->trigger('input changed delay:300ms, search')
      ->pushUrl(FALSE)
      ->replaceUrl(TRUE)
      ->sync('this:replace')
      ->applyTo($element);
    self::bubble($element);
    $element['#attributes']['data-drubricks-level'] = self::LIST;
  }

  /**
   * Links in an element swap only that element (a View's pager and sorting).
   *
   * Boosts the element (core's hx-boost): its links load their page and the
   * element is replaced by its new version, the URL is pushed, a record opened
   * with ?open=ID updates the inspector. Links with their own HTMX attributes
   * and modal links (Swap::modal) keep their behaviour; a form inside that
   * must post normally gets Swap::noBoost() (its links stay boosted).
   *
   * @param array $element
   *   Render array of the region (or any element with #attributes).
   * @param string $id
   *   The region's element id.
   */
  public static function region(array &$element, string $id): void {
    $element['#attributes']['id'] = $id;
    // HTMX 4 inherits an attribute only with ":inherited"; core's boost()
    // takes no modifiers (yet), so this one is set here.
    $element['#attributes']['data-hx-boost:inherited'] = 'true';
    (new Htmx())
      ->select('#' . $id, Htmx::INHERITED)
      ->target('#' . $id, Htmx::INHERITED)
      ->selectOob('#' . self::INSPECTOR, Htmx::INHERITED)
      ->swap('outerHTML', '', TRUE, Htmx::INHERITED)
      ->pushUrl(TRUE, Htmx::INHERITED)
      ->applyTo($element);
    self::bubble($element);
  }

  /**
   * A GET form (a View's exposed filters) that reloads only a region.
   *
   * Submitting, or changing a filter, loads the page with the form's values,
   * swaps the region (Swap::region()) and pushes the URL; the form itself
   * stays, with its focus.
   *
   * @param array $form
   *   The form render array.
   * @param string $id
   *   The id of the region to swap.
   * @param \Drupal\Core\Url $url
   *   The page the form filters.
   */
  public static function filters(array &$form, string $id, Url $url): void {
    // The full page, like the boosted links of the region: a main-content
    // request would put _wrapper_format into the query, and Views copies the
    // query into its pager and sort links.
    (new Htmx())
      ->get($url)
      ->trigger('submit, change')
      ->select('#' . $id)
      ->target('#' . $id)
      ->selectOob('#' . self::INSPECTOR)
      ->swap('outerHTML', '', TRUE)
      ->pushUrl(TRUE)
      ->sync('this:replace')
      ->applyTo($form);
    self::bubble($form);
  }

  /**
   * Leaves a form or link inside a boosted region (Swap::region()) alone.
   *
   * Only the element itself: links inside a form stay boosted.
   */
  public static function noBoost(array &$element): void {
    (new Htmx())->boost(FALSE)->applyTo($element);
    self::bubble($element);
  }

  /**
   * Makes a form submit with HTMX and re-render itself (errors in place).
   *
   * @param array $form
   *   The form render array.
   * @param string $id
   *   The id of the form element.
   * @param \Drupal\Core\Url $url
   *   Where the form posts. Needed because an empty hx-post posts to the
   *   address bar URL, which differs from the form URL in a modal.
   */
  public static function form(array &$form, string $id, Url $url): void {
    $form['#attributes']['id'] = $id;
    (new Htmx())
      ->post($url)
      ->onlyMainContent()
      ->select('#' . $id)
      ->target('#' . $id)
      ->swap('outerHTML')
      // A double click on Save sends the form once.
      ->sync('this:drop')
      ->applyTo($form);
    self::bubble($form);
  }

  /**
   * Marks a link to open its target form in a modal (UI.modal).
   */
  public static function modal(array &$element, Url $url, ?string $title = NULL): void {
    // shell.js opens it; a boosted region (Swap::region()) must not load it.
    (new Htmx())->boost(FALSE)->applyTo($element);
    $element['#attributes']['data-drubricks-modal'] = $url->toString();
    if ($title !== NULL) {
      $element['#attributes']['data-drubricks-modal-title'] = $title;
    }
  }

  /**
   * Bubbles an element's cache metadata and attachments to the render context.
   *
   * Core's Htmx::applyTo() puts the URLs' cacheability and the HTMX library on
   * the element; callers that only pass its #attributes on (to a component or
   * a template) would drop them. Like MetadataBubblingUrlGenerator::bubble().
   */
  private static function bubble(array $element): void {
    $renderer = \Drupal::service('renderer');
    if ($renderer->hasRenderContext()) {
      $build = [];
      BubbleableMetadata::createFromRenderArray($element)->applyTo($build);
      $renderer->render($build);
    }
  }

}
