<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drubricks_core\Access\DrubricksAccessControlHandler;
use Drupal\drubricks_examples_project_management\Entity\Project;

/**
 * Projects are visible to their owner and members only (admins: all).
 *
 * On top of the generated own / any permissions, membership is required:
 * "view any pm_project" means "every project I am in". The same rule is
 * applied to lists by ProjectAccessHooks::queryProjectAccessAlter().
 */
final class ProjectAccessControlHandler extends DrubricksAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    $permissions = parent::checkAccess($entity, $operation, $account);
    if (ProjectAccess::bypasses($account) || !$entity instanceof Project) {
      return $permissions;
    }
    $membership = AccessResult::allowedIf(ProjectAccess::isMember($entity, $account))
      ->cachePerUser()
      ->addCacheableDependency($entity);
    // Both are needed: a permission for the operation and membership.
    return $permissions->isAllowed()
      ? $membership->andIf($permissions)
      : $permissions->andIf($membership);
  }

}
