<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Routing;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Shows the user edit form in the app theme instead of the admin theme.
 *
 * Core's UserRouteProvider marks /user/{user}/edit as an admin route. With
 * drubricks_core.settings:user_edit_in_app_theme (default TRUE) it opens in
 * the Drubricks shell like the profile page. Changing the setting (form,
 * config import, recipe, drush) rebuilds the router, like core's
 * NodeAdminRouteSubscriber does for node.settings:use_admin_theme.
 */
final class UserRoutesSubscriber extends RouteSubscriberBase {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RouteBuilderInterface $routerBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if (!$this->configFactory->get('drubricks_core.settings')->get('user_edit_in_app_theme')) {
      return;
    }
    $collection->get('entity.user.edit_form')?->setOption('_admin_route', FALSE);
  }

  /**
   * Rebuilds the router when user_edit_in_app_theme changes.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    if ($event->getConfig()->getName() === 'drubricks_core.settings' && $event->isChanged('user_edit_in_app_theme')) {
      $this->routerBuilder->setRebuildNeeded();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[ConfigEvents::SAVE][] = ['onConfigSave', 0];
    return $events;
  }

}
