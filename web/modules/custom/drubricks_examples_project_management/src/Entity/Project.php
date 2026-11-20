<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\drubricks_examples_project_management\Access\ProjectAccessControlHandler;
use Drupal\drubricks_core\Entity\DrubricksEntityBase;
use Drupal\drubricks_core\Form\DrubricksDeleteForm;
use Drupal\drubricks_core\Form\DrubricksEntityForm;
use Drupal\drubricks_core\Routing\DrubricksRouteProvider;
use Drupal\drubricks_core\Views\DrubricksViewsData;
use Drupal\drubricks_examples_project_management\Screen\ProjectScreen;

/**
 * A project: groups tasks, has a status and a due date.
 */
#[ContentEntityType(
  id: 'pm_project',
  label: new TranslatableMarkup('Project'),
  label_collection: new TranslatableMarkup('Projects'),
  label_singular: new TranslatableMarkup('project'),
  label_plural: new TranslatableMarkup('projects'),
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
    // Owner + members only (admins: all); lists: ProjectAccessHooks.
    'access' => ProjectAccessControlHandler::class,
    'views_data' => DrubricksViewsData::class,
    'drubricks_screen' => ProjectScreen::class,
    'form' => [
      'add' => DrubricksEntityForm::class,
      'edit' => DrubricksEntityForm::class,
      'delete' => DrubricksDeleteForm::class,
    ],
    'route_provider' => ['html' => DrubricksRouteProvider::class],
  ],
  links: [
    'collection' => '/apps/pm/projects',
    'add-form' => '/apps/pm/projects/add',
    'canonical' => '/apps/pm/projects/{pm_project}',
    'edit-form' => '/apps/pm/projects/{pm_project}/edit',
    'delete-form' => '/apps/pm/projects/{pm_project}/delete',
    'drubricks-inspector' => '/apps/pm/projects/{pm_project}/info',
    // Confirmation of the bulk "Delete" of the admin view.
    'delete-multiple-form' => '/admin/app/entities/pm/projects/delete',
  ],
  // Its admin page (MODULE.routing.yml) carries the Field UI tabs.
  field_ui_base_route: 'entity.pm_project.settings',
  admin_permission: 'administer project management',
  base_table: 'pm_project',
  revision_table: 'pm_project_revision',
  // Saving a project changes task lists too (its members decide who sees
  // its tasks).
  list_cache_tags: ['pm_project_list', 'pm_task_list'],
)]
class Project extends DrubricksEntityBase {

  /**
   * Kit colour token of each project status.
   */
  public const STATUS_COLORS = [
    'planned' => 'gray',
    'active' => 'blue',
    'on_hold' => 'orange',
    'done' => 'green',
  ];

  /**
   * Project statuses: the options of the status field.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by status.
   */
  public static function statuses(): array {
    return [
      'planned' => new TranslatableMarkup('Planned'),
      'active' => new TranslatableMarkup('Active'),
      'on_hold' => new TranslatableMarkup('On hold'),
      'done' => new TranslatableMarkup('Done'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['state'] = BaseFieldDefinition::create('list_string')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue('planned')
      ->setSetting('allowed_values_function', [self::class, 'statuses'])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 0])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['due_date'] = BaseFieldDefinition::create('datetime')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Due date'))
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', ['type' => 'datetime_default', 'weight' => 1])
      ->setDisplayOptions('view', [
        'type' => 'datetime_custom',
        'label' => 'inline',
        'weight' => 1,
        'settings' => ['date_format' => 'j M Y'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['members'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Members'))
      ->setDescription(new TranslatableMarkup('People who see the project and its tasks. The owner is always a member.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler_settings', ['include_anonymous' => FALSE])
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      // People one under another with an "Add a person" search (drubricks_core
      // ReferenceListWidget); no legacy-AJAX "Add another item" button.
      ->setDisplayOptions('form', ['type' => 'drubricks_reference_list', 'weight' => 2])
      ->setDisplayOptions('view', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Description'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 2])
      ->setDisplayOptions('view', ['label' => 'above', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    return $fields;
  }

  /**
   * {@inheritdoc}
   *
   * The owner is always a member, so they keep seeing their project.
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    $owner = (int) $this->getOwnerId();
    if ($owner && !in_array($owner, array_map('intval', array_column($this->get('members')->getValue(), 'target_id')), TRUE)) {
      $this->get('members')->appendItem(['target_id' => $owner]);
    }
  }

}
