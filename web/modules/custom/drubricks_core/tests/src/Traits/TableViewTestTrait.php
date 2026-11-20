<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Traits;

use Drupal\drubricks_core\Screen\ScreenViews;
use Drupal\views\Plugin\views\field\BulkForm;
use Drupal\views\Plugin\views\field\EntityOperations;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\filter\Standard;
use Drupal\views\Views;

/**
 * Checks of an app's table view (ScreenHandler::table()), for kernel tests.
 *
 * @code
 * $this->assertTableView('drubricks_examples_crm', 'crm_deal');
 * @endcode
 * Installing the module's config validates the View against the config
 * schema. Call it with a current user who may see the records and at least
 * one record saved.
 */
trait TableViewTestTrait {

  /**
   * The table route, the view switch and the View: rows, filters, bulk form.
   */
  protected function assertTableView(string $module, string $entity_type_id): void {
    $this->installConfig([$module]);
    $this->container->get('router.builder')->rebuild();
    $type_manager = $this->container->get('entity_type.manager');
    $type = $type_manager->getDefinition($entity_type_id);
    $handler = $type_manager->getHandler($entity_type_id, 'drubricks_screen');
    $table = $handler->table();
    $this->assertNotNull($table, "$entity_type_id declares a table view");

    $route = $this->container->get('router.route_provider')->getRouteByName("entity.$entity_type_id.drubricks_table");
    $this->assertSame($type->getLinkTemplate('drubricks-table'), $route->getPath());
    $this->assertArrayHasKey('table', ScreenViews::available($type, $handler), 'the table in the view switch');

    $view = Views::getView($table['view']);
    $this->assertNotNull($view, "views.view.{$table['view']}");
    $display = $table['display'] ?? 'default';
    $view->setDisplay($display);
    $this->assertTrue($view->access($display));
    $view->execute();
    $this->assertNotEmpty($view->result, 'the View lists the records');

    $fields = $view->display_handler->getHandlers('field');
    $this->assertNotEmpty(array_filter($fields, static fn ($f) => $f instanceof BulkForm), 'a bulk form column');
    $this->assertNotEmpty(array_filter($fields, static fn ($f) => $f instanceof EntityOperations), 'an operations column');
    $exposed = array_filter($view->display_handler->getHandlers('filter'), static fn ($f) => $f instanceof FilterPluginBase && $f->isExposed());
    $this->assertNotEmpty($exposed, 'exposed filters');
    /** @var array<string, \Drupal\views\Plugin\views\filter\FilterPluginBase> $exposed */
    foreach ($exposed as $id => $filter) {
      // "standard" has no value to filter by (and a filter identified as
      // "value" is lost in the exposed form).
      $this->assertNotInstanceOf(Standard::class, $filter, "filter $id has a value form");
      $this->assertNotSame('value', $filter->options['expose']['identifier'], "filter $id: identifier 'value' clashes with the form");
    }
  }

  /**
   * Rows with a bulk checkbox and the offered actions, as the current user.
   *
   * @return array{rows: int, actions: string[], column: bool}
   *   Checkboxes rendered, action labels, whether the column is shown.
   */
  protected function bulkFormAsCurrentUser(string $view_id, string $display = 'default'): array {
    $view = Views::getView($view_id);
    $view->setDisplay($display);
    $build = $view->preview();
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    preg_match_all('/<option value="[^"]+"[^>]*>([^<]+)<\/option>/', (string) (preg_match('/<select[^>]*name="action"[^>]*>(.*?)<\/select>/s', $html, $m) ? $m[1] : ''), $options);
    return [
      'rows' => preg_match_all('/name="[a-z_]+_bulk_form\[\d+\]"/', $html),
      'actions' => $options[1],
      'column' => str_contains($html, 'select-all'),
    ];
  }

}
