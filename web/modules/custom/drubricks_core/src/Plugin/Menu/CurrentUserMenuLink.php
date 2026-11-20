<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * A menu link to a route of the current user (route parameter "user").
 *
 * For example "Edit profile" (entity.user.edit_form) in the account menu;
 * like core's LoginLogoutMenuLink, the link varies by user.
 */
class CurrentUserMenuLink extends MenuLinkDefault {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    StaticMenuLinkOverridesInterface $static_override,
    protected readonly AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters() {
    return ['user' => $this->currentUser->id()] + parent::getRouteParameters();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user'];
  }

}
