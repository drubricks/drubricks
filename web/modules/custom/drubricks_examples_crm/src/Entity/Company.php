<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\link\LinkItemInterface;
use Drupal\drubricks_core\Access\DrubricksAccessControlHandler;
use Drupal\drubricks_core\Entity\DrubricksEntityBase;
use Drupal\drubricks_core\Form\DrubricksDeleteForm;
use Drupal\drubricks_core\Form\DrubricksEntityForm;
use Drupal\drubricks_core\Routing\DrubricksRouteProvider;
use Drupal\drubricks_core\Views\DrubricksViewsData;
use Drupal\drubricks_examples_crm\Screen\CompanyScreen;

/**
 * A company (account) in the CRM.
 */
#[ContentEntityType(
  id: 'crm_company',
  label: new TranslatableMarkup('Company'),
  label_collection: new TranslatableMarkup('Companies'),
  label_singular: new TranslatableMarkup('company'),
  label_plural: new TranslatableMarkup('companies'),
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
    'drubricks_screen' => CompanyScreen::class,
    'form' => [
      'add' => DrubricksEntityForm::class,
      'edit' => DrubricksEntityForm::class,
      'delete' => DrubricksDeleteForm::class,
    ],
    'route_provider' => ['html' => DrubricksRouteProvider::class],
  ],
  links: [
    'collection' => '/apps/crm/companies',
    'add-form' => '/apps/crm/companies/add',
    'canonical' => '/apps/crm/companies/{crm_company}',
    'edit-form' => '/apps/crm/companies/{crm_company}/edit',
    'delete-form' => '/apps/crm/companies/{crm_company}/delete',
    'drubricks-inspector' => '/apps/crm/companies/{crm_company}/info',
    // Confirmation of the bulk "Delete" of the admin view.
    'delete-multiple-form' => '/admin/app/entities/crm/companies/delete',
  ],
  // Its admin page (MODULE.routing.yml) carries the Field UI tabs.
  field_ui_base_route: 'entity.crm_company.settings',
  admin_permission: 'administer crm',
  base_table: 'crm_company',
  revision_table: 'crm_company_revision',
  label_count: [
    'singular' => '@count company',
    'plural' => '@count companies',
  ],
)]
class Company extends DrubricksEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['industry'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Industry'))
      ->setDisplayOptions('form', ['weight' => 0])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['city'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('City'))
      ->setDisplayOptions('form', ['weight' => 1])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['size'] = BaseFieldDefinition::create('integer')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Employees'))
      ->setSetting('min', 0)
      ->setDisplayOptions('form', ['weight' => 2])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['website'] = BaseFieldDefinition::create('link')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Website'))
      ->setSetting('title', 0)
      ->setSetting('link_type', LinkItemInterface::LINK_EXTERNAL)
      ->setDisplayOptions('form', ['type' => 'link_default', 'weight' => 3])
      ->setDisplayOptions('view', ['type' => 'link', 'label' => 'inline', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $color = $fields['color'];
    if ($color instanceof BaseFieldDefinition) {
      $color->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 10]);
    }
    return $fields;
  }

}
