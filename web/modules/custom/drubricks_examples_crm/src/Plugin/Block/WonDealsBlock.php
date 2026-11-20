<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_examples_crm\Dashboard\CrmDashboardBlockBase;

/**
 * Stat tile: value and number of the won deals.
 */
#[Block(
  id: 'drubricks_examples_crm_won',
  admin_label: new TranslatableMarkup('Won deals (CRM)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class WonDealsBlock extends CrmDashboardBlockBase {

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
    $won = $this->dealTotals($this->currentUser)['won'] ?? ['value' => 0, 'count' => 0];
    return $this->stat([
      'label' => (string) $this->t('Won'),
      'icon' => 'trophy',
      'color' => 'green',
      'value' => self::short($won['value']),
      'sub' => (string) $this->formatPlural($won['count'], '1 deal', '@count deals'),
    ], $this->getUrl());
  }

}
