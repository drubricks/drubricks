<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Access;

use Drupal\Core\Entity\Query\QueryAggregateInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Entity queries limited to the records an account may see.
 *
 * Access-checked queries for the given account (query metadata "account"):
 * the ENTITY_TYPE_access query alters apply the rules — own / any
 * (RecordAccessHooks) and an app's own, e.g. project membership. Needs
 * $this->entityTypeManager.
 */
trait AccessibleRecordsTrait {

  /**
   * An access-checked query of the records the account may see.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface|null
   *   The query, or NULL when the entity type does not exist.
   */
  protected function accessibleQuery(string $entity_type_id, AccountInterface $account): ?QueryInterface {
    if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return NULL;
    }
    return $this->entityTypeManager->getStorage($entity_type_id)->getQuery()
      ->accessCheck(TRUE)
      ->addMetaData('account', $account);
  }

  /**
   * The same as accessibleQuery(), as an aggregate query (sums, group by).
   */
  protected function accessibleAggregateQuery(string $entity_type_id, AccountInterface $account): ?QueryAggregateInterface {
    if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return NULL;
    }
    return $this->entityTypeManager->getStorage($entity_type_id)->getAggregateQuery()
      ->accessCheck(TRUE)
      ->addMetaData('account', $account);
  }

}
