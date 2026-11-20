<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_examples_project_management\Dashboard\PmDashboardBlockBase;
use Drupal\drubricks_examples_project_management\Entity\Task;

/**
 * Panel: number of tasks per status (bar rows).
 */
#[Block(
  id: 'drubricks_examples_project_management_tasks_by_status',
  admin_label: new TranslatableMarkup('Tasks by status (Projects)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class TasksByStatusBlock extends PmDashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.pm_task.drubricks_board');
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkTitle(): string {
    return (string) $this->t('Board');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $account = $this->currentUser;
    $per_status = [];
    foreach ($this->taskCounts($account) as $statuses) {
      foreach ($statuses as $status => $count) {
        $per_status[$status] = ($per_status[$status] ?? 0) + $count;
      }
    }
    $max = max(1, ...array_values($per_status ?: [0]));
    $rows = [];
    foreach (Task::statuses() as $status => $label) {
      $count = $per_status[$status] ?? 0;
      $rows[$status] = [
        '#type' => 'component',
        '#component' => 'drubricks:bar-row',
        '#props' => [
          'label' => (string) $label,
          'percent' => round($count / $max * 100, 1),
          'color' => Task::STATUS_COLORS[$status],
          'value' => (string) $count,
        ],
      ];
    }
    return $rows;
  }

}
