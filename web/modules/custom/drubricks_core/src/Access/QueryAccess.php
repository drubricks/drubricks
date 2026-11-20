<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Access;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Helpers for hook_query_ENTITY_TYPE_access_alter() implementations.
 *
 * The same steps as core's node_query_node_access_alter()
 * (NodeDatabaseHooks): the account and operation from the query metadata,
 * the entity table in the query (base, data or revision table — or the
 * "base_table" metadata), and the cache context of the rule bubbled to the
 * render context, so cached lists vary by it.
 */
final class QueryAccess {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * The account the query runs for (metadata "account", else current user).
   */
  public function account(AlterableInterface $query): AccountInterface {
    $account = $query->getMetaData('account');
    return $account instanceof AccountInterface ? $account : $this->currentUser;
  }

  /**
   * The operation (metadata "op"): view, update or delete.
   */
  public function operation(AlterableInterface $query): string {
    return (string) ($query->getMetaData('op') ?: 'view');
  }

  /**
   * Alias of the entity's table in the query, to add conditions on.
   *
   * @throws \LogicException
   *   When the query has none of the entity's tables (like core, a query
   *   tagged for access on another table must name it in "base_table").
   */
  public function alias(AlterableInterface $query, string $entity_type_id): string {
    if (!$query instanceof SelectInterface) {
      throw new \LogicException("A $entity_type_id access query must be a select query.");
    }
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $type = $storage->getEntityType();
    $preferred = array_filter([$type->getDataTable(), $type->getBaseTable()]);
    $tables = $storage instanceof SqlEntityStorageInterface ? $storage->getTableMapping()->getTableNames() : $preferred;
    $base_table = $query->getMetaData('base_table');
    $found = NULL;
    foreach ($query->getTables() as $alias => $info) {
      $table = is_array($info) ? ($info['table'] ?? NULL) : NULL;
      if (!is_string($table)) {
        continue;
      }
      if ($base_table ? $table === $base_table : in_array($table, $preferred, TRUE)) {
        return (string) $alias;
      }
      // A revision table (allRevisions() / latestRevision() queries).
      if (!$base_table && $found === NULL && in_array($table, $tables, TRUE)) {
        $found = (string) $alias;
      }
    }
    return $found ?? throw new \LogicException("Query tagged {$entity_type_id}_access has no $entity_type_id table; set the base_table metadata.");
  }

  /**
   * Bubbles cache contexts of an access rule to the render context.
   *
   * @param string[] $contexts
   *   E.g. ['user'] for a rule that depends on who the user is.
   */
  public function bubble(array $contexts): void {
    if ($this->renderer->hasRenderContext()) {
      $build = ['#cache' => ['contexts' => $contexts]];
      $this->renderer->render($build);
    }
  }

}
