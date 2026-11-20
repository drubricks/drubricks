<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drubricks_core\Navigation\NavigationBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The app sidebar: sections and items of the "drubricks" menu + account.
 */
#[Block(
  id: 'drubricks_sidebar',
  admin_label: new TranslatableMarkup('Drubricks sidebar'),
  category: new TranslatableMarkup('Drubricks'),
)]
final class SidebarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected readonly NavigationBuilder $navigation) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('drubricks_core.navigation'));
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    // Anonymous pages (login) use the auth layout without a sidebar.
    return AccessResult::allowedIf($account->isAuthenticated())->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->navigation->sidebar();
  }

}
