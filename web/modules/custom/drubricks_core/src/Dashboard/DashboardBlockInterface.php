<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Dashboard;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Url;

/**
 * A block made for the home dashboard (/apps): stat tiles or panel rows.
 *
 * Placed in the theme's "dashboard_stats" region, a block returns one
 * drubricks:stat tile (one tile per block: each has its own placement and
 * "Configure block" link); in the "dashboard" region it returns the rows of
 * a panel (drubricks:p-row, drubricks:bar-row …) and the block template adds
 * the panel with the block label as heading and the "Show all" link.
 *
 * @see \Drupal\drubricks_core\Dashboard\DashboardBlockBase
 * @see \Drupal\drubricks_core\Hook\DashboardHooks
 */
interface DashboardBlockInterface extends BlockPluginInterface {

  /**
   * Theme region of the stat tiles (a strip on top of the dashboard).
   */
  public const STATS_REGION = 'dashboard_stats';

  /**
   * Theme region of the panels (a grid below the tiles).
   */
  public const PANELS_REGION = 'dashboard';

  /**
   * Both dashboard regions, in their order on the page.
   */
  public const REGIONS = [self::STATS_REGION, self::PANELS_REGION];

  /**
   * Target of the heading link ("Show all") and of the stat tiles.
   *
   * The block is hidden from users who may not open it.
   */
  public function getUrl(): ?Url;

  /**
   * Label of the heading link (panels).
   */
  public function getLinkTitle(): string;

}
