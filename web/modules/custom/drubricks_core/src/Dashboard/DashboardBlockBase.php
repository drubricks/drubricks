<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Dashboard;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\drubricks_core\Access\AccessibleRecordsTrait;
use Drupal\drubricks_core\Datetime\RelativeDayTrait;
use Drupal\drubricks_core\Entity\PreloadReferencesTrait;
use Drupal\drubricks_core\Htmx\Swap;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class of dashboard blocks: access by the link target, helpers.
 *
 * A block plugin (#[Block], category "Dashboard") of an app extends this and
 * implements build() for the current user; its placement (block.block.* in
 * the module's config/optional) puts it in a dashboard region.
 *
 * @phpstan-consistent-constructor
 */
abstract class DashboardBlockBase extends BlockBase implements DashboardBlockInterface, ContainerFactoryPluginInterface {

  use AccessibleRecordsTrait;
  use PreloadReferencesTrait;
  use RelativeDayTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The time service (request time for "today").
   */
  protected TimeInterface $time;

  /**
   * The user the block is built for.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->time = $container->get('datetime.time');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): ?Url {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkTitle(): string {
    return (string) $this->t('Show all');
  }

  /**
   * {@inheritdoc}
   *
   * Hidden from users who may not open the link target.
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    $url = $this->getUrl();
    return $url ? $url->access($account, TRUE) : AccessResult::allowed();
  }

  /**
   * A drubricks:stat tile.
   *
   * @param array $props
   *   Stat props (label, value, icon, color, unit, sub, value_color …).
   * @param \Drupal\Core\Url|null $url
   *   Makes the tile a link that swaps the app (no full page load).
   */
  protected function stat(array $props, ?Url $url = NULL): array {
    if ($url) {
      $props['url'] = $url->toString();
      $props['attributes'] = $this->swapAttributes($url);
    }
    return ['#type' => 'component', '#component' => 'drubricks:stat', '#props' => $props];
  }

  /**
   * Attributes of a link that swaps the whole app (like a sidebar click).
   */
  protected function swapAttributes(Url $url): Attribute {
    $link = ['#attributes' => []];
    Swap::link($link, $url, Swap::CONTENT);
    return new Attribute($link['#attributes']);
  }

  /**
   * Props 'url' + 'attributes' for a component linking to a record page.
   *
   * @return array{url?: string, attributes?: \Drupal\Core\Template\Attribute}
   *   Empty when the user may not view the record.
   */
  protected function recordLink(EntityInterface $entity): array {
    if (!$entity->hasLinkTemplate('canonical') || !$entity->access('view')) {
      return [];
    }
    $url = $entity->toUrl();
    return ['url' => $url->toString(), 'attributes' => $this->swapAttributes($url)];
  }

  /**
   * A one-line "nothing here" for an empty panel.
   */
  protected function emptyLine(string $text): array {
    return ['#type' => 'component', '#component' => 'drubricks:empty-line', '#props' => ['text' => $text]];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // Counts and lists depend on the user (own records, "my" items).
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

}
