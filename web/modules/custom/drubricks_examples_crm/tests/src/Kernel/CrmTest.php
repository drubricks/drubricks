<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_examples_crm\Kernel;

use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\drubricks_examples_crm\Entity\Company;
use Drupal\drubricks_examples_crm\Entity\Contact;
use Drupal\drubricks_examples_crm\Entity\Deal;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\drubricks_core\Traits\AdminViewTestTrait;
use Drupal\Tests\drubricks_core\Traits\TableViewTestTrait;
use Drupal\Tests\drubricks_core\Traits\FieldUiTestTrait;
use Drupal\Tests\drubricks_core\Traits\RevisionsTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * CRM rules: closed deals, delete blockers, own / any access.
 *
 * A template for app modules: one kernel test per rule of the prompt.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class CrmTest extends KernelTestBase {

  use AdminViewTestTrait;
  use TableViewTestTrait;
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
    'link',
    'drubricks_core',
    'drubricks_examples_crm',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    foreach (['crm_company', 'crm_contact', 'crm_deal'] as $type) {
      $this->installEntitySchema($type);
    }
    $this->createUser();
    // References are validated against what the current user may see.
    $this->setUpCurrentUser([], ['administer crm']);
  }

  /**
   * A won deal needs a close date and a value; an open one does not.
   */
  public function testClosedDealRule(): void {
    $company = Company::create(['name' => 'Acme']);
    $company->save();
    $deal = Deal::create(['name' => 'Big one', 'company' => $company->id(), 'stage' => 'proposal']);
    $this->assertCount(0, $deal->validate());

    $deal->set('stage', 'won');
    $paths = array_map(static fn ($v) => $v->getPropertyPath(), iterator_to_array($deal->validate()));
    $this->assertContains('close_date', $paths);
    $this->assertContains('value', $paths);

    $deal->set('close_date', '2026-12-01')->set('value', 1000);
    $this->assertCount(0, $deal->validate());
  }

  /**
   * A deal's contact works at the deal's company (forms, boards, API alike).
   */
  public function testDealContactWorksAtCompany(): void {
    $acme = Company::create(['name' => 'Acme']);
    $acme->save();
    $globex = Company::create(['name' => 'Globex']);
    $globex->save();
    $ann = Contact::create(['name' => 'Ann', 'company' => $acme->id()]);
    $ann->save();
    $deal = Deal::create(['name' => 'Big one', 'company' => $acme->id(), 'contact' => $ann->id(), 'stage' => 'proposal']);
    $this->assertCount(0, $deal->validate());

    $deal->set('company', $globex->id());
    $violations = $deal->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('contact', $violations[0]->getPropertyPath());
    $this->assertSame('Ann does not work at Globex.', strip_tags((string) $violations[0]->getMessage()));

    // A deal without a contact is fine.
    $deal->set('contact', NULL);
    $this->assertCount(0, $deal->validate());
  }

  /**
   * A company with contacts or deals cannot be deleted.
   */
  public function testCompanyDeleteBlockers(): void {
    $company = Company::create(['name' => 'Acme']);
    $company->save();
    $admin = $this->createUser(['administer crm']);
    $access = $this->container->get('entity_type.manager')->getAccessControlHandler('crm_company');
    $this->assertTrue($company->access('delete', $admin));

    // A contact at the company: nobody may delete it, not even an admin.
    $contact = Contact::create(['name' => 'Ann', 'company' => $company->id()]);
    $contact->save();
    $access->resetCache();
    $result = $company->access('delete', $admin, TRUE);
    $this->assertTrue($result->isForbidden());
    $this->assertInstanceOf(AccessResultReasonInterface::class, $result);
    $this->assertSame('1 contacts belong to this company.', $result->getReason());
    $this->assertContains('crm_contact_list', CacheableMetadata::createFromObject($result)->getCacheTags(), 'the answer changes with the contacts');
    $this->assertTrue($company->access('update', $admin), 'other operations stay allowed');

    $contact->delete();
    $access->resetCache();
    $this->assertTrue($company->access('delete', $admin));
  }

  /**
   * Own / any: a user edits own contacts, sees others only with "view any".
   */
  public function testOwnAndAny(): void {
    $alice = $this->createUser(['view own crm_contact', 'edit own crm_contact']);
    $bob = $this->createUser(['view any crm_contact']);
    $contact = Contact::create(['name' => 'Ann', 'uid' => $alice->id()]);
    $contact->save();
    $this->assertTrue($contact->access('update', $alice));
    $this->assertTrue($contact->access('view', $bob));
    $this->assertFalse($contact->access('update', $bob));
  }

  /**
   * Every change of a company, contact or deal is kept as a revision.
   */
  public function testRevisions(): void {
    foreach (['crm_company', 'crm_contact', 'crm_deal'] as $type) {
      $this->assertRevisionable($type);
    }
    $company = Company::create(['name' => 'Acme']);
    $company->save();
    $editor = $this->createUser(['edit any crm_deal']);
    $deal = Deal::create(['name' => 'Big one', 'company' => $company->id(), 'stage' => 'proposal']);
    $this->assertEditCreatesRevision($deal, 'name', 'Bigger one', $editor);
    $this->assertEditCreatesRevision(Contact::create(['name' => 'Ann', 'company' => $company->id()]), 'name', 'Anna', $editor);
  }

  /**
   * The admin views of companies, contacts and deals.
   */
  public function testAdminViews(): void {
    $this->setUpCurrentUser([], ['administer crm']);
    $company = Company::create(['name' => 'Acme']);
    $company->save();
    Contact::create(['name' => 'Ann', 'company' => $company->id()])->save();
    Deal::create(['name' => 'Big one', 'company' => $company->id(), 'stage' => 'proposal'])->save();
    foreach (['crm_company', 'crm_contact', 'crm_deal'] as $type) {
      $this->assertAdminView('drubricks_examples_crm', $type);
    }
  }

  /**
   * The table view of deals: a core View with filters, bulk form, operations.
   */
  public function testTableView(): void {
    $this->setUpCurrentUser([], ['administer crm']);
    $company = Company::create(['name' => 'Acme']);
    $company->save();
    Deal::create(['name' => 'Big one', 'company' => $company->id(), 'stage' => 'proposal', 'value' => 5000])->save();
    $this->assertTableView('drubricks_examples_crm', 'crm_deal');
    // Base fields filter with a value: amounts, dates, references.
    $data = $this->container->get('views.views_data')->get('crm_deal');
    $this->assertSame('numeric', $data['value']['filter']['id']);
    $this->assertSame('datetime', $data['close_date']['filter']['id']);
    $this->assertSame('entity_reference', $data['company']['filter']['id']);
  }

  /**
   * The bulk form offers only the rows and actions the user may use.
   */
  public function testBulkFormFollowsAccess(): void {
    $this->installConfig(['drubricks_examples_crm']);
    $company = Company::create(['name' => 'Acme']);
    $company->save();
    $seller = $this->createUser(['view any crm_deal', 'edit own crm_deal', 'delete own crm_deal']);
    $other = $this->createUser(['view any crm_deal']);
    Deal::create(['name' => 'Mine', 'company' => $company->id(), 'stage' => 'lead', 'uid' => $seller->id()])->save();
    Deal::create(['name' => 'Theirs', 'company' => $company->id(), 'stage' => 'lead', 'uid' => $other->id()])->save();

    $this->setUpCurrentUser([], ['administer crm']);
    $admin = $this->bulkFormAsCurrentUser('crm_deal_table');
    $this->assertSame(2, $admin['rows'], 'admins select every row');
    $this->assertCount(3, $admin['actions']);

    $this->container->get('current_user')->setAccount($seller);
    $own = $this->bulkFormAsCurrentUser('crm_deal_table');
    $this->assertSame(1, $own['rows'], 'own records only');
    $this->assertTrue($own['column']);

    $this->container->get('current_user')->setAccount($other);
    $none = $this->bulkFormAsCurrentUser('crm_deal_table');
    $this->assertSame(0, $none['rows']);
    $this->assertFalse($none['column'], 'no bulk column for a read-only user');
    $this->assertSame([], $none['actions'], 'no action bar for a read-only user');
  }

  /**
   * Rows show related records only to who may see them; the cache knows them.
   */
  public function testRelatedRecords(): void {
    $company = Company::create(['name' => 'Acme', 'uid' => 1]);
    $company->save();
    $contact = Contact::create(['name' => 'Ann', 'job_title' => 'CEO', 'company' => $company->id()]);
    $contact->save();
    $screen = $this->container->get('entity_type.manager')->getHandler('crm_contact', 'drubricks_screen');

    $this->assertContains('crm_company:' . $company->id(), $screen->cacheability([$contact])->getCacheTags());
    $this->assertSame('CEO · Acme', $screen->row($contact)['subtitle']);

    // A user who may see contacts but not that company: no company name.
    $this->setUpCurrentUser([], ['view any crm_contact']);
    $this->container->get('entity_type.manager')->getAccessControlHandler('crm_company')->resetCache();
    $this->assertSame('CEO', $screen->row($contact)['subtitle']);
  }

  /**
   * A save without a new revision keeps the revision's log message.
   */
  public function testRevisionLogKept(): void {
    $company = Company::create(['name' => 'Acme']);
    $company->setRevisionLogMessage('Imported from the old CRM');
    $company->save();
    // A synced save (e.g. an import) writes into the same revision.
    $company->set('city', 'Lisbon')->setRevisionLogMessage('');
    $company->setSyncing(TRUE)->save();
    $storage = $this->container->get('entity_type.manager')->getStorage('crm_company');
    $reloaded = $storage->loadUnchanged($company->id());
    $this->assertInstanceOf(Company::class, $reloaded);
    $this->assertSame('Lisbon', $reloaded->get('city')->value);
    $this->assertSame($company->getRevisionId(), $reloaded->getRevisionId(), 'no new revision');
    $this->assertSame('Imported from the old CRM', $reloaded->getRevisionLogMessage());
  }

  /**
   * Manage fields / form display / display, and the default displays.
   */
  public function testFieldUi(): void {
    $this->setUpCurrentUser([], ['administer crm']);
    $this->assertFieldUi('drubricks_examples_crm', 'crm_company');
    $this->assertFieldUi('drubricks_examples_crm', 'crm_contact');
    $this->assertFieldUi('drubricks_examples_crm', 'crm_deal');
  }

}
