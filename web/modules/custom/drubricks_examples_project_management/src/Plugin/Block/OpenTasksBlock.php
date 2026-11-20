<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_examples_project_management\Dashboard\PmDashboardBlockBase;

/**
 * Stat tile: open tasks, and how many are assigned to the user.
 */
#[Block(
  id: 'drubricks_examples_project_management_open_tasks',
  admin_label: new TranslatableMarkup('Open tasks (Projects)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class OpenTasksBlock extends PmDashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.pm_task.drubricks_board');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $account = $this->currentUser;
    $open = 0;
    foreach ($this->taskCounts($account) as $statuses) {
      $open += array_sum(array_diff_key($statuses, ['done' => 1]));
    }
    $mine = (int) $this->accessibleQuery('pm_task', $account)
      ->condition('state', 'done', '<>')
      ->condition('assignee', $account->id())
      ->count()
      ->execute();
    return $this->stat([
      'label' => (string) $this->t('Open tasks'),
      'icon' => 'checkmark-circle',
      'color' => 'indigo',
      'value' => $open,
      'sub' => (string) $this->formatPlural($mine, '1 assigned to you', '@count assigned to you'),
    ], $this->getUrl());
  }

}
