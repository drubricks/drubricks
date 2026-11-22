<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Traits;

use Drupal\drubricks_core\Screen\ScreenViews;
use Drupal\views\Views;

/**
 * Checks of charts built with Views (contrib Charts, Chart.js), for kernel tests.
 *
 * @code
 * $this->assertChartView('drubricks_examples_crm', 'crm_deal');
 * $chart = $this->renderChartView('crm_deal_won_by_month', 'block_1');
 * @endcode
 * The test installs charts and charts_chartjs with Chart.js as the default
 * library (setUpCharts()); installing the module's config validates its Views
 * against the config schema.
 */
trait ChartViewTestTrait {

  /**
   * Charts with Chart.js as the site's library, like the base recipe.
   */
  protected function setUpCharts(): void {
    $this->installConfig(['charts']);
    $this->config('charts.settings')
      ->set('charts_default_settings.library', 'chartjs')
      ->set('advanced.requirements.cdn', FALSE)
      ->save();
  }

  /**
   * The chart view of an entity type (ScreenHandler::chart()): route, switch.
   */
  protected function assertChartView(string $module, string $entity_type_id): void {
    $this->installConfig([$module]);
    $this->container->get('router.builder')->rebuild();
    $type_manager = $this->container->get('entity_type.manager');
    $type = $type_manager->getDefinition($entity_type_id);
    $handler = $type_manager->getHandler($entity_type_id, 'drubricks_screen');
    $chart = $handler->chart();
    $this->assertNotNull($chart, "$entity_type_id declares a chart view");

    $route = $this->container->get('router.route_provider')->getRouteByName("entity.$entity_type_id.drubricks_chart");
    $this->assertSame($type->getLinkTemplate('drubricks-chart'), $route->getPath());
    $this->assertArrayHasKey('chart', ScreenViews::available($type, $handler), 'the chart in the view switch');

    $view = Views::getView($chart['view']);
    $this->assertNotNull($view, "views.view.{$chart['view']}");
    $this->assertSame('chart', $view->storage->getDisplay($chart['display'] ?? 'default')['display_options']['style']['type'] ?? NULL, 'a View with the Chart format');
  }

  /**
   * Renders a chart View as the current user: its Chart.js definition.
   *
   * @return array{definition: array, html: string}
   *   The decoded data-chart attribute (empty without results) and the markup.
   */
  protected function renderChartView(string $view_id, string $display = 'default', array $input = []): array {
    $view = Views::getView($view_id);
    $this->assertNotNull($view, "views.view.$view_id");
    $view->setDisplay($display);
    if ($input) {
      $view->setExposedInput($input);
    }
    // The chart the "Chart" format builds from the results (the View's own
    // wrapper template is the theme's).
    $view->execute();
    $build = $view->style_plugin->render();
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $definition = preg_match('/data-chart="([^"]*)"/', $html, $match)
      ? (json_decode(html_entity_decode($match[1], ENT_QUOTES), TRUE) ?: [])
      : [];
    return ['definition' => $definition, 'html' => $html];
  }

}
