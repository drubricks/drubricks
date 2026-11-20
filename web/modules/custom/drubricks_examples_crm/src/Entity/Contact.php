<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Entity;

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
use Drupal\drubricks_examples_crm\Screen\ContactScreen;

/**
 * A person in the CRM, optionally working at a company.
 */
#[ContentEntityType(
  id: 'crm_contact',
  label: new TranslatableMarkup('Contact'),
  label_collection: new TranslatableMarkup('Contacts'),
  label_singular: new TranslatableMarkup('contact'),
  label_plural: new TranslatableMarkup('contacts'),
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
    'drubricks_screen' => ContactScreen::class,
    'form' => [
      'add' => DrubricksEntityForm::class,
      'edit' => DrubricksEntityForm::class,
      'delete' => DrubricksDeleteForm::class,
    ],
    'route_provider' => ['html' => DrubricksRouteProvider::class],
  ],
  links: [
    'collection' => '/apps/crm/contacts',
    'add-form' => '/apps/crm/contacts/add',
    'canonical' => '/apps/crm/contacts/{crm_contact}',
    'edit-form' => '/apps/crm/contacts/{crm_contact}/edit',
    'delete-form' => '/apps/crm/contacts/{crm_contact}/delete',
    'drubricks-inspector' => '/apps/crm/contacts/{crm_contact}/info',
    // Confirmation of the bulk "Delete" of the admin view.
    'delete-multiple-form' => '/admin/app/entities/crm/contacts/delete',
  ],
  // Its admin page (MODULE.routing.yml) carries the Field UI tabs.
  field_ui_base_route: 'entity.crm_contact.settings',
  admin_permission: 'administer crm',
  base_table: 'crm_contact',
  revision_table: 'crm_contact_revision',
  label_count: [
    'singular' => '@count contact',
    'plural' => '@count contacts',
  ],
)]
class Contact extends DrubricksEntityBase {

  /**
   * Colour token of each stage (pills).
   */
  public const STAGE_COLORS = [
    'lead' => 'gray',
    'prospect' => 'blue',
    'customer' => 'green',
  ];

  /**
   * Lifecycle stages of a contact.
   */
  public static function stages(): array {
    return [
      'lead' => new TranslatableMarkup('Lead'),
      'prospect' => new TranslatableMarkup('Prospect'),
      'customer' => new TranslatableMarkup('Customer'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['job_title'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Job title'))
      ->setDisplayOptions('form', ['weight' => 0])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['company'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Company'))
      ->setSetting('target_type', 'crm_company')
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 1])
      ->setDisplayOptions('view', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['stage'] = BaseFieldDefinition::create('list_string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Stage'))
      ->setRequired(TRUE)
      ->setDefaultValue('lead')
      ->setSetting('allowed_values_function', [self::class, 'stages'])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 2])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['email'] = BaseFieldDefinition::create('email')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Email'))
      ->setDisplayOptions('form', ['weight' => 3])
      ->setDisplayOptions('view', ['type' => 'email_mailto', 'label' => 'inline', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['phone'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Phone'))
      ->setSetting('max_length', 40)
      ->setDisplayOptions('form', ['weight' => 4])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['last_contacted'] = BaseFieldDefinition::create('datetime')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Last contacted'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', ['type' => 'datetime_default', 'weight' => 5])
      ->setDisplayOptions('view', [
        'type' => 'datetime_custom',
        'label' => 'inline',
        'weight' => 5,
        'settings' => ['date_format' => 'j M Y'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    return $fields;
  }

  /**
   * The company, or NULL.
   */
  public function getCompany(): ?Company {
    $company = $this->get('company')->entity;
    return $company instanceof Company ? $company : NULL;
  }

}
