<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\drubricks_core\Htmx\HtmxResponses;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Edge cases of HTMX requests in the shell.
 *
 * - An anonymous HTMX request that is denied (the session expired) gets a
 *   full redirect to the login page instead of a fragment.
 * - A redirect response to an HTMX request (e.g. a form without our
 *   HTMX-aware save) becomes HX-Location, so the shell follows it.
 * - Core's HTMX script sends its page state with every request; for a GET it
 *   lands in the query string, and pagers, table sorting and Views copy the
 *   query into their links. It is taken out of the query, like core's
 *   ViewAjaxController does for Views AJAX (core keeps the parsed state in a
 *   request attribute, AjaxPageState middleware).
 */
final class HtmxSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 100],
      KernelEvents::EXCEPTION => ['onException', 100],
      KernelEvents::RESPONSE => ['onResponse', -100],
    ];
  }

  /**
   * Keys core's HTMX script adds to a request that are not the page's input.
   */
  private const INTERNAL_QUERY = ['ajax_page_state', '_triggering_element_name', '_triggering_element_value'];

  /**
   * HTMX GET: the script's own values out of the query (not in links).
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if (!$event->isMainRequest() || !HtmxResponses::isHtmx($request)) {
      return;
    }
    foreach (self::INTERNAL_QUERY as $key) {
      $request->query->remove($key);
    }
  }

  /**
   * Anonymous + access denied on an app page → login page.
   *
   * HTMX request (the session expired): HX-Redirect, a full page load.
   * Normal request to an app screen: a redirect with ?destination=.
   */
  public function onException(ExceptionEvent $event): void {
    $request = $event->getRequest();
    if (!$this->currentUser->isAnonymous() || !$event->getThrowable() instanceof AccessDeniedHttpException) {
      return;
    }
    if (HtmxResponses::isHtmx($request)) {
      $current = $request->headers->get('HX-Current-URL') ?: $request->getRequestUri();
      $path = parse_url($current, PHP_URL_PATH) ?: '/';
      // HTMX follows HX-Redirect from a successful response only.
      $event->allowCustomResponseCode();
      $event->setResponse(HtmxResponses::redirect(Url::fromRoute('user.login', [], ['query' => ['destination' => $path]])));
      return;
    }
    $route = $request->attributes->get('_route_object');
    if ($route && $route->getOption('_drubricks_screen') && $request->isMethod('GET')) {
      $login = Url::fromRoute('user.login', [], ['query' => ['destination' => $request->getRequestUri()]])->toString();
      $event->setResponse(new RedirectResponse($login));
    }
  }

  /**
   * Redirects become HX-Location for HTMX requests.
   */
  public function onResponse(ResponseEvent $event): void {
    $response = $event->getResponse();
    if (!$response instanceof RedirectResponse || !HtmxResponses::isHtmx($event->getRequest())) {
      return;
    }
    $event->setResponse(HtmxResponses::redirect(Url::fromUri($response->getTargetUrl())));
  }

}
