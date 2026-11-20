<?php

declare(strict_types=1);

namespace Drupal\drubricks_core_test\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_core\Dashboard\DashboardBlockBase;

/**
 * A panel with its own access rule (only for "view any" users).
 */
#[Block(
  id: 'drubricks_core_test_records',
  admin_label: new TranslatableMarkup('All records'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class RecordsPanelBlock extends DashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.drubricks_test_record.collection');
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return parent::blockAccess($account)->andIf(AccessResult::allowedIfHasPermission($account, 'view any drubricks_test_record'));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return ['#markup' => 'All records panel'];
  }

}
