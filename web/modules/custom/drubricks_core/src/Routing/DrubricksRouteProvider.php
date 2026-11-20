<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\EntityRouteProviderInterface;
use Drupal\drubricks_core\Controller\BoardController;
use Drupal\drubricks_core\Controller\CalendarController;
use Drupal\drubricks_core\Controller\ScreenController;
use Drupal\drubricks_core\Controller\TableController;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Routes of a "list + details" screen, from the entity link templates.
 *
 * Link templates used: collection, canonical, add-form, edit-form,
 * delete-form, drubricks-inspector. Every route renders the full screen, so
 * each URL opened directly shows the same screen HTMX would build.
 *
 * With 'delete-multiple-form' (under /admin/app/…) it also adds the
 * confirmation of the "Delete" bulk operation of the admin views (core's
 * delete action redirects there; core's DeleteMultipleForm). Records that must
 * stay never get there: their "delete" access is forbidden, so the bulk form
 * skips them.
 */
class DrubricksRouteProvider implements EntityRouteProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type): RouteCollection {
    $collection = new RouteCollection();
    $id = $entity_type->id();
    $param = [$id => ['type' => 'entity:' . $id]];

    $routes = [
      'collection' => ['collection', ScreenController::class . '::collection', "drubricks_screen_list:$id"],
      'canonical' => ['canonical', ScreenController::class . '::canonical', "$id.view"],
      'add_form' => ['add-form', ScreenController::class . '::add', "$id.create"],
      'edit_form' => ['edit-form', ScreenController::class . '::edit', "$id.update"],
      'delete_form' => ['delete-form', ScreenController::class . '::delete', "$id.delete"],
      'drubricks_inspector' => ['drubricks-inspector', ScreenController::class . '::inspector', "$id.view"],
      'drubricks_board' => ['drubricks-board', BoardController::class . '::board', ''],
      'drubricks_calendar' => ['drubricks-calendar', CalendarController::class . '::calendar', ''],
      'drubricks_table' => ['drubricks-table', TableController::class . '::table', ''],
    ];
    foreach ($routes as $name => [$template, $controller, $access]) {
      if (!$entity_type->hasLinkTemplate($template)) {
        continue;
      }
      $route = new Route($entity_type->getLinkTemplate($template));
      $route->setDefault('_controller', $controller);
      $route->setDefault('entity_type_id', $id);
      $route->setDefault('_title_callback', ScreenController::class . '::' . match ($name) {
        'collection', 'drubricks_board', 'drubricks_calendar', 'drubricks_table' => 'collectionTitle',
        'add_form' => 'addTitle',
        'edit_form' => 'editTitle',
        'delete_form' => 'deleteTitle',
        default => 'title',
      });
      $route->setOption('_drubricks_screen', TRUE);
      if (in_array($name, ['collection', 'drubricks_board', 'drubricks_calendar', 'drubricks_table'], TRUE)) {
        // Any of: admin, view any, view own, create (core's "a+b" syntax).
        $route->setRequirement('_permission', implode('+', array_filter([
          $entity_type->getAdminPermission(),
          "view any $id",
          "view own $id",
          "create $id",
        ])));
      }
      elseif ($name === 'add_form') {
        $route->setRequirement('_entity_create_access', $id);
      }
      else {
        $route->setRequirement('_entity_access', $access);
        $route->setOption('parameters', $param);
        $route->setRequirement($id, '\d+');
      }
      $collection->add("entity.$id.$name", $route);
    }
    if ($entity_type->hasLinkTemplate('delete-multiple-form')) {
      $route = new Route($entity_type->getLinkTemplate('delete-multiple-form'));
      $route->setDefault('_form', $entity_type->getFormClass('delete-multiple-confirm') ?: DeleteMultipleForm::class);
      $route->setDefault('entity_type_id', $id);
      $route->setRequirement('_entity_delete_multiple_access', $id);
      $route->setOption('_admin_route', TRUE);
      $collection->add("entity.$id.delete_multiple_form", $route);
    }
    return $collection;
  }

}
