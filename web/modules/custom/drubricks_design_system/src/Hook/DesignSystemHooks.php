<?php

declare(strict_types=1);

namespace Drupal\drubricks_design_system\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hooks of drubricks_design_system.
 */
final class DesignSystemHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'drubricks_design_system' => [
        'variables' => [
          'groups' => [],
          'components' => [],
          'form' => NULL,
          'count' => 0,
        ],
      ],
    ];
  }

}
