<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Render;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Core's Navigation top bar (#type top_bar) only on admin pages.
 *
 * App pages have their own toolbar; admin pages need the top bar: the admin
 * theme (Default Admin) shows local actions ("Add user", "Create a new
 * field"), the sticky form actions and the breadcrumb there. A #pre_render
 * because '#access' in element info is checked before the type defaults are
 * merged.
 */
final class TopBarVisibility implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRender'];
  }

  /**
   * Renders the top bar as nothing outside admin routes.
   */
  public static function preRender(array $element): array {
    // A static render callback: the container is the only way in.
    $admin = \Drupal::service('router.admin_context')->isAdminRoute();
    CacheableMetadata::createFromRenderArray($element)->addCacheContexts(['route'])->applyTo($element);
    return $admin ? $element : ['#cache' => $element['#cache']];
  }

}
