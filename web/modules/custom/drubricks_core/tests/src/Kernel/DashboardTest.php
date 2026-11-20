<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\drubricks_core\Controller\AppController;
use Drupal\drubricks_core\Hook\DashboardHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Home dashboard: blocks placed in the theme's dashboard regions.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class DashboardTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
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
    $this->installConfig(['system', 'drubricks_core']);
    $this->container->get('theme_installer')->install(['drubricks']);
    $this->config('system.theme')->set('default', 'drubricks')->save();
    // User 1 is special; create it first so tests use a normal account.
    $this->createUser();

    // A count tile, a panel that stays empty and a panel with its own access.
    $this->place('drubricks_record_count', 'records_count', 'dashboard_stats', 5, [
      'label' => 'Records',
      'label_display' => '0',
      'entity_type' => 'drubricks_test_record',
      'icon' => 'folder',
      'color' => 'teal',
    ]);
    $this->place('drubricks_core_test_empty', 'empty', 'dashboard', -10, ['label' => 'Empty']);
    $this->place('drubricks_core_test_records', 'records', 'dashboard', 10, ['label' => 'All records'], [
      'size' => 'wide',
      'icon' => 'folder',
      'color' => 'blue',
    ]);
  }

  /**
   * Places a block in the drubricks theme.
   */
  private function place(string $plugin, string $id, string $region, int $weight, array $settings, array $panel = []): void {
    $block = Block::create([
      'id' => $id,
      'theme' => 'drubricks',
      'region' => $region,
      'weight' => $weight,
      'plugin' => $plugin,
      'settings' => $settings + ['label_display' => 'visible'],
    ]);
    foreach ($panel as $key => $value) {
      $block->setThirdPartySetting('drubricks_core', $key, $value);
    }
    $block->save();
  }

  /**
   * Blocks of the dashboard regions the current user may see.
   *
   * @return array<string, string[]>
   *   Block ids keyed by region.
   */
  private function visible(): array {
    $regions = $this->container->get('block.repository')->getVisibleBlocksPerRegion();
    return ['stats' => array_keys($regions['dashboard_stats']), 'panels' => array_keys($regions['dashboard'])];
  }

  /**
   * Route access of the link target and the block's own access rule.
   */
  public function testAccess(): void {
    $this->setCurrentUser($this->createUser(['view any drubricks_test_record']));
    $this->assertSame(['stats' => ['records_count'], 'panels' => ['empty', 'records']], $this->visible());

    // Own records only: no "all records" panel (blockAccess()).
    $this->setCurrentUser($this->createUser(['view own drubricks_test_record']));
    $this->assertSame(['stats' => ['records_count'], 'panels' => ['empty']], $this->visible());

    // No access to the records screen: the linked count tile disappears.
    $this->setCurrentUser($this->createUser([]));
    $this->assertSame(['stats' => [], 'panels' => ['empty']], $this->visible());
  }

  /**
   * The record count block counts the records the user may see.
   */
  public function testRecordCount(): void {
    $alice = $this->createUser(['view own drubricks_test_record']);
    $bob = $this->createUser(['view any drubricks_test_record']);
    $storage = $this->container->get('entity_type.manager')->getStorage('drubricks_test_record');
    $storage->create(['name' => 'A', 'uid' => $alice->id()])->save();
    $storage->create(['name' => 'B', 'uid' => $bob->id()])->save();

    $this->setCurrentUser($alice);
    $plugin = Block::load('records_count')->getPlugin();
    $stat = $plugin->build();
    $this->assertSame('drubricks:stat', $stat['#component']);
    $this->assertSame('Records', $stat['#props']['label']);
    $this->assertSame(1, $stat['#props']['value']);
    $this->assertSame('folder', $stat['#props']['icon']);
    $this->assertSame('teal', $stat['#props']['color']);
    $this->assertSame('/apps/test/records', $stat['#props']['url']);
    $this->assertSame('#drubricks-app', $stat['#props']['attributes']['data-hx-target']->value());
    $this->assertContains('drubricks_test_record_list', $plugin->getCacheTags());
    $this->assertContains('user', $plugin->getCacheContexts());

    $this->setCurrentUser($bob);
    $this->assertSame(2, Block::load('records_count')->getPlugin()->build()['#props']['value']);

    // The placement goes away with the module that provides the records.
    $this->assertContains('drubricks_core_test', Block::load('records_count')->getDependencies()['module']);
  }

  /**
   * The home page: tiles on top, panels with heading and link, no empties.
   */
  public function testHome(): void {
    $this->setCurrentUser($this->createUser(['view any drubricks_test_record']));
    $build = $this->container->get('class_resolver')->getInstanceFromDefinition(AppController::class)->home();
    $this->assertContains('config:block_list', $build['#cache']['tags']);
    $this->render($build);

    $tiles = $this->cssSelect('.drubricks-dashboard .stats > div > .stat');
    $this->assertCount(1, $tiles);
    $this->assertStringContainsString('Records', (string) $tiles[0]->asXML());

    $panels = $this->cssSelect('.drubricks-dashboard [data-drubricks-dashboard]');
    $this->assertCount(1, $panels, 'The empty panel is left out.');
    $panel = $panels[0];
    $this->assertSame('drubricks_core_test_records', (string) $panel['data-drubricks-dashboard']);
    $this->assertStringContainsString('col-span-2', (string) $panel['class']);
    $this->assertSame('All records', trim((string) $panel->h3));
    $this->assertStringContainsString('All records panel', (string) $panel->asXML());
    $link = $panel->h3->a;
    $this->assertSame('Show all', (string) $link);
    $this->assertSame('/apps/test/records', (string) $link['href']);
    $this->assertSame('#drubricks-app', (string) $link['data-hx-target']);
    $this->assertCount(0, $this->cssSelect('.drubricks-dashboard .insp-empty'));
  }

  /**
   * No visible dashboard block: the empty state.
   */
  public function testEmptyDashboard(): void {
    $this->setCurrentUser($this->createUser([]));
    Block::load('empty')->delete();
    $build = $this->container->get('class_resolver')->getInstanceFromDefinition(AppController::class)->home();
    $this->render($build);
    $this->assertCount(0, $this->cssSelect('.drubricks-dashboard .stats'));
    $this->assertCount(1, $this->cssSelect('.drubricks-dashboard .insp-empty'));
  }

  /**
   * The block form stores the panel settings only for the panels region.
   */
  public function testPanelSettings(): void {
    $hooks = $this->container->get('class_resolver')->getInstanceFromDefinition(DashboardHooks::class);
    $form = [];
    $form_state = (new FormState())->setValue('drubricks_dashboard', [
      'size' => 'full',
      'icon' => ' users ',
      'color' => 'green',
    ]);
    $block = Block::load('records');
    $hooks->blockFormBuilder('block', $block, $form, $form_state);
    $this->assertSame(['size' => 'full', 'icon' => 'users', 'color' => 'green'], $block->getThirdPartySettings('drubricks_core'));
    $block->save();
    $this->assertContains('drubricks_core', $block->getDependencies()['module']);

    // Moved out of the panels region: no panel settings left.
    $block->setRegion('content');
    $hooks->blockFormBuilder('block', $block, $form, $form_state);
    $this->assertSame([], $block->getThirdPartySettings('drubricks_core'));
  }

}
