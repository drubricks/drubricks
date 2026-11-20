<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_examples_project_management\Dashboard\PmDashboardBlockBase;
use Drupal\drubricks_examples_project_management\Entity\Task;

/**
 * Panel: the user's open tasks by due date, with priority.
 */
#[Block(
  id: 'drubricks_examples_project_management_my_tasks',
  admin_label: new TranslatableMarkup('My tasks (Projects)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class MyTasksBlock extends PmDashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('drubricks_examples_project_management.my_tasks');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $account = $this->currentUser;
    $query = $this->accessibleQuery('pm_task', $account)
      ->condition('state', 'done', '<>')
      ->condition('assignee', $account->id());
    $ids = $query->sort('due_date')->sort('weight')->range(0, 50)->execute();
    $tasks = array_filter($this->entityTypeManager->getStorage('pm_task')->loadMultiple($ids), static fn ($task) => $task instanceof Task);
    // Soonest due first, tasks without a due date last.
    $key = static fn (Task $task) => [$task->get('due_date')->value === NULL, $task->get('due_date')->value];
    uasort($tasks, static fn (Task $a, Task $b) => $key($a) <=> $key($b));
    $tasks = array_slice($tasks, 0, 6, TRUE);
    if (!$tasks) {
      return $this->emptyLine((string) $this->t('Nothing assigned to you'));
    }
    $this->preloadReferences($tasks, 'project');
    $rows = ['#cache' => ['max-age' => $this->untilMidnight()]];
    foreach ($tasks as $task) {
      $priority = (string) $task->get('priority')->value;
      $due = (string) $task->get('due_date')->value;
      $late = $due !== '' && $this->isPast($due);
      $rows[$task->id()] = [
        '#type' => 'component',
        '#component' => 'drubricks:p-row',
        '#props' => [
          'title' => (string) $task->label(),
          'subtitle' => 'T-' . $task->id() . ' · ' . (($project = $task->get('project')->entity) && $project->access('view label', $account) ? $project->label() : $this->t('No project')),
        ] + $this->recordLink($task),
        '#slots' => [
          'leading' => isset(Task::PRIORITY_ICONS[$priority]) ? [
            '#type' => 'component',
            '#component' => 'drubricks:priority',
            '#props' => [
              'level' => $priority,
              'label' => (string) $this->t('@priority priority', ['@priority' => Task::priorities()[$priority]]),
            ],
          ] : [],
          'trailing' => [
            '#theme' => 'pm_widget_due',
            '#date' => $due !== '' ? $this->dayLabel($due) : '',
            '#late' => $late,
          ],
        ],
      ];
    }
    return $rows;
  }

}
