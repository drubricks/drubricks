<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Htmx;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxLocationResponseData;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Responses for HTMX requests (navigation after a save, events for the JS).
 *
 * The HX-* headers are built by core's Htmx API; these helpers put them on a
 * bare response a form or an event subscriber can return.
 */
final class HtmxResponses {

  /**
   * Whether the request was sent by HTMX (core's HtmxRequestInfoTrait test).
   *
   * For code without a request-aware base class; forms and controllers use
   * HtmxRequestInfoTrait::isHtmxRequest().
   */
  public static function isHtmx(?Request $request): bool {
    return $request !== NULL && $request->headers->has('HX-Request');
  }

  /**
   * Navigates the shell to a URL (content level) and fires JS events.
   *
   * @param \Drupal\Core\Url $url
   *   Where to go; pushed to the history (HTMX's default for HX-Location).
   * @param array $events
   *   Events for HX-Trigger, e.g. ['drubricks:saved' => ['message' => …]].
   * @param string $level
   *   Which level of the shell to swap.
   */
  public static function navigate(Url $url, array $events = [], string $level = Swap::CONTENT): Response {
    $htmx = (new Htmx())->locationHeader(new HtmxLocationResponseData(
      path: $url,
      target: '#' . $level,
      swap: 'outerHTML',
      select: '#' . $level,
    ));
    if ($events) {
      $htmx->triggerHeader($events);
    }
    return self::response($htmx);
  }

  /**
   * Path + query of the page an HTMX request was sent from, or NULL.
   */
  public static function currentPage(?Request $request): ?string {
    $current = $request?->headers->get('HX-Current-URL');
    if (!$current) {
      return NULL;
    }
    $parts = parse_url($current);
    parse_str($parts['query'] ?? '', $query);
    unset($query['_wrapper_format'], $query['ajax_page_state']);
    return ($parts['path'] ?? '/') . ($query ? '?' . http_build_query($query) : '');
  }

  /**
   * The local ?destination= of the request (e.g. an admin view), or NULL.
   */
  public static function destination(?Request $request): ?string {
    $destination = $request?->query->get('destination');
    if (!is_string($destination) || $destination === '' || UrlHelper::isExternal($destination)) {
      return NULL;
    }
    return '/' . ltrim($destination, '/');
  }

  /**
   * Forces a full page load (e.g. session expired → login page).
   */
  public static function redirect(Url $url): Response {
    return self::response((new Htmx())->redirectHeader($url));
  }

  /**
   * An empty response with the HX-* headers of an Htmx object.
   */
  private static function response(Htmx $htmx): Response {
    $response = new Response('', 200);
    $response->headers->add($htmx->getHeaders()->all());
    return $response;
  }

}
