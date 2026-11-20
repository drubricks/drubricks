<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drubricks_core\Access\DrubricksAccessControlHandler;
use Drupal\drubricks_examples_project_management\Entity\Project;
use Drupal\drubricks_examples_project_management\Entity\Task;

/**
 * Tasks are visible only to members of their project (admins: all).
 *
 * On top of the own / any permissions the user must be in the task's
 * project. Creating a task in a project the user is not in is refused by
 * core's ValidReference constraint: the project selection runs an
 * access-checked query, which ProjectAccessHooks narrows to the user's
 * projects.
 */
final class TaskAccessControlHandler extends DrubricksAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    $permissions = parent::checkAccess($entity, $operation, $account);
    if (ProjectAccess::bypasses($account) || !$entity instanceof Task) {
      return $permissions;
    }
    $project = $entity->get('project')->entity;
    $membership = AccessResult::allowedIf($project instanceof Project && ProjectAccess::isMember($project, $account))
      ->cachePerUser()
      ->addCacheableDependency($entity);
    if ($project instanceof Project) {
      $membership->addCacheableDependency($project);
    }
    return $permissions->isAllowed()
      ? $membership->andIf($permissions)
      : $permissions->andIf($membership);
  }

}
