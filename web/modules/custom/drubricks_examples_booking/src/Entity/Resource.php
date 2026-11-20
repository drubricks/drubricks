<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drubricks_core\Access\DrubricksAccessControlHandler;
use Drupal\drubricks_core\Entity\DrubricksEntityBase;
use Drupal\drubricks_core\Form\DrubricksDeleteForm;
use Drupal\drubricks_core\Form\DrubricksEntityForm;
use Drupal\drubricks_core\Routing\DrubricksRouteProvider;
use Drupal\drubricks_core\Views\DrubricksViewsData;
use Drupal\drubricks_examples_booking\Screen\ResourceScreen;

/**
 * Something people book: a room, equipment, a car or a specialist.
 */
#[ContentEntityType(
  id: 'booking_resource',
  label: new TranslatableMarkup('Resource'),
  label_collection: new TranslatableMarkup('Resources'),
  label_singular: new TranslatableMarkup('resource'),
  label_plural: new TranslatableMarkup('resources'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'name',
    'owner' => 'uid',
    'published' => 'status',
    'revision' => 'revision_id',
  ],
  revision_metadata_keys: [
    'revision_user' => 'revision_user',
    'revision_created' => 'revision_created',
    'revision_log_message' => 'revision_log_message',
  ],
  handlers: [
    // Operations (edit / delete) in the admin view.
    'list_builder' => EntityListBuilder::class,
    'access' => DrubricksAccessControlHandler::class,
    'views_data' => DrubricksViewsData::class,
    'drubricks_screen' => ResourceScreen::class,
    'form' => [
      'add' => DrubricksEntityForm::class,
      'edit' => DrubricksEntityForm::class,
      'delete' => DrubricksDeleteForm::class,
    ],
    'route_provider' => ['html' => DrubricksRouteProvider::class],
  ],
  links: [
    'collection' => '/apps/booking/resources',
    'add-form' => '/apps/booking/resources/add',
    'canonical' => '/apps/booking/resources/{booking_resource}',
    'edit-form' => '/apps/booking/resources/{booking_resource}/edit',
    'delete-form' => '/apps/booking/resources/{booking_resource}/delete',
    'drubricks-inspector' => '/apps/booking/resources/{booking_resource}/info',
    // Confirmation of the bulk "Delete" of the admin view.
    'delete-multiple-form' => '/admin/app/entities/booking/resources/delete',
  ],
  // Its admin page (MODULE.routing.yml) carries the Field UI tabs.
  field_ui_base_route: 'entity.booking_resource.settings',
  admin_permission: 'administer booking',
  base_table: 'booking_resource',
  revision_table: 'booking_resource_revision',
)]
class Resource extends DrubricksEntityBase {

  /**
   * Kit icon of each kind of resource.
   */
  public const KIND_ICONS = [
    'room' => 'house',
    'equipment' => 'keyboard',
    'car' => 'send',
    'specialist' => 'person',
  ];

  /**
   * Kinds of resources: the options of the kind field.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by kind.
   */
  public static function kinds(): array {
    return [
      'room' => new TranslatableMarkup('Room'),
      'equipment' => new TranslatableMarkup('Equipment'),
      'car' => new TranslatableMarkup('Car'),
      'specialist' => new TranslatableMarkup('Specialist'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['kind'] = BaseFieldDefinition::create('list_string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Kind'))
      ->setRequired(TRUE)
      ->setDefaultValue('room')
      ->setSetting('allowed_values_function', [self::class, 'kinds'])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 0])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['location'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Location'))
      ->setDisplayOptions('form', ['weight' => 1])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['capacity'] = BaseFieldDefinition::create('integer')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Capacity'))
      ->setDescription(new TranslatableMarkup('Seats; 0 for equipment.'))
      ->setSetting('min', 0)
      ->setDefaultValue(0)
      ->setDisplayOptions('form', ['weight' => 2])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['opens'] = BaseFieldDefinition::create('integer')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Opens at (hour)'))
      ->setSettings(['min' => 0, 'max' => 23])
      ->setRequired(TRUE)
      ->setDefaultValue(8)
      ->setDisplayOptions('form', ['weight' => 3])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['closes'] = BaseFieldDefinition::create('integer')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Closes at (hour)'))
      ->setSettings(['min' => 1, 'max' => 24])
      ->setRequired(TRUE)
      ->setDefaultValue(18)
      ->setDisplayOptions('form', ['weight' => 4])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['rules'] = BaseFieldDefinition::create('string_long')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Rules'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 5])
      ->setDisplayOptions('view', ['label' => 'above', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $color = $fields['color'];
    if ($color instanceof BaseFieldDefinition) {
      $color->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 6]);
    }
    return $fields;
  }

  /**
   * Opening hour.
   */
  public function opens(): int {
    return (int) $this->get('opens')->value;
  }

  /**
   * Closing hour.
   */
  public function closes(): int {
    return (int) $this->get('closes')->value;
  }

}
