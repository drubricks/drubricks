<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_examples_project_management\Dashboard\PmDashboardBlockBase;

/**
 * Stat tile: open tasks past their due date (red when there are any).
 */
#[Block(
  id: 'drubricks_examples_project_management_overdue_tasks',
  admin_label: new TranslatableMarkup('Overdue tasks (Projects)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class OverdueTasksBlock extends PmDashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.pm_task.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $overdue = (int) $this->accessibleQuery('pm_task', $this->currentUser)
      ->condition('state', 'done', '<>')
      ->condition('due_date', $this->today()->format('Y-m-d'), '<')
      ->count()
      ->execute();
    return [
      '#cache' => ['max-age' => $this->untilMidnight()],
    ] + $this->stat([
      'label' => (string) $this->t('Overdue tasks'),
      'icon' => 'clock',
      'color' => 'red',
      'value' => $overdue,
      'value_color' => $overdue ? 'red' : '',
      'sub' => $overdue ? (string) $this->t('Past their due date') : (string) $this->t('Nothing overdue'),
    ], $this->getUrl());
  }

}
