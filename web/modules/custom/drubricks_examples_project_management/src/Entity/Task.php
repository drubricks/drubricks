<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drubricks_examples_project_management\Access\TaskAccessControlHandler;
use Drupal\drubricks_core\Entity\DrubricksEntityBase;
use Drupal\drubricks_core\Form\DrubricksDeleteForm;
use Drupal\drubricks_core\Form\DrubricksEntityForm;
use Drupal\drubricks_core\Routing\DrubricksRouteProvider;
use Drupal\drubricks_core\Views\DrubricksViewsData;
use Drupal\drubricks_examples_project_management\Screen\TaskScreen;

/**
 * A task of a project; its status is the lane on the task board.
 */
#[ContentEntityType(
  id: 'pm_task',
  label: new TranslatableMarkup('Task'),
  label_collection: new TranslatableMarkup('Tasks'),
  label_singular: new TranslatableMarkup('task'),
  label_plural: new TranslatableMarkup('tasks'),
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
    // Members of the task's project only (admins: all); lists: ProjectAccessHooks.
    'access' => TaskAccessControlHandler::class,
    'views_data' => DrubricksViewsData::class,
    'drubricks_screen' => TaskScreen::class,
    'form' => [
      'add' => DrubricksEntityForm::class,
      'edit' => DrubricksEntityForm::class,
      'delete' => DrubricksDeleteForm::class,
    ],
    'route_provider' => ['html' => DrubricksRouteProvider::class],
  ],
  links: [
    'collection' => '/apps/pm/tasks',
    'drubricks-board' => '/apps/pm/board',
    'add-form' => '/apps/pm/tasks/add',
    'canonical' => '/apps/pm/tasks/{pm_task}',
    'edit-form' => '/apps/pm/tasks/{pm_task}/edit',
    'delete-form' => '/apps/pm/tasks/{pm_task}/delete',
    'drubricks-inspector' => '/apps/pm/tasks/{pm_task}/info',
    // Confirmation of the bulk "Delete" of the admin view.
    'delete-multiple-form' => '/admin/app/entities/pm/tasks/delete',
  ],
  // Its admin page (MODULE.routing.yml) carries the Field UI tabs.
  field_ui_base_route: 'entity.pm_task.settings',
  admin_permission: 'administer project management',
  base_table: 'pm_task',
  revision_table: 'pm_task_revision',
  // Saving a task changes project lists too (task counts and progress).
  list_cache_tags: ['pm_task_list', 'pm_project_list'],
)]
class Task extends DrubricksEntityBase {

  /**
   * Kit colour token of each task status (board lanes, pills).
   */
  public const STATUS_COLORS = [
    'todo' => 'gray',
    'doing' => 'blue',
    'review' => 'purple',
    'done' => 'green',
  ];

  /**
   * Kit icon of each priority.
   */
  public const PRIORITY_ICONS = [
    'low' => 'prio-low',
    'medium' => 'prio-medium',
    'high' => 'prio-high',
  ];

  /**
   * Task statuses (board lanes): the options of the status field.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by status.
   */
  public static function statuses(): array {
    return [
      'todo' => new TranslatableMarkup('To do'),
      'doing' => new TranslatableMarkup('In progress'),
      'review' => new TranslatableMarkup('Review'),
      'done' => new TranslatableMarkup('Done'),
    ];
  }

  /**
   * Priorities: the options of the priority field.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by priority.
   */
  public static function priorities(): array {
    return [
      'low' => new TranslatableMarkup('Low'),
      'medium' => new TranslatableMarkup('Medium'),
      'high' => new TranslatableMarkup('High'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['project'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Project'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'pm_project')
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 0])
      ->setDisplayOptions('view', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['state'] = BaseFieldDefinition::create('list_string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue('todo')
      ->setSetting('allowed_values_function', [self::class, 'statuses'])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 1])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['priority'] = BaseFieldDefinition::create('list_string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Priority'))
      ->setRequired(TRUE)
      ->setDefaultValue('medium')
      ->setSetting('allowed_values_function', [self::class, 'priorities'])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 2])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['assignee'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Assignee'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 3])
      ->setDisplayOptions('view', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['due_date'] = BaseFieldDefinition::create('datetime')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Due date'))
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
    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Order on the board'))
      ->setDefaultValue(0);
    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Description'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 5])
      ->setDisplayOptions('view', ['label' => 'above', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    return $fields;
  }

  /**
   * Status key.
   */
  public function getState(): string {
    return (string) $this->get('state')->value;
  }

}
