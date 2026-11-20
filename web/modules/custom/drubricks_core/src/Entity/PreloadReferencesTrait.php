<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Entity;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Loads the targets of a reference field of many records in one query.
 *
 * Call it before a loop that reads $entity->get($field)->entity, so the loop
 * hits the static entity cache instead of loading one record per row.
 * Needs $this->entityTypeManager.
 */
trait PreloadReferencesTrait {

  /**
   * Loads the entities referenced by $field of all $entities.
   *
   * @param iterable<mixed> $entities
   *   Records (entities without the field are skipped).
   * @param string $field
   *   An entity reference field.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The referenced entities, keyed by id.
   */
  protected function preloadReferences(iterable $entities, string $field): array {
    $ids = [];
    $target_type = NULL;
    foreach ($entities as $entity) {
      if ($entity instanceof FieldableEntityInterface && $entity->hasField($field)) {
        $target_type ??= $entity->getFieldDefinition($field)->getSetting('target_type');
        foreach ($entity->get($field) as $item) {
          if ($id = $item->target_id ?? NULL) {
            $ids[$id] = $id;
          }
        }
      }
    }
    if (!$target_type || !$ids) {
      return [];
    }
    return $this->entityTypeManager->getStorage($target_type)->loadMultiple($ids);
  }

}
