<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\drubricks_core_test\Entity\TestRecord;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Own / any permissions of the Drubricks access handler and base fields.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class AccessTest extends KernelTestBase {

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
    $this->installEntitySchema('drubricks_test_record');
    $this->createUser();
  }

  /**
   * Permissions are generated for the entity type.
   */
  public function testPermissionsGenerated(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();
    foreach (['create', 'view own', 'view any', 'edit own', 'edit any', 'delete own', 'delete any'] as $operation) {
      $this->assertArrayHasKey("$operation drubricks_test_record", $permissions);
    }
  }

  /**
   * Own vs any for view, update and delete; create; admin.
   */
  public function testOwnAndAny(): void {
    $owner = $this->createUser([
      'view own drubricks_test_record',
      'edit own drubricks_test_record',
      'create drubricks_test_record',
    ]);
    $other = $this->createUser(['view own drubricks_test_record', 'edit own drubricks_test_record']);
    $manager = $this->createUser(['view any drubricks_test_record', 'delete any drubricks_test_record']);
    $admin = $this->createUser(['administer drubricks test records']);

    $record = $this->container->get('entity_type.manager')->getStorage('drubricks_test_record')
      ->create(['name' => 'Mine', 'uid' => $owner->id(), 'color' => 'teal']);
    $record->save();

    $this->assertTrue($record->access('view', $owner));
    $this->assertTrue($record->access('update', $owner));
    $this->assertFalse($record->access('delete', $owner));

    $this->assertFalse($record->access('view', $other));
    $this->assertFalse($record->access('update', $other));

    $this->assertTrue($record->access('view', $manager));
    $this->assertFalse($record->access('update', $manager));
    $this->assertTrue($record->access('delete', $manager));

    $this->assertTrue($record->access('delete', $admin));

    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('drubricks_test_record');
    $this->assertTrue($handler->createAccess(NULL, $owner));
    $this->assertFalse($handler->createAccess(NULL, $other));
  }

  /**
   * Base fields: owner, dates, status and a colour token.
   */
  public function testBaseFields(): void {
    $record = TestRecord::create(['name' => 'X', 'color' => 'teal']);
    $record->save();
    $this->assertSame('teal', $record->getColor());
    $this->assertTrue($record->isPublished());
    $this->assertGreaterThan(0, $record->getCreatedTime());
    $this->assertCount(0, $record->validate());

    $record->set('color', '#ff0000');
    $this->assertGreaterThan(0, $record->validate()->count(), 'Only kit colour tokens are allowed.');
  }

  /**
   * Entity queries (lists, Views, references) follow own / any too.
   */
  public function testQueriesFollowOwnAndAny(): void {
    $owner = $this->createUser(['view own drubricks_test_record']);
    $other = $this->createUser(['view own drubricks_test_record']);
    $manager = $this->createUser(['view any drubricks_test_record']);
    $nobody = $this->createUser();
    $mine = TestRecord::create(['name' => 'Mine', 'uid' => $owner->id()]);
    $mine->save();
    $theirs = TestRecord::create(['name' => 'Theirs', 'uid' => $other->id()]);
    $theirs->save();
    $visible = fn ($account) => array_map('intval', array_values($this->container->get('entity_type.manager')
      ->getStorage('drubricks_test_record')->getQuery()
      ->accessCheck(TRUE)
      ->addMetaData('account', $account)
      ->sort('id')
      ->execute()));
    $this->assertSame([(int) $mine->id()], $visible($owner), 'own records only');
    $this->assertSame([(int) $mine->id(), (int) $theirs->id()], $visible($manager), 'view any: all records');
    $this->assertSame([], $visible($nobody), 'no view permission: nothing');
  }

}
