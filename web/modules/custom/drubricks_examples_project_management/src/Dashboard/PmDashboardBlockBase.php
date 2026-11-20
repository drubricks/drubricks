<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Dashboard;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drubricks_core\Dashboard\DashboardBlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared data of the project dashboard blocks.
 */
abstract class PmDashboardBlockBase extends DashboardBlockBase {

  /**
   * Core's memory cache bin: one aggregate query for all project blocks.
   */
  protected CacheBackendInterface $memoryCache;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->memoryCache = $container->get('cache.memory');
    return $instance;
  }

  /**
   * Number of tasks the account may see, per project id and status.
   *
   * @return array<int|string, array<string, int>>
   *   [project id (0 = none) => [status => count]].
   */
  protected function taskCounts(AccountInterface $account): array {
    $cid = 'drubricks_examples_project_management:task_counts:' . $account->id();
    if ($cached = $this->memoryCache->get($cid)) {
      return $cached->data;
    }
    $counts = [];
    $query = $this->accessibleAggregateQuery('pm_task', $account);
    $rows = $query ? $query->aggregate('id', 'COUNT')->groupBy('project')->groupBy('state')->execute() : [];
    foreach ($rows as $row) {
      $counts[(int) $row['project']][$row['state']] = (int) $row['id_count'];
    }
    $this->memoryCache->set($cid, $counts, CacheBackendInterface::CACHE_PERMANENT, ['pm_task_list', 'pm_project_list']);
    return $counts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['pm_task_list', 'pm_project_list']);
  }

}
