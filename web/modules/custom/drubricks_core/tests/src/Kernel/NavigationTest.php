<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Sidebar and account menu built from core menus (drubricks, account).
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class NavigationTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'link',
    'menu_link_content',
    'views',
    'inline_form_errors',
    'options',
    'drubricks_core',
    'drubricks_core_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('drubricks_test_record');
    $this->installEntitySchema('menu_link_content');
    $this->installConfig(['system', 'drubricks_core']);
    // Menu links of the enabled modules (a kernel test discovers none).
    $this->container->get('plugin.manager.menu.link')->rebuild();
    // User 1 is special; create it first so tests use a normal account.
    $this->createUser();
  }

  /**
   * Sections and items are sorted by weight; empty sections are hidden.
   */
  public function testSidebarOrderAndVisibility(): void {
    $this->setCurrentUser($this->createUser(['view own drubricks_test_record']));
    $build = $this->container->get('drubricks_core.navigation')->sidebar();
    $sections = $build['#sections'];

    $this->assertSame([
      '',
      'drubricks_core_test.early_section',
      'drubricks_core_test.section',
    ], array_column($sections, 'id'));
    $this->assertSame(['drubricks_core.home'], array_column($sections[0]['items'], 'id'));
    $this->assertSame('house', $sections[0]['items'][0]['icon']);
    $this->assertSame('Test app', $sections[2]['title']);
    $expected = ['drubricks_core_test.dynamic', 'drubricks_core_test.records'];
    $this->assertSame($expected, array_column($sections[2]['items'], 'id'));
    // A class-based link: dynamic title, no counter.
    $this->assertSame('Dynamic 42', $sections[2]['items'][0]['title']);
    $this->assertNull($sections[2]['items'][0]['count']);
    $records = $sections[2]['items'][1];
    $this->assertSame(0, $records['count']);
    $this->assertSame('folder', $records['icon']);
    $this->assertSame('/apps/test/records', $records['url']);
    $this->assertSame('drubricks-app', ltrim($records['attributes']['data-hx-target'], '#'));

    // Cacheability: the menu, the counted records, the user.
    $tags = $build['#cache']['tags'];
    $this->assertContains('config:system.menu.drubricks', $tags);
    $this->assertContains('drubricks_test_record_list', $tags);
    $this->assertContains('user', $build['#cache']['contexts']);
  }

  /**
   * Site builders reorder, disable and add links in Menu UI.
   */
  public function testMenuUiChanges(): void {
    $this->setCurrentUser($this->createUser(['view own drubricks_test_record']));
    // Menu UI saves changes of module links as overrides (updateDefinition).
    $links = $this->container->get('plugin.manager.menu.link');
    // "Early section" after "Test app"; "Records" before "Dynamic".
    $links->updateDefinition('drubricks_core_test.early_section', ['weight' => 50]);
    $links->updateDefinition('drubricks_core_test.records', ['weight' => -10]);
    // A link added in Menu UI to the "Test app" section.
    $this->container->get('entity_type.manager')->getStorage('menu_link_content')->create([
      'title' => 'Handbook',
      'menu_name' => 'drubricks',
      'parent' => 'drubricks_core_test.section',
      'link' => ['uri' => 'internal:/apps'],
      'weight' => 100,
    ])->save();

    $sections = $this->container->get('drubricks_core.navigation')->sections(new CacheableMetadata());
    $this->assertSame(['', 'drubricks_core_test.section', 'drubricks_core_test.early_section'], array_column($sections, 'id'));
    $this->assertSame(['Records', 'Dynamic 42', 'Handbook'], array_column($sections[1]['items'], 'title'));

    // A disabled section hides its screens.
    $links->updateDefinition('drubricks_core_test.early_section', ['enabled' => FALSE]);
    $sections = $this->container->get('drubricks_core.navigation')->sections(new CacheableMetadata());
    $this->assertSame(['', 'drubricks_core_test.section'], array_column($sections, 'id'));
  }

  /**
   * Items whose route is not accessible disappear (and then their section).
   */
  public function testSidebarAccess(): void {
    $this->setCurrentUser($this->createUser([]));
    $sections = $this->container->get('drubricks_core.navigation')->sections(new CacheableMetadata());
    $test = array_values(array_filter($sections, static fn ($s) => $s['id'] === 'drubricks_core_test.section'))[0];
    $this->assertSame(['drubricks_core_test.dynamic'], array_column($test['items'], 'id'));
  }

  /**
   * Counters respect own / any.
   */
  public function testCountOwnAndAny(): void {
    $alice = $this->createUser(['view own drubricks_test_record']);
    $bob = $this->createUser(['view any drubricks_test_record']);
    $storage = $this->container->get('entity_type.manager')->getStorage('drubricks_test_record');
    $storage->create(['name' => 'A', 'uid' => $alice->id()])->save();
    $storage->create(['name' => 'B', 'uid' => $bob->id()])->save();
    $storage->create(['name' => 'C', 'uid' => $bob->id()])->save();

    $count = function ($account): ?int {
      $this->setCurrentUser($account);
      $sections = $this->container->get('drubricks_core.navigation')->sections(new CacheableMetadata());
      $items = array_column(array_merge(...array_column($sections, 'items')), 'count', 'id');
      return $items['drubricks_core_test.records'];
    };
    $this->assertSame(1, $count($alice));
    $this->assertSame(3, $count($bob));
  }

  /**
   * The sidebar header: theme logo and site name, by the theme settings.
   */
  public function testBrand(): void {
    $this->container->get('theme_installer')->install(['drubricks']);
    $this->config('system.theme')->set('default', 'drubricks')->save();
    $this->config('system.site')->set('name', 'Acme')->save();
    $this->setCurrentUser($this->createUser([]));
    $navigation = $this->container->get('drubricks_core.navigation');

    $cache = new CacheableMetadata();
    $brand = $navigation->brand($cache);
    $this->assertSame('Acme', $brand['name']);
    $this->assertStringEndsWith('/themes/custom/drubricks/logo.svg', $brand['logo']);
    $this->assertFalse($brand['hide_name']);
    $this->assertSame('/apps', $brand['url']);
    $this->assertSame('#drubricks-app', $brand['attributes']['data-hx-target']);
    $this->assertContains('config:system.site', $cache->getCacheTags());

    // An uploaded logo, the site name switched off ("Toggle display").
    $this->config('drubricks.settings')
      ->set('logo', ['use_default' => FALSE, 'path' => 'public://acme.svg'])
      ->set('features.name', FALSE)
      ->save();
    $brand = $navigation->brand(new CacheableMetadata());
    $this->assertStringEndsWith('/acme.svg', $brand['logo']);
    $this->assertTrue($brand['hide_name']);

    // Neither logo nor name: no brand.
    $this->config('drubricks.settings')->set('features.logo', FALSE)->save();
    $this->assertNull($navigation->brand(new CacheableMetadata()));
  }

  /**
   * The account menu: core defaults + module links, sorted by weight.
   */
  public function testAccountMenu(): void {
    $jane = $this->createUser([], 'jane.doe');
    $this->setCurrentUser($jane);
    $cache = new BubbleableMetadata();
    $account = $this->container->get('drubricks_core.navigation')->account($cache);
    $ids = array_map(static fn ($i) => is_array($i) ? $i['id'] : $i, $account['items']);
    $this->assertSame([
      'user.page',
      'drubricks_core.profile_edit',
      'drubricks_core_test.help',
      '-',
      'user.logout',
    ], $ids);
    $this->assertSame('View profile', $account['items'][0]['label']);
    $this->assertSame('user', $account['items'][0]['icon']);
    $this->assertSame('/user/' . $jane->id() . '/edit', $account['items'][1]['href']);
    $logout = end($account['items']);
    $this->assertSame('Log out', $logout['label']);
    $this->assertTrue($logout['danger']);
    $this->assertSame('JD', $account['initials']);
    $this->assertContains('config:system.menu.account', $cache->getCacheTags());
    $this->assertContains('user', $cache->getCacheContexts());
  }

}
