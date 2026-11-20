<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Dashboard;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drubricks_core\Dashboard\DashboardBlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared data of the CRM dashboard blocks (one query for all of them).
 */
abstract class CrmDashboardBlockBase extends DashboardBlockBase {

  /**
   * Core's memory cache bin: one aggregate query for all CRM blocks.
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
   * Sum of the value and number of deals per stage the account may see.
   *
   * @return array<string, array{value: float, count: int}>
   *   Keyed by stage.
   */
  protected function dealTotals(AccountInterface $account): array {
    $cid = 'drubricks_examples_crm:deal_totals:' . $account->id();
    if ($cached = $this->memoryCache->get($cid)) {
      return $cached->data;
    }
    $totals = [];
    $query = $this->accessibleAggregateQuery('crm_deal', $account);
    $rows = $query ? $query->aggregate('value', 'SUM')->aggregate('id', 'COUNT')->groupBy('stage')->execute() : [];
    foreach ($rows as $row) {
      $totals[$row['stage']] = ['value' => (float) $row['value_sum'], 'count' => (int) $row['id_count']];
    }
    $this->memoryCache->set($cid, $totals, CacheBackendInterface::CACHE_PERMANENT, ['crm_deal_list']);
    return $totals;
  }

  /**
   * A short amount: 1.2M, 48K, 900.
   */
  public static function short(float $value): string {
    return match (TRUE) {
      $value >= 1e6 => rtrim(rtrim(number_format($value / 1e6, 1, '.', ''), '0'), '.') . 'M',
      $value >= 1e3 => round($value / 1e3) . 'K',
      default => (string) round($value),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['crm_deal_list', 'crm_company_list', 'crm_contact_list']);
  }

}
