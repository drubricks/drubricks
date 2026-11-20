<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_examples_booking\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\drubricks_core\Traits\DashboardBlocksTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The app's dashboard blocks, placed in the theme's dashboard regions.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class DashboardBlocksTest extends KernelTestBase {

  use DashboardBlocksTestTrait;
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
    'datetime',
    'link',
    'drubricks_core',
    'drubricks_examples_booking',
  ];

  /**
   * Placements in order, visible to the app's users only; they build.
   */
  public function testDashboardBlocks(): void {
    $this->installEntitySchema('booking_resource');
    $this->installEntitySchema('booking');
    $this->assertDashboardBlocks('drubricks_examples_booking', [
      'dashboard_stats' => [
        'drubricks_booking_today_stats',
      ],
      'dashboard' => [
        'drubricks_booking_next',
        'drubricks_booking_busiest',
      ],
    ], 'administer booking');
  }

}
