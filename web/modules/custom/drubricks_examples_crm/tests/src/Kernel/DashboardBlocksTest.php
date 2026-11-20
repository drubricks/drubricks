<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_examples_crm\Kernel;

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
    'drubricks_examples_crm',
  ];

  /**
   * Placements in order, visible to the app's users only; they build.
   */
  public function testDashboardBlocks(): void {
    $this->installEntitySchema('crm_company');
    $this->installEntitySchema('crm_contact');
    $this->installEntitySchema('crm_deal');
    $this->assertDashboardBlocks('drubricks_examples_crm', [
      'dashboard_stats' => [
        'drubricks_crm_open_pipeline',
        'drubricks_crm_won',
      ],
      'dashboard' => [
        'drubricks_crm_closing_soon',
        'drubricks_crm_pipeline_by_stage',
        'drubricks_crm_recent_contacts',
      ],
    ], 'administer crm');
  }

}
