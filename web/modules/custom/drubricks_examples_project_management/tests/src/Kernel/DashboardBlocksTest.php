<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_examples_project_management\Kernel;

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
    'charts',
    'charts_chartjs',
    'drubricks_core',
    'drubricks_examples_project_management',
  ];

  /**
   * Placements in order, visible to the app's users only; they build.
   */
  public function testDashboardBlocks(): void {
    $this->installEntitySchema('pm_project');
    $this->installEntitySchema('pm_task');
    $this->assertDashboardBlocks('drubricks_examples_project_management', [
      'dashboard_stats' => [
        'drubricks_pm_open_tasks',
        'drubricks_pm_overdue_tasks',
      ],
      'dashboard' => [
        'drubricks_pm_my_tasks',
        'drubricks_pm_projects',
        'drubricks_pm_tasks_by_status',
      ],
    ], 'administer project management');
  }

}
