<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Traits;

use Drupal\system\Entity\Action;
use Drupal\views\Views;

/**
 * Checks of an app's admin view (config/install of the module), for kernel tests.
 *
 * @code
 * $this->assertAdminView('drubricks_examples_crm', 'crm_contact');
 * @endcode
 * Installing the module's config validates the view and the actions against
 * the config schema (kernel tests are strict about it).
 */
trait AdminViewTestTrait {

  /**
   * The view lists the records; bulk actions and their confirmation exist.
   *
   * Call it with a user holding the admin permission as the current user and
   * at least one record saved.
   */
  protected function assertAdminView(string $module, string $entity_type_id): void {
    $this->installConfig([$module]);
    // The view's page route.
    $this->container->get('router.builder')->rebuild();
    foreach (['delete', 'publish', 'unpublish'] as $op) {
      $this->assertNotNull(Action::load("{$entity_type_id}_{$op}_action"), "$op action of $entity_type_id");
    }
    $view = Views::getView("{$entity_type_id}_admin");
    $this->assertNotNull($view, "views.view.{$entity_type_id}_admin");
    $this->assertTrue($view->access('page_1'), 'admins open the view');
    $view->setDisplay('page_1');
    $view->execute();
    $this->assertNotEmpty($view->result, 'the view lists the records');
    $this->assertStringStartsWith('/admin/app/entities/', $view->getUrl()->toString());

    $route = $this->container->get('router.route_provider')->getRouteByName("entity.$entity_type_id.delete_multiple_form");
    $this->assertStringStartsWith('/admin/app/entities/', $route->getPath());
  }

}
