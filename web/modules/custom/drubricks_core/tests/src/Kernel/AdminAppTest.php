<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The "Application" admin pages (/admin/app, Entities) and sidebar links.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class AdminAppTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
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
    $this->installConfig(['system']);
    $this->createUser();
  }

  /**
   * The link sits under the admin root, right before Reports, with an icon.
   */
  public function testMenuLink(): void {
    $manager = $this->container->get('plugin.manager.menu.link');
    $manager->rebuild();
    $link = $manager->getDefinition('drubricks_core.admin_app');
    $this->assertSame('system.admin', $link['parent']);
    $this->assertSame('drubricks_core.admin_app', $link['route_name']);
    // Same weight as Reports (system.admin_reports); ties sort by title.
    $this->assertSame(5, (int) $link['weight']);
    $this->assertSame(['pack_id' => 'drubricks', 'icon_id' => 'application'], $link['options']['icon']);
    $this->assertNotNull($this->container->get('plugin.manager.icon_pack')->getIcon('drubricks:application'));
    // "Entity records": the app modules link their admin views into it;
    // "Entity structure": a link per entity type to its Field UI admin page.
    foreach (['drubricks_core.admin_app_entities', 'drubricks_core.admin_app_structure'] as $id) {
      $this->assertSame('drubricks_core.admin_app', $manager->getDefinition($id)['parent']);
    }
    $records = $manager->getDefinition('drubricks_core.entity_structure:drubricks_test_record');
    $this->assertSame('drubricks_core.admin_app_structure', $records['parent']);
    $this->assertSame('entity.drubricks_test_record.settings', $records['route_name']);
  }

  /**
   * Administrators open the pages; other users do not.
   */
  public function testPages(): void {
    $admin = $this->createUser(['access administration pages']);
    $provider = $this->container->get('router.route_provider');
    // Section pages like /admin/config: core shows them (and their sidebar
    // links) only once an app links something under them — none here.
    foreach ([
      'drubricks_core.admin_app' => ['/admin/app', '_access_admin_overview_page'],
      'drubricks_core.admin_app_entities' => ['/admin/app/entities', '_access_admin_menu_block_page'],
      'drubricks_core.admin_app_structure' => ['/admin/app/structure', '_access_admin_menu_block_page'],
    ] as $name => [$path, $requirement]) {
      $url = Url::fromRoute($name);
      $this->assertSame($path, $url->toString());
      $route = $provider->getRouteByName($name);
      $this->assertSame('access administration pages', $route->getRequirement('_permission'));
      $this->assertSame('TRUE', $route->getRequirement($requirement));
      $this->assertFalse($url->access($admin), "$path is hidden while no app links an admin view");
    }

    // Application → Settings (sidebar, dashboard, branding, locale) is there
    // from the first install: a site admin sees Application with no app yet.
    $site_admin = $this->createUser(['access administration pages', 'administer site configuration']);
    $this->container->get('plugin.manager.menu.link')->rebuild();
    $this->assertSame('/admin/app/settings', Url::fromRoute('drubricks_core.admin_app_settings')->toString());
    $this->assertTrue(Url::fromRoute('drubricks_core.admin_app_settings')->access($site_admin));
    $this->assertTrue(Url::fromRoute('drubricks_core.admin_app')->access($site_admin), '/admin/app is visible with no app');
  }

}
