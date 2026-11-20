<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\drubricks_core\Access\QueryAccess;
use Drupal\Core\Session\AccountInterface;
use Drupal\drubricks_examples_project_management\Access\ProjectAccess;

/**
 * Project membership in lists: entity queries, Views, counts, references.
 *
 * Core tags every access-checked entity query (and every View of an entity
 * type) with "ENTITY_TYPE_access" (see hook_query_TAG_alter() and
 * EntityViewsData). These hooks add the membership condition there, so the
 * sidebar counters, lists, boards, dashboard widgets, autocomplete and the
 * project field's options show only the user's projects and their tasks.
 * Single records are checked by the access control handlers with the same
 * rule (ProjectAccess). drubricks_core's QueryAccess finds the table (also in
 * revision queries), reads the account from the query and bubbles the "user"
 * cache context, like core's node access query alter.
 */
final class ProjectAccessHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly QueryAccess $queryAccess,
  ) {}

  /**
   * Implements hook_query_TAG_alter() for pm_project_access.
   */
  #[Hook('query_pm_project_access_alter')]
  public function queryProjectAccessAlter(AlterableInterface $query): void {
    $account = $this->queryAccess->account($query);
    if (!$query instanceof SelectInterface || ProjectAccess::bypasses($account)) {
      return;
    }
    $alias = $this->queryAccess->alias($query, 'pm_project');
    $query->condition($query->orConditionGroup()
      ->condition("$alias.uid", $account->id())
      ->condition("$alias.id", $this->memberProjects($account), 'IN'));
    $this->queryAccess->bubble(['user']);
  }

  /**
   * Implements hook_query_TAG_alter() for pm_task_access.
   */
  #[Hook('query_pm_task_access_alter')]
  public function queryTaskAccessAlter(AlterableInterface $query): void {
    $account = $this->queryAccess->account($query);
    if (!$query instanceof SelectInterface || ProjectAccess::bypasses($account)) {
      return;
    }
    $alias = $this->queryAccess->alias($query, 'pm_task');
    $owned = $this->database->select('pm_project', 'p')->fields('p', ['id'])->condition('p.uid', $account->id());
    $query->condition($query->orConditionGroup()
      ->condition("$alias.project", $owned, 'IN')
      ->condition("$alias.project", $this->memberProjects($account), 'IN'));
    $this->queryAccess->bubble(['user']);
  }

  /**
   * Subquery: ids of the projects the account is a member of.
   */
  private function memberProjects(AccountInterface $account): SelectInterface {
    $storage = $this->entityTypeManager->getStorage('pm_project');
    $table = $storage instanceof SqlEntityStorageInterface
      ? $storage->getTableMapping()->getFieldTableName('members')
      : 'pm_project__members';
    return $this->database->select($table, 'm')
      ->fields('m', ['entity_id'])
      ->condition('m.members_target_id', $account->id());
  }

}
