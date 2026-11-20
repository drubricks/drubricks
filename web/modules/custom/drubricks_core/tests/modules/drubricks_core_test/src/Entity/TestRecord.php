<?php

declare(strict_types=1);

namespace Drupal\drubricks_core_test\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drubricks_core\Access\DrubricksAccessControlHandler;
use Drupal\drubricks_core\Entity\DrubricksEntityBase;
use Drupal\drubricks_core\Form\DrubricksDeleteForm;
use Drupal\drubricks_core\Form\DrubricksEntityForm;
use Drupal\drubricks_core\Routing\DrubricksRouteProvider;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_core\Views\DrubricksViewsData;

/**
 * The smallest Drubricks entity: everything comes from the base class.
 */
#[ContentEntityType(
  id: 'drubricks_test_record',
  label: new TranslatableMarkup('Test record'),
  label_collection: new TranslatableMarkup('Test records'),
  label_singular: new TranslatableMarkup('test record'),
  label_plural: new TranslatableMarkup('test records'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'name',
    'owner' => 'uid',
    'published' => 'status',
  ],
  handlers: [
    'access' => DrubricksAccessControlHandler::class,
    'views_data' => DrubricksViewsData::class,
    'drubricks_screen' => ScreenHandler::class,
    'form' => [
      'add' => DrubricksEntityForm::class,
      'edit' => DrubricksEntityForm::class,
      'delete' => DrubricksDeleteForm::class,
    ],
    'route_provider' => ['html' => DrubricksRouteProvider::class],
  ],
  links: [
    'collection' => '/apps/test/records',
    'add-form' => '/apps/test/records/add',
    'canonical' => '/apps/test/records/{drubricks_test_record}',
    'edit-form' => '/apps/test/records/{drubricks_test_record}/edit',
    'delete-form' => '/apps/test/records/{drubricks_test_record}/delete',
    'drubricks-inspector' => '/apps/test/records/{drubricks_test_record}/info',
  ],
  // Its admin page (MODULE.routing.yml) carries the Field UI tabs.
  field_ui_base_route: 'entity.drubricks_test_record.settings',
  admin_permission: 'administer drubricks test records',
  base_table: 'drubricks_test_record',
)]
final class TestRecord extends DrubricksEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    // A multi-value choice list: it lives in its own table (Views tests).
    $fields['kinds'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Kinds'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('allowed_values', ['a' => 'A', 'b' => 'B']);
    return $fields;
  }

}
