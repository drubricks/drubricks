<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\drubricks_core\Htmx\Swap;
use Drupal\drubricks_core\Screen\RecordPanelTrait;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_core\Screen\ScreenViews;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Table and chart views of any Drubricks entity type: a core View in the app.
 *
 * The screen handler names the View (ScreenHandler::table() / chart()); this
 * controller puts it in a details pane with the shared view header (title,
 * record count, view switch, "add" button, the View's exposed filters in the
 * toolbar row), and a record opened with ?open=ID in the right-side panel
 * (the table stays). Columns, exposed filters, sorting, the pager, the bulk
 * form and the operations are the View's own, edited in the Views UI.
 */
final class TableController extends ControllerBase {

  use RecordPanelTrait;

  /**
   * Id of the table region, swapped by the filters, the pager and sorting.
   */
  public const REGION = 'drubricks-table';

  /**
   * The table page.
   */
  public function table(Request $request, string $entity_type_id): array {
    return $this->viewPage($request, $entity_type_id, 'table');
  }

  /**
   * The chart page (a View with the Chart style of the contrib "charts").
   */
  public function chart(Request $request, string $entity_type_id): array {
    return $this->viewPage($request, $entity_type_id, 'chart');
  }

  /**
   * A View of the entity type (table or chart) in the app shell.
   */
  private function viewPage(Request $request, string $entity_type_id, string $kind): array {
    $type = $this->entityTypeManager()->getDefinition($entity_type_id);
    $handler = $this->entityTypeManager()->getHandler($entity_type_id, 'drubricks_screen');
    if (!$handler instanceof ScreenHandler || !($table = $handler->{$kind}())) {
      throw new NotFoundHttpException();
    }
    $view = Views::getView($table['view']);
    $display = $table['display'] ?? 'default';
    if (!$view || !$view->setDisplay($display)) {
      throw new NotFoundHttpException();
    }
    if (!$view->access($display)) {
      throw new AccessDeniedHttpException();
    }

    // The exposed filters go to the header's toolbar row, like the search and
    // filters of the other views: core's "exposed form in a block" (the View
    // itself then leaves the form out), built from the same View object.
    $view->display_handler->setOption('exposed_block', TRUE);
    $filters = $view->display_handler->viewExposedFormBlocks() ?: NULL;

    // Filtering, paging and sorting reload only the table (HTMX), the URL
    // keeps the state; the bulk form posts as core's form.
    $region = ['#attributes' => ['class' => $kind === 'chart' ? ['drubricks-chart-view'] : []]];
    Swap::region($region, self::REGION);
    if ($filters) {
      Swap::filters($filters, self::REGION, Url::fromRoute('<current>'));
    }

    return [
      '#theme' => 'drubricks_table',
      '#inspector' => $this->recordPanel($request, $entity_type_id, $handler),
      '#title' => $handler->title(),
      // The records the user may see, like "N items" of the list and board;
      // the View's filters narrow the table below it.
      '#subtitle' => ScreenViews::count($handler->count($request)),
      '#views' => ScreenViews::links($type, $handler, $kind),
      '#add' => ScreenViews::add($type, $handler),
      '#filters' => $filters,
      '#table_attributes' => $region['#attributes'],
      '#table' => $view->buildRenderable($display, [], FALSE),
      '#cache' => ['contexts' => ['user.permissions'], 'tags' => $type->getListCacheTags()],
    ];
  }

}
