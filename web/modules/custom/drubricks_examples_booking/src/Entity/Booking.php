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
use Drupal\drubricks_examples_booking\Screen\BookingScreen;

/**
 * A booking of a resource for a time slot; the owner is who booked it.
 */
#[ContentEntityType(
  id: 'booking',
  label: new TranslatableMarkup('Booking'),
  label_collection: new TranslatableMarkup('Bookings'),
  label_singular: new TranslatableMarkup('booking'),
  label_plural: new TranslatableMarkup('bookings'),
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
    'drubricks_screen' => BookingScreen::class,
    'form' => [
      'add' => DrubricksEntityForm::class,
      'edit' => DrubricksEntityForm::class,
      'delete' => DrubricksDeleteForm::class,
    ],
    'route_provider' => ['html' => DrubricksRouteProvider::class],
  ],
  links: [
    // The calendar is the main view; the list lives under /list.
    'drubricks-calendar' => '/apps/booking',
    'collection' => '/apps/booking/list',
    'add-form' => '/apps/booking/add',
    'canonical' => '/apps/booking/{booking}',
    'edit-form' => '/apps/booking/{booking}/edit',
    'delete-form' => '/apps/booking/{booking}/delete',
    'drubricks-inspector' => '/apps/booking/{booking}/info',
    // Confirmation of the bulk "Delete" of the admin view.
    'delete-multiple-form' => '/admin/app/entities/booking/bookings/delete',
  ],
  // Its admin page (MODULE.routing.yml) carries the Field UI tabs.
  field_ui_base_route: 'entity.booking.settings',
  admin_permission: 'administer booking',
  base_table: 'booking',
  revision_table: 'booking_revision',
  constraints: [
    'BookingSlot' => [],
  ],
)]
class Booking extends DrubricksEntityBase {

  /**
   * Kit colour token of each booking state.
   */
  public const STATE_COLORS = [
    'confirmed' => 'green',
    'pending' => 'orange',
    'cancelled' => 'gray',
  ];

  /**
   * Booking states: the options of the state field.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by state.
   */
  public static function states(): array {
    return [
      'confirmed' => new TranslatableMarkup('Confirmed'),
      'pending' => new TranslatableMarkup('Pending approval'),
      'cancelled' => new TranslatableMarkup('Cancelled'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $name = $fields['name'];
    if ($name instanceof BaseFieldDefinition) {
      $name->setLabel(new TranslatableMarkup('Title'));
    }
    $fields['resource'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Resource'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'booking_resource')
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 0])
      ->setDisplayOptions('view', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    foreach (['starts' => new TranslatableMarkup('Starts'), 'ends' => new TranslatableMarkup('Ends')] as $name => $label) {
      $fields[$name] = BaseFieldDefinition::create('datetime')
        ->setRevisionable(TRUE)
        ->setLabel($label)
        ->setRequired(TRUE)
        ->setSetting('datetime_type', 'datetime')
        ->setDisplayOptions('form', ['type' => 'datetime_default', 'weight' => $name === 'starts' ? 1 : 2])
        ->setDisplayOptions('view', [
          'type' => 'datetime_custom',
          'label' => 'inline',
          'weight' => $name === 'starts' ? 1 : 2,
          'settings' => ['date_format' => 'D j M Y, H:i'],
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);
    }
    $fields['state'] = BaseFieldDefinition::create('list_string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue('confirmed')
      ->setSetting('allowed_values_function', [self::class, 'states'])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 3])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['attendees'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Attendees'))
      ->setSetting('max_length', 512)
      ->setDisplayOptions('form', ['weight' => 4])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Notes'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 5])
      ->setDisplayOptions('view', ['label' => 'above', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    return $fields;
  }

  /**
   * The booked resource, or NULL.
   */
  public function getResource(): ?Resource {
    $resource = $this->get('resource')->entity;
    return $resource instanceof Resource ? $resource : NULL;
  }

  /**
   * Whether the booking blocks the slot (not cancelled).
   */
  public function isActive(): bool {
    return $this->get('state')->value !== 'cancelled';
  }

}
