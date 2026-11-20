<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_examples_project_management\Dashboard\PmDashboardBlockBase;
use Drupal\drubricks_examples_project_management\Entity\Project;

/**
 * Panel: active projects with their progress (done / all tasks).
 */
#[Block(
  id: 'drubricks_examples_project_management_projects',
  admin_label: new TranslatableMarkup('Projects (Projects)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class ProjectProgressBlock extends PmDashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.pm_project.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $account = $this->currentUser;
    $ids = $this->accessibleQuery('pm_project', $account)
      ->condition('state', 'done', '<>')
      ->sort('due_date')
      ->sort('name')
      ->range(0, 6)
      ->execute();
    if (!$ids) {
      return $this->emptyLine((string) $this->t('No active projects'));
    }
    $counts = $this->taskCounts($account);
    $rows = [];
    foreach ($this->entityTypeManager->getStorage('pm_project')->loadMultiple($ids) as $project) {
      assert($project instanceof Project);
      $statuses = $counts[(int) $project->id()] ?? [];
      $total = array_sum($statuses);
      $done = $statuses['done'] ?? 0;
      $color = $project->getColor() ?? ColorTokens::forName((string) $project->label());
      $state = (string) (Project::statuses()[$project->get('state')->value] ?? '');
      $rows[$project->id()] = [
        '#type' => 'component',
        '#component' => 'drubricks:p-row',
        '#props' => [
          'title' => (string) $project->label(),
          'subtitle' => $state . ' · ' . $this->formatPlural($total - $done, '1 open task', '@count open tasks'),
        ] + $this->recordLink($project),
        '#slots' => [
          'leading' => [
            '#type' => 'component',
            '#component' => 'drubricks:tile',
            '#props' => ['name' => (string) $project->label(), 'color' => $color],
          ],
          'trailing' => [
            '#type' => 'component',
            '#component' => 'drubricks:progress',
            '#props' => [
              'value' => $total ? round($done / $total * 100) : 0,
              'color' => $color,
              'label' => $done . '/' . $total,
              'aria_label' => (string) $this->t('@done of @total tasks done', ['@done' => $done, '@total' => $total]),
              'attributes' => new Attribute(['class' => ['w-32', 'shrink-0']]),
            ],
          ],
        ],
      ];
    }
    return $rows;
  }

}
