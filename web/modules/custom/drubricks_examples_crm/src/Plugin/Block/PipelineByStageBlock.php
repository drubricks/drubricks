<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\drubricks_examples_crm\Dashboard\CrmDashboardBlockBase;
use Drupal\drubricks_examples_crm\Entity\Deal;

/**
 * Panel: value of the open deals per stage (bar rows).
 */
#[Block(
  id: 'drubricks_examples_crm_pipeline_by_stage',
  admin_label: new TranslatableMarkup('Pipeline by stage (CRM)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class PipelineByStageBlock extends CrmDashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.crm_deal.drubricks_board');
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkTitle(): string {
    return (string) $this->t('Pipeline');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $account = $this->currentUser;
    $totals = $this->dealTotals($account);
    $stages = array_diff_key(Deal::stages(), ['won' => 1, 'lost' => 1]);
    $max = max(1, ...array_map(static fn ($stage) => $totals[$stage]['value'] ?? 0, array_keys($stages)));
    $rows = [];
    foreach ($stages as $stage => $label) {
      $value = $totals[$stage]['value'] ?? 0;
      $rows[$stage] = [
        '#type' => 'component',
        '#component' => 'drubricks:bar-row',
        '#props' => [
          'label' => (string) $label,
          'percent' => round($value / $max * 100, 1),
          'color' => Deal::STAGE_COLORS[$stage],
          'value' => self::short($value),
          'attributes' => new Attribute([
            'title' => (string) $this->formatPlural($totals[$stage]['count'] ?? 0, '1 deal', '@count deals'),
          ]),
        ],
      ];
    }
    return $rows;
  }

}
