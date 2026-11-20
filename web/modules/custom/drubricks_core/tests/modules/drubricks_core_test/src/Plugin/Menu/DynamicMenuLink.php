<?php

declare(strict_types=1);

namespace Drupal\drubricks_core_test\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;

/**
 * A class-based sidebar link: its title comes from code.
 */
final class DynamicMenuLink extends MenuLinkDefault {

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return 'Dynamic 42';
  }

}
