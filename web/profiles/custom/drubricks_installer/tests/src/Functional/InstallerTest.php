<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_installer\Functional;

use Drupal\FunctionalTests\Installer\InstallerTestBase;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The browser installer: base recipe, "Choose applications", profile removed.
 */
#[Group('drubricks')]
#[Group('#slow')]
#[RunTestsInSeparateProcesses]
final class InstallerTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'drubricks_installer';

  /**
   * {@inheritdoc}
   *
   * The test base sets this as the default theme after the installation; the
   * base recipe already made drubricks the default.
   */
  protected $defaultTheme = 'drubricks';

  /**
   * {@inheritdoc}
   */
  protected function setUpLanguage(): void {
    // A distribution: its name, no "Choose profile" step.
    $this->assertSession()->pageTextContains('Drubricks');
    $this->assertSession()->pageTextNotContains('Select an installation profile');
    parent::setUpLanguage();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpSite(): void {
    // Configure site (like the parent, but the installation goes on: the base
    // recipe batch runs and the profile's own step follows).
    $edit = $this->translatePostValues($this->parameters['forms']['install_configure_form']);
    $this->submitForm($edit, $this->translations['Save and continue']);
    // The profile's own step: every App recipe is offered.
    $this->assertSession()->pageTextContains('Choose applications');
    $this->assertSession()->fieldExists('apps[drubricks_examples_crm]');
    $this->assertSession()->fieldExists('apps[drubricks_examples_project_management]');
    $this->assertSession()->fieldExists('apps[drubricks_examples_booking]');
    $this->submitForm(['apps[drubricks_examples_crm]' => TRUE], 'Save and continue');
    $this->isInstalled = TRUE;
  }

  /**
   * The site is installed with the chosen app, without the profile.
   */
  public function testInstalled(): void {
    // distribution.install.finish_url, logged in as the site's admin.
    $this->assertSession()->addressEquals('apps');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Contacts');

    // keep_profile: false — no profile once the installer has finished.
    $this->assertFalse(\Drupal::installProfile());
    $this->assertNull($this->config('core.extension')->get('profile'));
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('drubricks_installer'));

    // The chosen app with its demo data; the others not installed.
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('drubricks_examples_crm'));
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('drubricks_examples_booking'));
    $this->assertSame(10, (int) \Drupal::entityQuery('crm_contact')->accessCheck(FALSE)->count()->execute());
    $this->assertSame('drubricks', $this->config('system.theme')->get('default'));
    // The sidebar menu (Menu UI) with the app's links.
    $this->assertNotNull(Menu::load('drubricks'));
    $this->drupalGet('admin/structure/menu/manage/drubricks');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Contacts');
  }

}
