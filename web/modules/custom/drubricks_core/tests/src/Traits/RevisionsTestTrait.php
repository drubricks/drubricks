<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Traits;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks of revisionable Drubricks entities, for app kernel tests.
 *
 * @code
 * $this->assertRevisionable('crm_contact');
 * $this->assertEditCreatesRevision($contact, 'email', 'new@example.com', $editor);
 * @endcode
 */
trait RevisionsTestTrait {

  /**
   * The entity type keeps revisions of every stored field.
   */
  protected function assertRevisionable(string $entity_type_id): void {
    $entity_type = $this->container->get('entity_type.manager')->getDefinition($entity_type_id);
    $this->assertTrue($entity_type->isRevisionable(), "$entity_type_id is revisionable");
    $keys = array_filter(array_map(
      static fn (string $key) => $entity_type->getKey($key),
      ['id', 'uuid', 'revision', 'langcode'],
    ));
    $definitions = $this->container->get('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
    foreach ($definitions as $name => $definition) {
      if (!in_array($name, $keys, TRUE) && !$definition->hasCustomStorage()) {
        $this->assertTrue($definition->isRevisionable(), "$entity_type_id field $name is revisionable (->setRevisionable(TRUE))");
      }
    }
  }

  /**
   * An edit saves a new revision by the editor; the old one stays.
   *
   * A save without changes creates no revision.
   */
  protected function assertEditCreatesRevision(ContentEntityInterface $entity, string $field, mixed $value, AccountInterface $editor): void {
    $type = $entity->getEntityType();
    $this->assertInstanceOf(ContentEntityTypeInterface::class, $type);
    $storage = $this->container->get('entity_type.manager')->getStorage($type->id());
    $this->assertInstanceOf(RevisionableStorageInterface::class, $storage);
    if ($entity->isNew()) {
      $entity->save();
    }
    $first = $entity->getRevisionId();
    // As stored (an integer field reads back as a string), like the revision.
    $old = $storage->loadUnchanged($entity->id())?->get($field)->getValue();
    $this->assertSame(1, $this->countRevisions($entity));

    $this->container->get('current_user')->setAccount($editor);
    $edited = $storage->loadUnchanged($entity->id());
    $this->assertInstanceOf(ContentEntityInterface::class, $edited);
    $edited->set($field, $value)->save();
    $this->assertNotEquals($first, $edited->getRevisionId(), 'An edit creates a new revision.');
    $this->assertSame(2, $this->countRevisions($edited));
    $author = $edited->get($type->getRevisionMetadataKey('revision_user'))->target_id;
    $this->assertSame((int) $editor->id(), (int) $author, 'The editor is the revision author.');
    $previous = $storage->loadRevision($first);
    $this->assertInstanceOf(ContentEntityInterface::class, $previous);
    $this->assertSame($old, $previous->get($field)->getValue(), 'The previous revision keeps the old value.');

    $storage->loadUnchanged($entity->id())?->save();
    $this->assertSame(2, $this->countRevisions($edited), 'A save without changes creates no revision.');
  }

  /**
   * Number of revisions of a record.
   */
  private function countRevisions(ContentEntityInterface $entity): int {
    $type = $entity->getEntityType();
    return (int) $this->container->get('entity_type.manager')->getStorage($type->id())->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition($type->getKey('id'), $entity->id())
      ->count()
      ->execute();
  }

}
