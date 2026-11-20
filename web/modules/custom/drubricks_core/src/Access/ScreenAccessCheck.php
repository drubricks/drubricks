<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Access to the screen endpoints of any entity type ({entity_type_id} routes).
 *
 * Any of view own / view any / create / admin of the entity type named by the
 * route parameter. Routes of one entity type use core's
 * `_permission: 'a+b+c'` instead (DrubricksRouteProvider).
 */
final class ScreenAccessCheck implements AccessInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Checks access.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $id = $route->getRequirement('_drubricks_screen_access');
    // 'entity_type_id': the entity type comes from that route parameter.
    if ($id === 'entity_type_id') {
      $id = (string) $route_match->getRawParameter('entity_type_id');
    }
    $type = $id ? $this->entityTypeManager->getDefinition($id, FALSE) : NULL;
    if (!$type) {
      return AccessResult::forbidden();
    }
    $permissions = array_filter([$type->getAdminPermission(), "view any $id", "view own $id", "create $id"]);
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

}
