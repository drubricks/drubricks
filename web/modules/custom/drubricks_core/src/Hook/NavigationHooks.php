<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Core's account menu links in the app's account menu.
 */
final class NavigationHooks {

  /**
   * Orders, styles and gives icons to "View profile" and "Log out".
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(array &$links): void {
    if (isset($links['user.page'])) {
      $links['user.page']['title'] = new TranslatableMarkup('View profile');
      $links['user.page']['weight'] = 0;
      $links['user.page']['options']['icon'] = ['pack_id' => 'kit', 'icon_id' => 'i-user'];
    }
    // Masquerade's "Unmasquerade" (shown while switched to another account).
    if (isset($links['masquerade.unmasquerade'])) {
      $links['masquerade.unmasquerade']['options']['icon'] = ['pack_id' => 'kit', 'icon_id' => 'i-arrow-left'];
    }
    if (isset($links['user.logout'])) {
      $links['user.logout']['weight'] = 1000;
      $links['user.logout']['options']['icon'] = ['pack_id' => 'kit', 'icon_id' => 'i-log-out'];
      $links['user.logout']['metadata']['danger'] = TRUE;
      $links['user.logout']['metadata']['separator_before'] = TRUE;
    }
  }

}
