<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_examples_crm\Dashboard\CrmDashboardBlockBase;

/**
 * Stat tile: value and number of the open deals.
 */
#[Block(
  id: 'drubricks_examples_crm_open_pipeline',
  admin_label: new TranslatableMarkup('Open pipeline (CRM)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class OpenPipelineBlock extends CrmDashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.crm_deal.drubricks_board');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $open = array_diff_key($this->dealTotals($this->currentUser), ['won' => 1, 'lost' => 1]);
    return $this->stat([
      'label' => (string) $this->t('Open pipeline'),
      'icon' => 'dollar',
      'color' => 'blue',
      'value' => self::short(array_sum(array_column($open, 'value'))),
      'sub' => (string) $this->formatPlural(array_sum(array_column($open, 'count')), '1 open deal', '@count open deals'),
    ], $this->getUrl());
  }

}
