<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\drubricks_core\Traits\ChartViewTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Charts (contrib, Chart.js) in the kit look (ChartHooks).
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class ChartsTest extends KernelTestBase {

  use ChartViewTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'inline_form_errors',
    'options',
    'charts',
    'charts_chartjs',
    'drubricks_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpCharts();
  }

  /**
   * A chart element prints a Chart.js chart with kit colours and a data table.
   */
  public function testChartElement(): void {
    // Charts' preprocess, wired the Drupal 12 way: the chart is printed.
    $registry = $this->container->get('theme.registry')->get();
    $this->assertSame('Drupal\charts\Hook\ChartsHooks:templatePreprocessChartsChart', $registry['charts_chart']['initial preprocess'] ?? NULL);
    // Views' own template, not the missing one Charts' display plugin names.
    $view = $registry['views_view'];
    $this->assertFileExists(DRUPAL_ROOT . '/' . $view['path'] . '/' . $view['template'] . '.html.twig');
    $this->assertSame('Drupal\views\Hook\ViewsThemeHooks:preprocessViewsView', $view['initial preprocess'] ?? NULL);

    $defaults = (array) $this->config('charts.settings')->get('charts_default_settings.display.colors');
    $build = [
      '#type' => 'chart',
      '#chart_type' => 'column',
      '#title' => 'Revenue',
      '#height' => 240,
      '#height_units' => 'px',
      // Charts' own default colours (second of the palette), a kit token, a
      // colour picked by hand, none.
      'a' => ['#type' => 'chart_data', '#title' => 'A', '#data' => [1, 2], '#color' => $defaults[1]],
      'b' => ['#type' => 'chart_data', '#title' => 'B', '#data' => [2, 1], '#color' => 'purple'],
      'c' => ['#type' => 'chart_data', '#title' => 'C', '#data' => [3, 3], '#color' => '#123456'],
      'd' => ['#type' => 'chart_data', '#title' => 'D', '#data' => [1, 1]],
      'xaxis' => ['#type' => 'chart_xaxis', '#labels' => ['Jan', 'Feb']],
    ];
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertMatchesRegularExpression('/data-chart="[^"]+"/', $html, 'the chart is printed');
    preg_match('/data-chart="([^"]*)"/', $html, $match);
    $definition = json_decode(html_entity_decode($match[1], ENT_QUOTES), TRUE);

    $colours = array_column($definition['data']['datasets'], 'backgroundColor');
    $this->assertSame(['teal', 'purple', '#123456', 'purple'], $colours, "Charts' defaults → kit tokens, own colours kept, the next kit colour for none");
    $this->assertArrayNotHasKey('color', $definition['options']['plugins']['title'], 'no fixed title colour');
    foreach ($definition['options']['scales'] as $axis => $scale) {
      $this->assertArrayNotHasKey('color', $scale['ticks'] ?? [], "no fixed tick colour on $axis");
    }
    $this->assertFalse($definition['options']['maintainAspectRatio'], 'a set height is the chart height');

    $this->assertStringContainsString('visually-hidden', $html, 'a data table for screen readers');
    $this->assertStringContainsString('<th scope="col">', $html);
    $attached = $build['#attached']['library'] ?? [];
    $this->assertContains('drubricks_core/charts', $attached, 'the kit look (tokens, light / dark)');
  }

}
