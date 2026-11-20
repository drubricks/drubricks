<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Screen;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_examples_project_management\Entity\Project;
use Drupal\drubricks_examples_project_management\Entity\Task;
use Drupal\user\UserInterface;

/**
 * Projects: status pill and due date in the list; progress and tasks view.
 */
class ProjectScreen extends ScreenHandler {

  use PmScreenTrait;

  /**
   * {@inheritdoc}
   */
  public function row(EntityInterface $entity): array {
    $row = parent::row($entity);
    if ($entity instanceof Project) {
      $color = Project::STATUS_COLORS[$entity->get('state')->value] ?? 'gray';
      $row['color'] = $entity->getColor() ?? $color;
      $row['pill'] = [
        'label' => (string) (Project::statuses()[$entity->get('state')->value] ?? ''),
        'color' => $color,
      ];
      $row['meta'] = $this->fieldDate($entity, 'due_date');
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   *
   * The number of tasks of each project the user may see: one aggregate
   * query for the whole page, not one count per row.
   */
  public function rows(array $entities, ?EntityInterface $active): array {
    $rows = parent::rows($entities, $active);
    $counts = [];
    if ($entities) {
      $result = $this->accessibleAggregateQuery('pm_task', $this->currentUser)
        ?->condition('project', array_keys($entities), 'IN')
          ->aggregate('id', 'COUNT')
          ->groupBy('project')
          ->execute() ?? [];
      foreach ($result as $item) {
        $counts[(int) $item['project']] = (int) $item['id_count'];
      }
    }
    foreach ($rows as &$row) {
      $row['text'] = (string) $this->formatPlural($counts[(int) $row['id']] ?? 0, '1 task', '@count tasks');
    }
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBlockers(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
    $cacheability?->addCacheTags($this->entityTypeManager->getDefinition('pm_task')->getListCacheTags());
    $count = $this->taskCount($entity);
    return $count ? [(string) $this->formatPlural($count, '1 task belongs to this project.', '@count tasks belong to this project.')] : [];
  }

  /**
   * Number of tasks of a project, all of them (what blocks a delete).
   */
  private function taskCount(EntityInterface $project): int {
    return (int) $this->entityTypeManager->getStorage('pm_task')->getQuery()
      ->accessCheck(FALSE)
      ->condition('project', $project->id())
      ->count()
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  protected function view(EntityInterface $entity, bool $compact): array {
    if (!$entity instanceof Project) {
      return parent::detail($entity);
    }
    $ids = $this->accessibleQuery('pm_task', $this->currentUser)?->condition('project', $entity->id())->sort('weight')->sort('name')->execute() ?? [];
    $tasks = array_filter($this->entityTypeManager->getStorage('pm_task')->loadMultiple($ids), static fn ($task) => $task instanceof Task);
    // Assignees of all tasks in one query.
    $this->preloadReferences($tasks, 'assignee');
    $groups = [];
    foreach (Task::statuses() as $state => $label) {
      $groups[$state] = ['label' => (string) $label, 'color' => Task::STATUS_COLORS[$state], 'tasks' => []];
    }
    $done = $late = 0;
    foreach ($tasks as $task) {
      $due = $this->due($task);
      $done += (int) ($task->getState() === 'done');
      $late += (int) $due['late'];
      $priority = (string) $task->get('priority')->value;
      $groups[$task->getState()]['tasks'][] = [
        'title' => (string) $task->label(),
        'key' => 'T-' . $task->id(),
        'priority' => isset(Task::PRIORITY_ICONS[$priority]) ? $priority : '',
        'priority_label' => (string) $this->t('@priority priority', ['@priority' => Task::priorities()[$priority] ?? $priority]),
        'due' => $due,
        'assignee' => $this->assignee($task),
        'link' => $this->recordLink($task),
      ];
    }
    $total = count($tasks);
    $state = (string) $entity->get('state')->value;
    // Fields hidden in Manage display are hidden here too.
    $due = $this->shows($entity, 'due_date') ? (string) $entity->get('due_date')->value : '';
    $project_late = $due && $state !== 'done' && $this->isPast($due);
    $owner = $entity->getOwnerId() ? $entity->getOwner() : NULL;
    $members = [];
    foreach ($entity->get('members')->referencedEntities() as $member) {
      if ($member instanceof UserInterface) {
        $members[] = ['name' => $member->getDisplayName(), 'color' => ColorTokens::forName($member->getDisplayName())];
      }
    }
    $color = $entity->getColor() ?? Project::STATUS_COLORS[$state] ?? ColorTokens::forName((string) $entity->label());
    return [
      '#theme' => 'pm_project_detail',
      '#compact' => $compact,
      '#title' => (string) $entity->label(),
      '#color' => $color,
      '#status' => $this->shows($entity, 'state') ? [
        'label' => (string) (Project::statuses()[$state] ?? $state),
        'color' => Project::STATUS_COLORS[$state] ?? 'gray',
      ] : NULL,
      '#due' => $due ? ['label' => $this->dayLabel($due, 'j M Y'), 'late' => $project_late] : NULL,
      '#progress' => [
        'value' => $total ? (int) round($done / $total * 100) : 0,
        'label' => (string) $this->t('@done of @total tasks done', ['@done' => $done, '@total' => $total]),
      ],
      '#stats' => [
        ['label' => (string) $this->t('Open'), 'icon' => 'circle-dot', 'color' => 'blue', 'value' => $total - $done],
        ['label' => (string) $this->t('Done'), 'icon' => 'checkmark-circle', 'color' => 'green', 'value' => $done],
        [
          'label' => (string) $this->t('Overdue'),
          'icon' => 'clock',
          'color' => 'red',
          'value' => $late,
          'value_color' => $late ? 'red' : '',
        ],
      ],
      '#groups' => array_filter($groups, static fn ($group) => $group['tasks'] !== []),
      '#description' => $this->shows($entity, 'description') ? (string) $entity->get('description')->value : '',
      '#facts' => $this->visibleFacts($entity, [
        [
          'field' => 'state',
          'label' => $this->t('Status'),
          'pill' => [
            'label' => (string) (Project::statuses()[$state] ?? $state),
            'color' => Project::STATUS_COLORS[$state] ?? 'gray',
          ],
        ],
        [
          'field' => 'due_date',
          'label' => $this->t('Due date'),
          'value' => $due ? $this->dayLabel($due, 'j M Y') : '—',
          'late' => $project_late,
          'icon' => 'calendar',
        ],
        [
          'field' => 'uid',
          'label' => $this->t('Owner'),
          'value' => $owner?->getDisplayName() ?? '—',
          'avatar' => $owner?->getDisplayName(),
        ],
        [
          // Who sees the project and its tasks (ProjectAccess).
          'field' => 'members',
          'label' => $this->t('Members'),
          'people' => $members,
          'value' => implode(', ', array_column($members, 'name')),
        ],
      ]),
      // Fields added in Manage fields (and others it does not place).
      '#fields' => $this->displayFields($entity, ['state', 'due_date', 'uid', 'members', 'description']),
      '#cache' => ['tags' => ['pm_task_list'], 'max-age' => $this->untilMidnight()],
    ];
  }

}
