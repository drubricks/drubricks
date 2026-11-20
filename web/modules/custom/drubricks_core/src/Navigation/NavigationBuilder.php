<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Navigation;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\drubricks_core\Access\AccessibleRecordsTrait;
use Drupal\drubricks_core\Htmx\Swap;

/**
 * Builds the sidebar and the account menu from core menus.
 *
 * Sidebar: the "drubricks" menu. Top-level links without a target
 * (route <nolink>) are the section headings, their children the items;
 * other top-level links form the group on top (Home). Account menu: core's
 * "account" menu. Both are edited in Menu UI; apps add links in
 * MODULE.links.menu.yml. Link options / metadata read here:
 * - options.icon: {pack_id, icon_id} (core's Icon API, like the Navigation
 *   module's links);
 * - metadata.count: an entity type id — the item shows how many records of
 *   it the user may see;
 * - metadata.color: kit colour token of the icon;
 * - metadata.danger, metadata.separator_before: account menu styling.
 */
final class NavigationBuilder {

  use AccessibleRecordsTrait;

  /**
   * Menu of the sidebar.
   */
  public const SIDEBAR_MENU = 'drubricks';

  /**
   * Core's user account menu.
   */
  public const ACCOUNT_MENU = 'account';

  public function __construct(
    private readonly MenuLinkTreeInterface $menuTree,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly RouteProviderInterface $routeProvider,
    private readonly AdminContext $adminContext,
    private readonly ThemeSettingsProvider $themeSettings,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Render array of the whole sidebar (kit .sidebar content).
   */
  public function sidebar(): array {
    // Bubbleable: links with a CSRF token (e.g. Masquerade's "Unmasquerade")
    // carry a placeholder that the renderer replaces through attachments.
    $cache = new BubbleableMetadata();
    $build = [
      '#theme' => 'drubricks_sidebar',
      '#brand' => $this->brand($cache),
      '#sections' => $this->sections($cache),
      '#account' => $this->account($cache),
    ];
    $cache->applyTo($build);
    return $build;
  }

  /**
   * The sidebar header: logo and site name, a link to Home.
   *
   * Like core's "Site branding" block: the logo of the theme settings
   * (Appearance → Settings, "Logo image") and the site name, each shown when
   * its "Toggle display" setting is on. Saving theme settings clears the
   * render cache (core's ConfigCacheTag); the site name has its config tag.
   *
   * @return array{name: string, logo: ?string, hide_name: bool, url: string, attributes: array}|null
   *   NULL when both are switched off.
   */
  public function brand(CacheableMetadata $cache): ?array {
    $site = $this->configFactory->get('system.site');
    $cache->addCacheableDependency($site);
    $logo = $this->themeSettings->getSetting('features.logo') ? $this->themeSettings->getSetting('logo.url') : NULL;
    $show_name = (bool) $this->themeSettings->getSetting('features.name');
    if (!$logo && !$show_name) {
      return NULL;
    }
    $url = Url::fromRoute('drubricks_core.home');
    $link = ['#attributes' => []];
    Swap::link($link, $url, Swap::CONTENT);
    $cache->addCacheableDependency(CacheableMetadata::createFromRenderArray($link));
    $generated = $url->toString(TRUE);
    $cache->addCacheableDependency($generated);
    return [
      'name' => (string) $site->get('name'),
      'logo' => $logo ?: NULL,
      'hide_name' => $logo && !$show_name,
      'url' => $generated->getGeneratedUrl(),
      'attributes' => $link['#attributes'],
    ];
  }

  /**
   * Sections of the sidebar the current user may see.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects the cacheability (menu, access, counters).
   *
   * @return array<int, array{id: string, title: ?string, items: array}>
   *   The top group first (id ''), then the sections in menu order; items:
   *   id, title, url, icon, color, count, attributes. Empty sections left out.
   */
  public function sections(CacheableMetadata $cache): array {
    $sections = ['' => ['id' => '', 'title' => NULL, 'items' => []]];
    foreach ($this->menuItems(self::SIDEBAR_MENU, 2, $cache) as $id => $item) {
      if ($this->isHeading($item['original_link'])) {
        $sections[$id] = ['id' => $id, 'title' => (string) $item['title'], 'items' => []];
        foreach ($item['below'] as $child_id => $child) {
          $sections[$id]['items'][] = $this->sidebarItem($child_id, $child, $cache);
        }
      }
      else {
        $sections['']['items'][] = $this->sidebarItem($id, $item, $cache);
      }
    }
    return array_values(array_filter($sections, static fn ($s) => $s['items'] !== []));
  }

  /**
   * Account button data + menu items for UI.setAccountMenu().
   *
   * @param \Drupal\Core\Render\BubbleableMetadata $cache
   *   Collects cacheability and the attachments of the links: a link to a
   *   route with a CSRF token gets a placeholder for the token (core's
   *   RouteProcessorCsrf), replaced when the page is rendered.
   */
  public function account(BubbleableMetadata $cache): array {
    $cache->addCacheContexts(['user']);
    if ($this->currentUser->isAnonymous()) {
      return [];
    }
    $items = [];
    foreach ($this->menuItems(self::ACCOUNT_MENU, 1, $cache) as $id => $item) {
      $link = $item['original_link'];
      $metadata = $link->getMetaData();
      if (!empty($metadata['separator_before']) && $items) {
        $items[] = '-';
      }
      $generated = $item['url']->toString(TRUE);
      $cache->addCacheableDependency($generated);
      $cache->addAttachments($generated->getAttachments());
      $items[] = [
        'id' => $id,
        'label' => (string) $item['title'],
        'icon' => $this->icon($link),
        'href' => $generated->getGeneratedUrl(),
        'danger' => !empty($metadata['danger']),
      ];
    }
    $name = $this->currentUser->getDisplayName();
    return [
      'name' => $name,
      'email' => $this->currentUser->getEmail(),
      'initials' => self::initials($name),
      'items' => $items,
    ];
  }

  /**
   * Items of a menu the user may see, in menu order (core's tree build).
   *
   * @return array<string, array>
   *   Items of MenuLinkTreeInterface::build() (title, url, below,
   *   original_link …) keyed by link id.
   */
  private function menuItems(string $menu_name, int $depth, CacheableMetadata $cache): array {
    // All links: the build skips a disabled link with its children (with
    // onlyEnabledLinks() the storage would hang the children of a disabled
    // section under the previous one).
    $parameters = (new MenuTreeParameters())->setMaxDepth($depth);
    $tree = $this->menuTree->transform($this->menuTree->load($menu_name, $parameters), [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);
    $build = $this->menuTree->build($tree);
    // The menu's config tag, access and link cacheability.
    $cache->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    return $build['#items'] ?? [];
  }

  /**
   * Whether a top-level sidebar link is a section heading (no target).
   */
  private function isHeading(MenuLinkInterface $link): bool {
    return $link->getUrlObject()->isRouted() && $link->getRouteName() === '<nolink>';
  }

  /**
   * One sidebar item: link, icon, colour, counter, HTMX swap attributes.
   */
  private function sidebarItem(string $id, array $item, CacheableMetadata $cache): array {
    $link = $item['original_link'];
    $metadata = $link->getMetaData();
    $url = $item['url'];
    assert($url instanceof Url);
    $element = ['#attributes' => []];
    if ($this->isAppUrl($url)) {
      Swap::link($element, $url, Swap::CONTENT);
      $cache->addCacheableDependency(CacheableMetadata::createFromRenderArray($element));
    }
    $generated = $url->toString(TRUE);
    $cache->addCacheableDependency($generated);
    return [
      'id' => $id,
      'title' => (string) $item['title'],
      'url' => $generated->getGeneratedUrl(),
      'icon' => $this->icon($link),
      'color' => $metadata['color'] ?? NULL,
      'count' => isset($metadata['count']) ? $this->count((string) $metadata['count'], $cache) : NULL,
      'attributes' => $element['#attributes'],
    ];
  }

  /**
   * The link's icon (options.icon): a kit name, or "pack:icon" of other packs.
   */
  private function icon(MenuLinkInterface $link): ?string {
    $icon = $link->getOptions()['icon'] ?? NULL;
    if (!isset($icon['pack_id'], $icon['icon_id'])) {
      return NULL;
    }
    // Kit sprite ids are "i-<name>"; templates and the kit JS take the name.
    return $icon['pack_id'] === 'kit' ? preg_replace('/^i-/', '', $icon['icon_id']) : $icon['pack_id'] . ':' . $icon['icon_id'];
  }

  /**
   * Number of records of a type the current user may see (own / any).
   */
  private function count(string $entity_type_id, CacheableMetadata $cache): ?int {
    $type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
    $query = $type ? $this->accessibleQuery($entity_type_id, $this->currentUser) : NULL;
    if (!$type || !$query) {
      return NULL;
    }
    $cache->addCacheTags($type->getListCacheTags())->addCacheContexts(['user']);
    return (int) $query->count()->execute();
  }

  /**
   * Whether a URL opens inside the app shell (not admin pages, not external).
   */
  private function isAppUrl(Url $url): bool {
    if (!$url->isRouted()) {
      return FALSE;
    }
    try {
      $route = $this->routeProvider->getRouteByName($url->getRouteName());
    }
    catch (\Exception) {
      return FALSE;
    }
    return !$this->adminContext->isAdminRoute($route);
  }

  /**
   * Two-letter initials, like UI.initials().
   */
  public static function initials(string $name): string {
    $parts = preg_split('/[\s._-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : mb_substr($parts[0], 1, 1);
    return mb_strtoupper($first . $last);
  }

}
