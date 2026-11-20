<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Views data of Drubricks entities: filters with a value form for base fields.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class ViewsDataTest extends KernelTestBase {

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
   * Single- and multi-value list_string base fields get core's list filter.
   */
  public function testListFilters(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('drubricks_test_record');
    $views_data = $this->container->get('views.views_data');

    // Single value (the base class's colour): a column of the base table.
    $base = $views_data->get('drubricks_test_record');
    $this->assertSame('list_field', $base['color']['filter']['id']);
    $this->assertSame('color', $base['color']['filter']['field_name']);

    // Multi-value: its own table, column NAME_value.
    $kinds = $views_data->get('drubricks_test_record__kinds');
    $this->assertSame('list_field', $kinds['kinds_value']['filter']['id']);
    $this->assertSame('kinds', $kinds['kinds_value']['filter']['field_name']);
  }

  /**
   * References filter with core's entity_reference filter (a select).
   */
  public function testReferenceFilter(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('drubricks_test_record');
    $base = $this->container->get('views.views_data')->get('drubricks_test_record');
    $this->assertSame('entity_reference', $base['uid']['filter']['id']);
    $this->assertSame('uid', $base['uid']['filter']['field_name']);
  }

}
