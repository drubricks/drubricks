<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerTrait;

/**
 * Base class for Drubricks content entities.
 *
 * Gives every record a label, owner, created / changed dates, a published
 * status and an optional colour (a kit token name). Subclasses add their own
 * fields with parent::baseFieldDefinitions() + their definitions.
 *
 * Required entity keys: id, uuid, label, owner, published.
 *
 * Revisions (recommended): declare a revision table, the entity key
 * 'revision' and the revision metadata keys (see the example entities) and
 * mark the subclass fields ->setRevisionable(TRUE). Then every save of an
 * existing record that changes it creates a new revision, made by the current
 * user (forms, board / calendar moves, API) — the previous versions stay.
 */
abstract class DrubricksEntityBase extends ContentEntityBase implements DrubricksEntityInterface, RevisionLogInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;
  use EntityPublishedTrait;
  use RevisionLogEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);
    $revisionable = $entity_type->isRevisionable();
    if ($revisionable) {
      $fields += static::revisionLogBaseFieldDefinitions($entity_type);
      // Revisions are kept silently: no log message in the (modal) forms.
      // An app can show it in its form display (the field is configurable).
      $log = $entity_type instanceof ContentEntityTypeInterface ? $fields[$entity_type->getRevisionMetadataKey('revision_log_message')] ?? NULL : NULL;
      if ($log instanceof BaseFieldDefinition) {
        $log->setDisplayOptions('form', ['region' => 'hidden']);
      }
    }

    if ($label_key = $entity_type->getKey('label')) {
      $fields[$label_key] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Name'))
        ->setRequired(TRUE)
        ->setSetting('max_length', 255)
        ->setRevisionable($revisionable)
        ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -10])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);
    }

    $owner = $fields[$entity_type->getKey('owner')];
    if ($owner instanceof BaseFieldDefinition) {
      $owner->setLabel(new TranslatableMarkup('Owner'))
        ->setRevisionable($revisionable)
        ->setDisplayOptions('view', ['type' => 'author', 'label' => 'inline', 'weight' => 90])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);
    }
    $published = $fields[$entity_type->getKey('published')];
    if ($published instanceof BaseFieldDefinition) {
      $published->setLabel(new TranslatableMarkup('Active'))
        ->setDisplayConfigurable('form', TRUE);
    }

    $fields['color'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Colour'))
      ->setDescription(new TranslatableMarkup('A colour of the UI kit palette.'))
      ->setSetting('allowed_values_function', [ColorTokens::class, 'options'])
      ->setRevisionable($revisionable)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setRevisionable($revisionable)
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setRevisionable($revisionable)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   *
   * On a revisionable entity type a changed record is saved as a new
   * revision, with the current user and time as its author and date. Content
   * syncs (imports, migrations) keep their own revision handling.
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getEntityType()->isRevisionable()) {
      return;
    }
    // The first revision is made by its creator; imported records (demo
    // content, migrations) by their owner.
    if ($this->isNew() && !$this->getRevisionUserId()) {
      $this->setRevisionUserId((int) ($this->isSyncing() ? $this->getOwnerId() : \Drupal::currentUser()->id()));
    }
    if ($this->isSyncing()) {
      return;
    }
    if (!$this->isNew() && !$this->isNewRevision() && $this->hasTranslationChanges()) {
      $this->setNewRevision();
      $original = $this->getOriginal();
      if ($original instanceof RevisionLogInterface && $this->getRevisionUserId() === $original->getRevisionUserId()) {
        $this->setRevisionUserId((int) \Drupal::currentUser()->id());
      }
      // A log message belongs to the revision it was written for.
      $type = $this->getEntityType();
      if ($type instanceof ContentEntityTypeInterface && $original instanceof RevisionLogInterface && $this->getRevisionLogMessage() === $original->getRevisionLogMessage()) {
        $this->set($type->getRevisionMetadataKey('revision_log_message'), NULL);
      }
    }
    if ($this->isNew() || $this->isNewRevision()) {
      $this->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    }
  }

  /**
   * {@inheritdoc}
   *
   * Like core's Node / Media: saving without a new revision keeps the
   * revision's log message instead of clearing it with an empty value.
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record): void {
    parent::preSaveRevision($storage, $record);
    $type = $this->getEntityType();
    $key = $type instanceof ContentEntityTypeInterface ? $type->getRevisionMetadataKey('revision_log_message') : NULL;
    $original = $this->getOriginal();
    if ($key && !$this->isNewRevision() && $original instanceof RevisionLogInterface && empty($record->{$key})) {
      // The record is already mapped from the entity: set it there.
      $record->{$key} = $original->getRevisionLogMessage();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getColor(): ?string {
    return $this->get('color')->value ?: NULL;
  }

}
