<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Designed screens follow Manage display (ScreenHandler display helpers).
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class DisplayFieldsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'field',
    'text',
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
    $this->installConfig(['system', 'field']);
    $this->createUser();
    $this->setUpCurrentUser([], ['view any drubricks_test_record']);

    // A field added in Manage fields, shown in Manage display after the owner.
    FieldStorageConfig::create([
      'field_name' => 'field_notes',
      'entity_type' => 'drubricks_test_record',
      'type' => 'string_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_notes',
      'entity_type' => 'drubricks_test_record',
      'bundle' => 'drubricks_test_record',
      'label' => 'Notes',
    ])->save();
    EntityViewDisplay::create([
      'targetEntityType' => 'drubricks_test_record',
      'bundle' => 'drubricks_test_record',
      'mode' => 'default',
      'status' => TRUE,
    ])
      ->setComponent('uid', ['type' => 'author', 'weight' => 0])
      ->setComponent('field_notes', ['type' => 'basic_string', 'label' => 'inline', 'weight' => 5])
      ->save();
  }

  /**
   * A designed screen: its fields, the rest of the display, hidden ones.
   */
  public function testDisplayHelpers(): void {
    $record = $this->container->get('entity_type.manager')->getStorage('drubricks_test_record')->create([
      'name' => 'A',
      'field_notes' => 'Call after 3 pm.',
    ]);
    $record->save();
    $screen = $this->container->get('entity_type.manager')->getHandler('drubricks_test_record', 'drubricks_screen');
    $call = static fn (string $method, ...$args) => (fn () => $this->{$method}(...$args))->call($screen);

    // The screen places the owner; the rest is the new field, rendered with
    // its formatter, carrying the display's cache tag.
    $this->assertTrue($call('shows', $record, 'uid'));
    $fields = $call('displayFields', $record, ['uid']);
    $this->assertSame('drubricks_fields', $fields['#theme']);
    $this->assertSame(['field_notes'], array_values(array_filter(array_keys($fields['#content']), static fn ($key) => $key[0] !== '#')));
    $this->assertContains('config:core.entity_view_display.drubricks_test_record.drubricks_test_record.default', $fields['#cache']['tags']);
    $html = (string) $this->container->get('renderer')->renderInIsolation($fields);
    $this->assertStringContainsString('Call after 3 pm.', $html);
    $this->assertStringContainsString('Notes', $html);

    // Hidden in Manage display: not shown, also not in the designed facts.
    EntityViewDisplay::load('drubricks_test_record.drubricks_test_record.default')->removeComponent('uid')->save();
    $this->assertFalse($call('shows', $record, 'uid'));
    $facts = $call('visibleFacts', $record, [
      ['field' => 'uid', 'label' => 'Owner', 'value' => 'Ann'],
      ['label' => 'Records', 'value' => '3'],
      ['field' => 'field_notes', 'label' => 'Notes', 'value' => 'x'],
    ]);
    $this->assertSame([
      ['label' => 'Records', 'value' => '3'],
      ['label' => 'Notes', 'value' => 'x'],
    ], $facts);
    $this->assertFalse($call('shows', $record, 'not_a_field'));
  }

}
