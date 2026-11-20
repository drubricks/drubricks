<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Hook;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\drubricks_core\Access\DrubricksAccessControlHandler;
use Drupal\drubricks_core\Access\QueryAccess;

/**
 * The own / any permissions in lists of Drubricks records.
 *
 * Core tags every access-checked entity query and every View of an entity
 * type with "ENTITY_TYPE_access". For each Drubricks entity type (access
 * handler DrubricksAccessControlHandler) the query gets the same rule as
 * single records: everything with the admin permission or "view any X", the
 * user's own records with "view own X", nothing otherwise. So lists, counters,
 * boards, calendars, dashboard widgets, Views, autocomplete and reference
 * options follow the permissions without code of their own.
 */
final class RecordAccessHooks {

  /**
   * Entity types with the own / any rule, keyed by ID (built once).
   *
   * @var array<string, \Drupal\Core\Entity\EntityTypeInterface>|null
   */
  private ?array $types = NULL;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly QueryAccess $queryAccess,
  ) {}

  /**
   * Implements hook_query_alter() for the ENTITY_TYPE_access tags.
   */
  #[Hook('query_alter')]
  public function queryAlter(AlterableInterface $query): void {
    if (!$query instanceof SelectInterface) {
      return;
    }
    foreach ($this->types() as $id => $type) {
      if ($query->hasTag($id . '_access')) {
        $this->limit($query, $type);
      }
    }
  }

  /**
   * Adds the own / any condition of one entity type.
   */
  private function limit(SelectInterface $query, EntityTypeInterface $type): void {
    $account = $this->queryAccess->account($query);
    $verb = DrubricksAccessControlHandler::OPERATIONS[$this->queryAccess->operation($query)] ?? 'view';
    $id = $type->id();
    $admin = $type->getAdminPermission();
    $this->queryAccess->bubble(['user.permissions']);
    if (($admin && $account->hasPermission($admin)) || $account->hasPermission("$verb any $id")) {
      return;
    }
    $alias = $this->queryAccess->alias($query, $id);
    if (!$account->hasPermission("$verb own $id")) {
      $query->alwaysFalse();
      return;
    }
    $storage = $this->entityTypeManager->getStorage($id);
    $owner = $type->getKey('owner');
    $column = $storage instanceof SqlEntityStorageInterface
      ? $storage->getTableMapping()->getFieldColumnName($this->entityFieldManager->getFieldStorageDefinitions($id)[$owner], 'target_id')
      : $owner;
    $query->condition("$alias.$column", $account->id());
    $this->queryAccess->bubble(['user']);
  }

  /**
   * The entity types with an owner and the Drubricks access handler.
   *
   * @return array<string, \Drupal\Core\Entity\EntityTypeInterface>
   *   Entity types by ID.
   */
  private function types(): array {
    return $this->types ??= array_filter(
      $this->entityTypeManager->getDefinitions(),
      static fn (EntityTypeInterface $type) => $type->hasKey('owner')
        && is_a((string) $type->getHandlerClass('access'), DrubricksAccessControlHandler::class, TRUE),
    );
  }

}
