<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\drubricks_examples_project_management\Entity\Project;

/**
 * Who belongs to which project — the one place the membership rule lives.
 *
 * A user "is in" a project when they are its owner or one of its members.
 * Users with "administer project management" see every project and task.
 * Used by the access handlers (one record) and the query alter hooks (lists,
 * counts, Views, reference fields), so both always agree.
 */
final class ProjectAccess {

  /**
   * The permission that bypasses membership.
   */
  public const ADMIN_PERMISSION = 'administer project management';

  /**
   * Whether the account sees every project regardless of membership.
   */
  public static function bypasses(AccountInterface $account): bool {
    return $account->hasPermission(self::ADMIN_PERMISSION);
  }

  /**
   * Whether the account is the owner or a member of a project.
   */
  public static function isMember(Project $project, AccountInterface $account): bool {
    if ((int) $project->getOwnerId() === (int) $account->id()) {
      return TRUE;
    }
    $members = array_map('intval', array_column($project->get('members')->getValue(), 'target_id'));
    return in_array((int) $account->id(), $members, TRUE);
  }

}
