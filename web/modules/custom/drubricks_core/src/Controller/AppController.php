<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\block\BlockRepositoryInterface;
use Drupal\drubricks_core\Dashboard\DashboardBlockInterface;
use Drupal\drubricks_core\Navigation\NavigationBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * App home (dashboard of the sidebar items) and the sidebar fragment.
 */
final class AppController extends ControllerBase {

  public function __construct(
    private readonly NavigationBuilder $navigation,
    private readonly BlockRepositoryInterface $blockRepository,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('drubricks_core.navigation'),
      $container->get('block.repository'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Home: greeting, dashboard blocks, then a tile per sidebar item.
   */
  public function home(): array {
    $cache = (new CacheableMetadata())->addCacheContexts(['user', 'user.permissions']);
    // Tiles of the sidebar sections (the group on top, Home, left out).
    $sections = array_filter($this->navigation->sections($cache), static fn ($section) => $section['id'] !== '');
    $regions = $this->dashboardRegions($cache);
    // The date line changes at midnight (user's timezone).
    $now = $this->time->getRequestTime();
    $midnight = (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->modify('tomorrow');
    $cache->mergeCacheMaxAge(max(60, $midnight->getTimestamp() - $now));
    $build = [
      '#theme' => 'drubricks_home',
      '#title' => $this->t('Home'),
      '#name' => $this->currentUser()->getDisplayName(),
      '#date' => $this->dateFormatter->format($now, 'custom', 'l, j F'),
      '#stats' => $regions[DashboardBlockInterface::STATS_REGION],
      '#panels' => $regions[DashboardBlockInterface::PANELS_REGION],
      '#sections' => array_values($sections),
    ];
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Blocks of the theme's dashboard regions the user may see.
   *
   * Like core's BlockPageVariant, for the two regions the page template does
   * not print: the home template puts them between the greeting and the
   * section tiles.
   *
   * @return array<string, array>
   *   Render arrays of the blocks keyed by region (empty: no visible block).
   */
  private function dashboardRegions(CacheableMetadata $cache): array {
    $regions = array_fill_keys(DashboardBlockInterface::REGIONS, []);
    $access = [];
    $view_builder = $this->entityTypeManager()->getViewBuilder('block');
    foreach ($this->blockRepository->getVisibleBlocksPerRegion($access) as $region => $blocks) {
      if (!isset($regions[$region])) {
        continue;
      }
      if (isset($access[$region])) {
        $cache->addCacheableDependency($access[$region]);
      }
      foreach ($blocks as $id => $block) {
        $regions[$region][$id] = $view_builder->view($block);
      }
      if ($regions[$region]) {
        $regions[$region]['#sorted'] = TRUE;
      }
    }
    // Placements come and go with apps and the Block layout UI.
    $cache->addCacheTags(['config:block_list']);
    return $regions;
  }

  /**
   * The sidebar alone, for refreshing counters after a change.
   */
  public function sidebar(): array {
    return $this->navigation->sidebar();
  }

}
