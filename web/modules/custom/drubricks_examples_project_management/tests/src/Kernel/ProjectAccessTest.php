<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_examples_project_management\Kernel;

use Drupal\drubricks_examples_project_management\Entity\Project;
use Drupal\drubricks_examples_project_management\Entity\Task;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\drubricks_core\Traits\AdminViewTestTrait;
use Drupal\Tests\drubricks_core\Traits\FieldUiTestTrait;
use Drupal\Tests\drubricks_core\Traits\RevisionsTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Project membership: who sees which projects and tasks.
 *
 * Single records go through the access handlers; lists, counts and the
 * project field's options through hook_query_pm_*_access_alter(). Both must
 * agree.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class ProjectAccessTest extends KernelTestBase {

  use AdminViewTestTrait;
  use FieldUiTestTrait;
  use RevisionsTestTrait;
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
    'datetime',
    'text',
    'charts',
    'charts_chartjs',
    'drubricks_core',
    'drubricks_examples_project_management',
  ];

  /**
   * The permissions every worker gets (as in the App recipe).
   */
  private const WORKER = [
    'view any pm_project',
    'create pm_project',
    'edit own pm_project',
    'view any pm_task',
    'create pm_task',
    'edit own pm_task',
    'delete own pm_task',
  ];

  /**
   * Owner of Apollo.
   */
  private UserInterface $alice;

  /**
   * Member of Apollo.
   */
  private UserInterface $bob;

  /**
   * Owner of Zephyr, not in Apollo.
   */
  private UserInterface $carol;

  /**
   * Has "administer project management".
   */
  private UserInterface $admin;

  /**
   * Alice's project, Bob is a member.
   */
  private Project $apollo;

  /**
   * Carol's project.
   */
  private Project $zephyr;

  /**
   * A task of Apollo.
   */
  private Task $apolloTask;

  /**
   * A task of Zephyr.
   */
  private Task $zephyrTask;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('pm_project');
    $this->installEntitySchema('pm_task');
    $this->installSchema('user', ['users_data']);
    // User 1 is special: create it first so the test users are normal ones.
    $this->createUser();
    $this->alice = $this->createUser(self::WORKER, 'alice');
    $this->bob = $this->createUser(self::WORKER, 'bob');
    $this->carol = $this->createUser(self::WORKER, 'carol');
    $this->admin = $this->createUser(['administer project management'], 'admin');

    // Alice owns Apollo, Bob is a member of it. Carol owns Zephyr.
    $this->apollo = Project::create(['name' => 'Apollo', 'uid' => $this->alice->id(), 'members' => [$this->bob->id()]]);
    $this->apollo->save();
    $this->zephyr = Project::create(['name' => 'Zephyr', 'uid' => $this->carol->id()]);
    $this->zephyr->save();
    $this->apolloTask = Task::create(['name' => 'Launch', 'project' => $this->apollo->id(), 'uid' => $this->alice->id()]);
    $this->apolloTask->save();
    $this->zephyrTask = Task::create(['name' => 'Breeze', 'project' => $this->zephyr->id(), 'uid' => $this->carol->id()]);
    $this->zephyrTask->save();
  }

  /**
   * Ids an entity query with access check returns for the user.
   *
   * @return int[]
   *   Entity ids.
   */
  private function visible(string $entity_type_id, UserInterface $account): array {
    $this->setCurrentUser($account);
    $ids = $this->container->get('entity_type.manager')->getStorage($entity_type_id)->getQuery()->accessCheck(TRUE)->execute();
    return array_map('intval', array_values($ids));
  }

  /**
   * The owner is added as a member automatically.
   */
  public function testOwnerIsMember(): void {
    $members = array_map('intval', array_column($this->zephyr->get('members')->getValue(), 'target_id'));
    $this->assertSame([(int) $this->carol->id()], $members);
  }

  /**
   * Single records: members only, admin bypasses.
   */
  public function testEntityAccess(): void {
    $this->assertTrue($this->apollo->access('view', $this->alice));
    $this->assertTrue($this->apollo->access('view', $this->bob), 'A member sees the project.');
    $this->assertFalse($this->apollo->access('view', $this->carol), 'A non-member does not.');
    $this->assertTrue($this->apollo->access('view', $this->admin), 'Admin sees every project.');

    $this->assertTrue($this->apolloTask->access('view', $this->bob), 'A member sees the tasks.');
    $this->assertFalse($this->apolloTask->access('view', $this->carol));
    $this->assertFalse($this->apolloTask->access('update', $this->carol));
    $this->assertTrue($this->zephyrTask->access('view', $this->admin));

    // Membership does not replace permissions: Bob cannot edit Alice's project.
    $this->assertFalse($this->apollo->access('update', $this->bob));
    $this->assertTrue($this->apollo->access('update', $this->alice));
  }

  /**
   * Lists and counts (entity queries tagged ENTITY_TYPE_access).
   */
  public function testListsFollowMembership(): void {
    $this->assertSame([(int) $this->apollo->id()], $this->visible('pm_project', $this->bob));
    $this->assertSame([(int) $this->zephyr->id()], $this->visible('pm_project', $this->carol));
    $this->assertCount(2, $this->visible('pm_project', $this->admin));

    $this->assertSame([(int) $this->apolloTask->id()], $this->visible('pm_task', $this->bob));
    $this->assertSame([(int) $this->zephyrTask->id()], $this->visible('pm_task', $this->carol));
    $this->assertCount(2, $this->visible('pm_task', $this->admin));

    // Adding Carol to Apollo shows the project and its task to Carol.
    $this->apollo->get('members')->appendItem(['target_id' => $this->carol->id()]);
    $this->apollo->save();
    $this->assertCount(2, $this->visible('pm_project', $this->carol));
    $this->assertCount(2, $this->visible('pm_task', $this->carol));
  }

  /**
   * Revision queries (allRevisions()) follow membership too.
   */
  public function testRevisionQueriesFollowMembership(): void {
    $this->apolloTask->set('name', 'Launch v2')->save();
    $cases = [
      'pm_task' => $this->zephyrTask,
      'pm_project' => $this->zephyr,
    ];
    foreach ($cases as $type => $zephyr) {
      $ids = $this->container->get('entity_type.manager')->getStorage($type)->getQuery()
        ->allRevisions()
        ->accessCheck(TRUE)
        ->addMetaData('account', $this->carol)
        ->execute();
      $this->assertSame([(int) $zephyr->id()], array_values(array_unique(array_map('intval', $ids))), "$type revisions of other projects stay hidden");
    }
  }

  /**
   * A task cannot be put into a project the user is not in.
   */
  public function testTaskOnlyInOwnProjects(): void {
    $this->setCurrentUser($this->carol);
    $task = Task::create(['name' => 'Sneaky', 'project' => $this->apollo->id(), 'uid' => $this->carol->id()]);
    $paths = array_map(static fn ($v) => $v->getPropertyPath(), iterator_to_array($task->validate()));
    $this->assertContains('project.0.target_id', $paths, 'The project is not referenceable for a non-member.');

    $task->set('project', $this->zephyr->id());
    $this->assertCount(0, $task->validate());
  }

  /**
   * Every change of a project or task is kept as a revision.
   */
  public function testRevisions(): void {
    $this->assertRevisionable('pm_project');
    $this->assertRevisionable('pm_task');
    $project = Project::create(['name' => 'Hermes', 'uid' => $this->alice->id()]);
    $this->assertEditCreatesRevision($project, 'name', 'Hermes 2', $this->alice);
    $task = Task::create(['name' => 'Plan', 'project' => $project->id(), 'uid' => $this->alice->id()]);
    $this->assertEditCreatesRevision($task, 'name', 'Plan it', $this->bob);
  }

  /**
   * The admin views of projects and tasks.
   */
  public function testAdminViews(): void {
    $this->setCurrentUser($this->admin);
    $this->assertAdminView('drubricks_examples_project_management', 'pm_project');
    $this->assertAdminView('drubricks_examples_project_management', 'pm_task');
  }

  /**
   * Manage fields / form display / display, and the default displays.
   */
  public function testFieldUi(): void {
    $this->setCurrentUser($this->admin);
    $this->assertFieldUi('drubricks_examples_project_management', 'pm_project');
    $this->assertFieldUi('drubricks_examples_project_management', 'pm_task');
  }

}
