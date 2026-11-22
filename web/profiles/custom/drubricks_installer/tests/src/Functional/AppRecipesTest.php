<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_installer\Functional;

use Drupal\Core\Recipe\Recipe;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Every App recipe (with its demo data) applies to a fresh site and works.
 *
 * The same as the installer's "Choose applications" with all apps chosen, or
 * `drush recipe ../recipes/<app>_demo` on an existing site.
 */
#[Group('drubricks')]
#[Group('#slow')]
#[RunTestsInSeparateProcesses]
final class AppRecipesTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Records each demo recipe brings, by entity type.
   */
  private const DEMO_RECORDS = [
    'crm_contact' => 10,
    'crm_deal' => 27,
    'pm_task' => 18,
    'booking' => 31,
  ];

  /**
   * Applies every App recipe and checks the apps.
   */
  public function testAppRecipes(): void {
    $dir = dirname(DRUPAL_ROOT) . '/recipes';
    $apps = [];
    foreach (glob($dir . '/*/recipe.yml') ?: [] as $file) {
      $recipe = Recipe::createFromDirectory(dirname($file));
      if ($recipe->type === 'App') {
        $apps[basename(dirname($file))] = $recipe;
      }
    }
    $this->assertArrayHasKey('drubricks_examples_crm', $apps);

    foreach (array_keys($apps) as $id) {
      // The Demo recipe includes the App recipe (and the base recipe).
      $this->applyRecipe(is_dir("$dir/{$id}_demo") ? "$dir/{$id}_demo" : "$dir/$id");
    }

    foreach (array_keys($apps) as $id) {
      $this->assertTrue(\Drupal::moduleHandler()->moduleExists($id), "$id installed");
    }
    foreach (self::DEMO_RECORDS as $type => $count) {
      $this->assertSame($count, (int) \Drupal::entityQuery($type)->accessCheck(FALSE)->count()->execute(), "$type demo records");
    }
    // Config entities of the modules came with the App recipes.
    $views = [
      'crm_contact_admin', 'pm_task_admin', 'booking_admin', 'user_admin_people',
      'crm_deal_chart', 'crm_deal_won_by_month', 'crm_deal_forecast', 'pm_task_open_by_assignee', 'booking_this_week',
    ];
    foreach ($views as $view) {
      $this->assertNotNull(View::load($view), "views.view.$view");
    }
    $this->assertSame('drubricks', $this->config('system.theme')->get('default'));
    // Charts from the base recipe: Chart.js, from the site's own copy.
    $this->assertSame('chartjs', $this->config('charts.settings')->get('charts_default_settings.library'));
    $this->assertFalse($this->config('charts.settings')->get('advanced.requirements.cdn'));

    // The apps answer for an administrator.
    $this->drupalLogin($this->drupalCreateUser(admin: TRUE));
    $paths = [
      '/apps',
      '/apps/crm/contacts',
      '/apps/crm/deals',
      '/apps/crm/deals/chart',
      '/apps/pm/board',
      '/apps/booking',
      '/apps/design-system',
      '/admin/app',
      '/admin/app/entities/crm/contacts',
    ];
    foreach ($paths as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(200);
    }
  }

}
