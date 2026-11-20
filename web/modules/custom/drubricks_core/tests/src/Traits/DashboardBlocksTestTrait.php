<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Traits;

use Drupal\block\Entity\Block;
use Drupal\drubricks_core\Dashboard\DashboardBlockInterface;

/**
 * Checks of an app's dashboard blocks and their placements, for kernel tests.
 *
 * @code
 * $this->assertDashboardBlocks('drubricks_examples_crm', [
 *   'dashboard_stats' => ['drubricks_crm_pipeline_stats'],
 *   'dashboard' => ['drubricks_crm_closing_soon', …],
 * ], 'administer crm');
 * @endcode
 * The test installs the app's entity schemas first; installing the
 * placements (config/optional of the module) validates them against the
 * config schema (kernel tests are strict about it). Needs the modules system,
 * user, block and the app with its dependencies, and UserCreationTrait.
 */
trait DashboardBlocksTestTrait {

  /**
   * The placements exist in order; an admin sees them, they build.
   *
   * @param string $module
   *   The app module.
   * @param array<string, string[]> $regions
   *   Ids of the module's placements per dashboard region, in their order.
   * @param string $admin_permission
   *   The app's admin permission.
   * @param string[] $per_user
   *   Placements shown only to some users (e.g. "my next lessons" for users
   *   with a lecturer profile): they must exist, but the admin of the checks
   *   need not see them. Leave them out of $regions; test them in the app.
   */
  protected function assertDashboardBlocks(string $module, array $regions, string $admin_permission, array $per_user = []): void {
    $this->installEntitySchema('user');
    $this->installConfig(['system']);
    $this->container->get('theme_installer')->install(['drubricks']);
    $this->config('system.theme')->set('default', 'drubricks')->save();
    $this->container->get('config.installer')->installOptionalConfig();
    $this->container->get('router.builder')->rebuild();

    // User 1 is special; create it first so the checks use normal accounts.
    $this->createUser();
    $this->setCurrentUser($this->createUser([$admin_permission]));
    $visible = $this->container->get('block.repository')->getVisibleBlocksPerRegion();
    foreach ($regions as $region => $ids) {
      $this->assertSame($ids, array_keys(array_filter($visible[$region], static fn ($block) => $block->getPlugin()->getPluginDefinition()['provider'] === $module && !in_array($block->id(), $per_user, TRUE))), "$region of $module");
      foreach ($ids as $id) {
        $block = Block::load($id);
        $plugin = $block->getPlugin();
        $this->assertInstanceOf(DashboardBlockInterface::class, $plugin, $id);
        $this->assertContains($module, $block->getDependencies()['module'], "$id goes away with $module");
        // They build with no records at all.
        $this->assertNotEmpty($plugin->build(), $id);
      }
    }

    foreach ($per_user as $id) {
      $block = Block::load($id);
      $this->assertNotNull($block, "$id is placed");
      $this->assertInstanceOf(DashboardBlockInterface::class, $block->getPlugin(), $id);
    }

    // A user without the app's permissions sees none of them.
    $this->setCurrentUser($this->createUser([]));
    $visible = $this->container->get('block.repository')->getVisibleBlocksPerRegion();
    foreach (array_keys($regions) as $region) {
      $this->assertSame([], array_keys(array_filter($visible[$region], static fn ($block) => $block->getPlugin()->getPluginDefinition()['provider'] === $module && !in_array($block->id(), $per_user, TRUE))), "$region of $module, no permissions");
    }
  }

}
