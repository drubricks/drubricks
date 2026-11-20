<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_examples_crm\Dashboard\CrmDashboardBlockBase;
use Drupal\drubricks_examples_crm\Entity\Company;
use Drupal\drubricks_examples_crm\Entity\Deal;

/**
 * Panel: open deals by close date; a row opens the deal.
 */
#[Block(
  id: 'drubricks_examples_crm_closing_soon',
  admin_label: new TranslatableMarkup('Closing soon (CRM)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class ClosingSoonBlock extends CrmDashboardBlockBase {

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
    $ids = $this->accessibleQuery('crm_deal', $account)
      ->condition('stage', ['won', 'lost'], 'NOT IN')
      ->exists('close_date')
      ->sort('close_date')
      ->range(0, 5)
      ->execute();
    if (!$ids) {
      return $this->emptyLine((string) $this->t('No open deals'));
    }
    $deals = $this->entityTypeManager->getStorage('crm_deal')->loadMultiple($ids);
    // Companies of all rows in one query.
    $this->preloadReferences($deals, 'company');
    $rows = ['#cache' => ['max-age' => $this->untilMidnight()]];
    foreach ($deals as $deal) {
      assert($deal instanceof Deal);
      $company = $deal->get('company')->entity;
      $company = $company instanceof Company && $company->access('view label', $account) ? $company : NULL;
      $close = (string) $deal->get('close_date')->value;
      $late = $this->isPast($close);
      $tile_name = (string) ($company?->label() ?? $deal->label());
      $rows[$deal->id()] = [
        '#type' => 'component',
        '#component' => 'drubricks:p-row',
        '#props' => [
          'title' => (string) $deal->label(),
          'subtitle' => implode(' · ', array_filter([
            (string) $company?->label(),
            (string) (Deal::stages()[$deal->getStage()] ?? ''),
          ])),
        ] + $this->recordLink($deal),
        '#slots' => [
          'leading' => [
            '#type' => 'component',
            '#component' => 'drubricks:tile',
            '#props' => [
              'name' => $tile_name,
              'color' => $company?->getColor() ?? ColorTokens::forName($tile_name),
            ],
          ],
          'trailing' => [
            '#theme' => 'crm_widget_deal_due',
            '#value' => self::short((float) $deal->get('value')->value),
            '#date' => $this->dayLabel($close),
            '#late' => $late,
          ],
        ],
      ];
    }
    return $rows;
  }

}
