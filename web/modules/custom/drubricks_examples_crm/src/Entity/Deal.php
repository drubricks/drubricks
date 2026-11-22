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
use Drupal\drubricks_core\Routing\DrubricksRouteProvider;
use Drupal\drubricks_core\Views\DrubricksViewsData;
use Drupal\drubricks_examples_crm\Form\DealForm;
use Drupal\drubricks_examples_crm\Screen\DealScreen;

/**
 * A sales opportunity with a company.
 */
#[ContentEntityType(
  id: 'crm_deal',
  label: new TranslatableMarkup('Deal'),
  label_collection: new TranslatableMarkup('Deals'),
  label_singular: new TranslatableMarkup('deal'),
  label_plural: new TranslatableMarkup('deals'),
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
    'drubricks_screen' => DealScreen::class,
    'form' => [
      'add' => DealForm::class,
      'edit' => DealForm::class,
      'delete' => DrubricksDeleteForm::class,
    ],
    'route_provider' => ['html' => DrubricksRouteProvider::class],
  ],
  links: [
    // The board is the main view of deals; the list lives under /list.
    'drubricks-board' => '/apps/crm/deals',
    'collection' => '/apps/crm/deals/list',
    // A core View (views.view.crm_deal_table) in the app: filters, bulk form.
    'drubricks-table' => '/apps/crm/deals/table',
    // A core View with the Chart format (views.view.crm_deal_chart): the value
    // per close month and stage, with filters.
    'drubricks-chart' => '/apps/crm/deals/chart',
    'add-form' => '/apps/crm/deals/add',
    'canonical' => '/apps/crm/deals/{crm_deal}',
    'edit-form' => '/apps/crm/deals/{crm_deal}/edit',
    'delete-form' => '/apps/crm/deals/{crm_deal}/delete',
    'drubricks-inspector' => '/apps/crm/deals/{crm_deal}/info',
    // Confirmation of the bulk "Delete" of the admin view.
    'delete-multiple-form' => '/admin/app/entities/crm/deals/delete',
  ],
  // Its admin page (MODULE.routing.yml) carries the Field UI tabs.
  field_ui_base_route: 'entity.crm_deal.settings',
  admin_permission: 'administer crm',
  base_table: 'crm_deal',
  revision_table: 'crm_deal_revision',
  label_count: [
    'singular' => '@count deal',
    'plural' => '@count deals',
  ],
  constraints: [
    'CrmDealClosed' => [],
    'CrmDealContactCompany' => [],
  ],
)]
class Deal extends DrubricksEntityBase {

  /**
   * Kit colour token of each pipeline stage (lanes, pills).
   */
  public const STAGE_COLORS = [
    'lead' => 'gray',
    'qualified' => 'blue',
    'proposal' => 'purple',
    'negotiation' => 'orange',
    'won' => 'green',
    'lost' => 'red',
  ];

  /**
   * Pipeline stages: the options of the stage field, in pipeline order.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by stage.
   */
  public static function stages(): array {
    return [
      'lead' => new TranslatableMarkup('Lead'),
      'qualified' => new TranslatableMarkup('Qualified'),
      'proposal' => new TranslatableMarkup('Proposal'),
      'negotiation' => new TranslatableMarkup('Negotiation'),
      'won' => new TranslatableMarkup('Won'),
      'lost' => new TranslatableMarkup('Lost'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['company'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Company'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'crm_company')
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 0])
      ->setDisplayOptions('view', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['contact'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Contact'))
      ->setSetting('target_type', 'crm_contact')
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 1])
      ->setDisplayOptions('view', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['value'] = BaseFieldDefinition::create('decimal')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Value'))
      ->setSetting('precision', 12)
      ->setSetting('scale', 2)
      ->setSetting('min', 0)
      ->setDisplayOptions('form', ['weight' => 2])
      ->setDisplayOptions('view', [
        'type' => 'number_decimal',
        'label' => 'inline',
        'weight' => 2,
        'settings' => ['scale' => 0, 'thousand_separator' => ' '],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['stage'] = BaseFieldDefinition::create('list_string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Stage'))
      ->setRequired(TRUE)
      ->setDefaultValue('lead')
      ->setSetting('allowed_values_function', [self::class, 'stages'])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 3])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['close_date'] = BaseFieldDefinition::create('datetime')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Close date'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', ['type' => 'datetime_default', 'weight' => 4])
      ->setDisplayOptions('view', [
        'type' => 'datetime_custom',
        'label' => 'inline',
        'weight' => 4,
        'settings' => ['date_format' => 'j M Y'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    return $fields;
  }

  /**
   * Stage key.
   */
  public function getStage(): string {
    return (string) $this->get('stage')->value;
  }

  /**
   * Whether the deal is closed (won or lost).
   */
  public function isClosed(): bool {
    return in_array($this->getStage(), ['won', 'lost'], TRUE);
  }

}
