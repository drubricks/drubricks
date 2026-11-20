<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Icons through core's Icon API: the theme's "kit" pack and app packs.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class IconsTest extends KernelTestBase {

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
   * The kit sprite is a core svg_sprite pack with a versioned URL.
   */
  public function testKitPack(): void {
    $this->container->get('theme_installer')->install(['drubricks']);
    $icon = $this->container->get('plugin.manager.icon_pack')->getIcon('kit:i-pencil');
    $this->assertNotNull($icon, 'kit:i-pencil');
    $this->assertMatchesRegularExpression('#/kit/icons\.[0-9a-f]{8}\.svg$#', (string) $icon->getSource());
    // The kit's JavaScript gets the same sprite (KitInfo reads the active
    // theme, as on a page of the app).
    $theme = $this->container->get('theme.initialization')->initTheme('drubricks');
    $this->container->get('theme.manager')->setActiveTheme($theme);
    $this->assertSame($icon->getSource(), $this->container->get('drubricks_core.kit')->initOptions()['iconSprite']);
  }

  /**
   * Sidebar items take kit names or a full "pack:icon" of an app's pack.
   */
  public function testSidebarIcons(): void {
    $this->container->get('theme_installer')->install(['drubricks']);
    $build = [
      '#theme' => 'drubricks_sidebar',
      '#sections' => [[
        'id' => 'app',
        'title' => 'App',
        'items' => [
          $this->item('kit', 'users'),
          $this->item('own', 'drubricks_test:star'),
        ],
      ]],
    ];
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertMatchesRegularExpression('#<use href="[^"]*/kit/icons\.[0-9a-f]{8}\.svg\#i-users"#', $html);
    $this->assertStringContainsString('class="test-icon"', $html);
    $this->assertStringContainsString('M12 2l3 7h7', $html);
  }

  /**
   * A sidebar item with an icon.
   *
   * @return array<string, mixed>
   *   The item as NavigationBuilder builds it.
   */
  private function item(string $id, string $icon): array {
    return [
      'id' => $id,
      'title' => ucfirst($id),
      'url' => '/' . $id,
      'icon' => $icon,
      'color' => NULL,
      'count' => NULL,
      'attributes' => [],
    ];
  }

}
