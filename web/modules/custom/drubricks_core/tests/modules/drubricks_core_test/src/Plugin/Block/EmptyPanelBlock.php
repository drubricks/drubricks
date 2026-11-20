<?php

declare(strict_types=1);

namespace Drupal\drubricks_core_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drubricks_core\Dashboard\DashboardBlockBase;

/**
 * A panel that has nothing to show: the dashboard leaves it out.
 */
#[Block(
  id: 'drubricks_core_test_empty',
  admin_label: new TranslatableMarkup('Empty'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class EmptyPanelBlock extends DashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

}
