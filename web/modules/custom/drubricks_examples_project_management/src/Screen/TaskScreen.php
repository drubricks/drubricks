<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Screen;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_examples_project_management\Entity\Project;
use Drupal\drubricks_examples_project_management\Entity\Task;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tasks: grouped by status; "My tasks" (route default mine) filters by user.
 */
class TaskScreen extends ScreenHandler {

  use PmScreenTrait;

  /**
   * The request stack (the "My tasks" route has the default mine: TRUE).
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $instance = parent::createInstance($container, $entity_type);
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function title(): string {
    $request = $this->requestStack->getCurrentRequest();
    return $request && $this->mine($request) ? (string) $this->t('My tasks') : parent::title();
  }

  /**
   * {@inheritdoc}
   */
  public function query(Request $request): QueryInterface {
    $query = parent::query($request);
    if ($this->mine($request)) {
      $query->condition('assignee', $this->currentUser->id());
    }
    return $query;
  }

  /**
   * Whether the request is for the user's tasks only ("My tasks").
   */
  protected function mine(Request $request): bool {
    return (bool) $request->attributes->get('mine');
  }

  /**
   * {@inheritdoc}
   */
  public function board(): array {
    $lanes = [];
    foreach (Task::statuses() as $state => $label) {
      $lanes[$state] = [(string) $label, Task::STATUS_COLORS[$state]];
    }
    return [
      'field' => 'state',
      'lanes' => $lanes,
      'order' => 'weight',
      'filter' => 'project',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function card(EntityInterface $entity): array {
    $card = parent::card($entity);
    if ($entity instanceof Task) {
      $assignee = $entity->get('assignee')->entity;
      $card['key'] = 'T-' . $entity->id();
      $card['subtitle'] = $this->referenceLabel($entity, 'project');
      $card['icon'] = Task::PRIORITY_ICONS[$entity->get('priority')->value] ?? NULL;
      $card['avatar'] = $assignee instanceof UserInterface ? $assignee->getDisplayName() : NULL;
      $card['meta'] = $this->fieldDate($entity, 'due_date');
    }
    return $card;
  }

  /**
   * {@inheritdoc}
   */
  protected function sort(QueryInterface $query): void {
    $query->sort('state')->sort('weight')->sort('name');
  }

  /**
   * {@inheritdoc}
   */
  public function rows(array $entities, ?EntityInterface $active): array {
    // Referenced records of the whole page in one query each, not per row.
    $this->preloadReferences($entities, 'project');
    $this->preloadReferences($entities, 'assignee');
    return parent::rows($entities, $active);
  }

  /**
   * {@inheritdoc}
   */
  public function row(EntityInterface $entity): array {
    $row = parent::row($entity);
    if ($entity instanceof Task) {
      $row['color'] = Task::STATUS_COLORS[$entity->getState()] ?? 'gray';
      $row['section'] = (string) (Task::statuses()[$entity->getState()] ?? '');
      $row['subtitle'] = $this->referenceLabel($entity, 'project');
      $assignee = $entity->get('assignee')->entity;
      $parts = [
        (string) (Task::priorities()[$entity->get('priority')->value] ?? ''),
        $assignee instanceof UserInterface ? $assignee->getDisplayName() : (string) $this->t('Unassigned'),
      ];
      $row['text'] = implode(' · ', $parts);
      $due = $this->due($entity);
      $row['meta'] = $due['label'] ? $this->dayLabel((string) $entity->get('due_date')->value) : '';
      if ($due['late']) {
        $row['pill'] = ['label' => (string) $this->t('Overdue'), 'color' => 'red'];
      }
      elseif ($entity->get('priority')->value === 'high' && $entity->getState() !== 'done') {
        $row['pill'] = ['label' => (string) Task::priorities()['high'], 'color' => 'orange'];
      }
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function view(EntityInterface $entity, bool $compact): array {
    if (!$entity instanceof Task) {
      return parent::detail($entity);
    }
    // Fields hidden in Manage display are hidden here too.
    $project = $this->shows($entity, 'project') ? $this->referenced($entity, 'project') : NULL;
    $project = $project instanceof Project ? $project : NULL;
    $show_priority = $this->shows($entity, 'priority');
    $priority = (string) $entity->get('priority')->value;
    $priority_label = (string) (Task::priorities()[$priority] ?? $priority);
    $status = $this->statusPill($entity);
    $due = $this->due($entity);
    $assignee = $this->assignee($entity);
    $owner = $entity->getOwnerId() ? $entity->getOwner() : NULL;
    $project_item = NULL;
    if ($project) {
      $counts = [];
      $rows = $this->accessibleAggregateQuery('pm_task', $this->currentUser)
        ?->condition('project', $project->id())->aggregate('id', 'COUNT')->groupBy('state')->execute() ?? [];
      foreach ($rows as $row) {
        $counts[$row['state']] = (int) $row['id_count'];
      }
      $project_item = [
        'title' => (string) $project->label(),
        'subtitle' => (string) $this->t('@done of @total tasks done', [
          '@done' => $counts['done'] ?? 0,
          '@total' => array_sum($counts),
        ]),
        'color' => $project->getColor() ?? Project::STATUS_COLORS[$project->get('state')->value] ?? 'gray',
        'link' => $this->recordLink($project),
      ];
    }
    return [
      '#theme' => 'pm_task_detail',
      '#compact' => $compact,
      '#title' => (string) $entity->label(),
      '#key' => 'T-' . $entity->id(),
      '#status' => $this->shows($entity, 'state') ? $status : NULL,
      '#priority' => $show_priority && isset(Task::PRIORITY_ICONS[$priority]) ? [
        'level' => $priority,
        'label' => $priority_label,
      ] : NULL,
      '#project' => $project_item,
      '#facts' => $this->visibleFacts($entity, [
        ['field' => 'state', 'label' => $this->t('Status'), 'pill' => $status],
        ['field' => 'priority'] + (isset(Task::PRIORITY_ICONS[$priority]) ? [
          'label' => $this->t('Priority'),
          'priority' => $priority,
          'value' => $priority_label,
        ] : ['label' => $this->t('Priority'), 'value' => '—']),
        ['field' => 'assignee'] + ($assignee ? [
          'label' => $this->t('Assignee'),
          'value' => $assignee['name'],
          'avatar' => $assignee['name'],
          'avatar_color' => $assignee['color'],
        ] : ['label' => $this->t('Assignee'), 'value' => (string) $this->t('Unassigned')]),
        [
          'field' => 'due_date',
          'label' => $this->t('Due date'),
          'value' => $due['label'] ?: '—',
          'late' => $due['late'],
          'icon' => 'calendar',
        ],
        [
          'field' => 'project',
          'label' => $this->t('Project'),
          'value' => (string) ($project?->label() ?? '—'),
        ] + ($project_item['link'] ?? []),
        [
          'field' => 'uid',
          'label' => $this->t('Owner'),
          'value' => $owner?->getDisplayName() ?? '—',
          'avatar' => $owner?->getDisplayName(),
        ],
      ]),
      '#description' => $this->shows($entity, 'description') ? (string) $entity->get('description')->value : '',
      // Fields added in Manage fields (and others it does not place).
      '#fields' => $this->displayFields($entity, [
        'state',
        'priority',
        'assignee',
        'due_date',
        'project',
        'uid',
        'description',
      ]),
      '#cache' => ['tags' => ['pm_task_list'], 'max-age' => $this->untilMidnight()],
    ];
  }

}
