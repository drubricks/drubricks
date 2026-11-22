<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\charts\Hook\ChartsHooks;
use Drupal\charts\Plugin\chart\Library\ChartBase;
use Drupal\views\Hook\ViewsThemeHooks;

/**
 * Charts (contrib "charts" with its Chart.js library) in the UI kit look.
 *
 * Charts and Views keep building the charts; this only:
 * - turns Charts' default colours (its palette, by position) into kit colour
 *   tokens ("blue", "teal" …) — a colour picked in the chart settings stays;
 *   code may give a series a token name as its #color;
 * - drops the fixed black / grey of titles, ticks and grid lines and the
 *   fixed font, so the kit's colours and font apply (drubricks_core/charts
 *   sets them from the page's CSS, in light and dark mode);
 * - adds the visually hidden data table of Charts (screen readers) when a
 *   chart has none.
 * The hooks run only where charts is installed.
 */
final class ChartHooks {

  /**
   * Kit tokens for Charts' default colours, in the order of its palette.
   */
  public const PALETTE = ['blue', 'teal', 'orange', 'purple', 'green', 'pink', 'indigo', 'yellow', 'red', 'gray'];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleExtensionList $moduleList,
  ) {}

  /**
   * Implements hook_theme_registry_alter().
   *
   * Charts 5.2 registers the preprocess of its "charts_chart" template as
   * #[Hook('preprocess_charts_chart', module: 'template')], which Drupal 12 no
   * longer runs (template preprocess functions became the theme hook's
   * "initial preprocess"): the chart printed nothing. Wire that same method
   * the Drupal 12 way; drop this once Charts declares it itself.
   *
   * Its "Chart attachment" display plugin also names "views_view" as its
   * theme, so Views registers views_view a second time, from the charts
   * folder (no such template) and without core's preprocess; whichever
   * display plugin comes last wins. Where no theme overrides the template,
   * keep core's registration.
   */
  #[Hook('theme_registry_alter')]
  public function themeRegistryAlter(array &$theme_registry): void {
    if (isset($theme_registry['charts_chart']) && empty($theme_registry['charts_chart']['initial preprocess'])) {
      $theme_registry['charts_chart']['initial preprocess'] = ChartsHooks::class . ':templatePreprocessChartsChart';
    }
    $view = $theme_registry['views_view'] ?? NULL;
    if ($view && isset($view['path'], $view['template']) && !is_file(DRUPAL_ROOT . '/' . $view['path'] . '/' . $view['template'] . '.html.twig')) {
      $views = $this->moduleList->getPath('views');
      $theme_registry['views_view']['path'] = $views . '/templates';
      $theme_registry['views_view']['theme path'] = $views;
      $theme_registry['views_view']['initial preprocess'] = ViewsThemeHooks::class . ':preprocessViewsView';
    }
  }

  /**
   * Implements hook_chart_alter().
   */
  #[Hook('chart_alter')]
  public function chartAlter(array &$element, ?string $chart_id): void {
    if ($this->library($element) !== 'chartjs') {
      return;
    }
    $element['#attached']['library'][] = 'drubricks_core/charts';
    // A data table for screen readers (the canvas is aria-hidden), unless the
    // chart shows one itself.
    if (($element['#accessible_table'] ?? 'disabled') === 'disabled') {
      $element['#accessible_table'] = 'invisible';
    }
  }

  /**
   * Implements hook_chart_definition_alter().
   */
  #[Hook('chart_definition_alter')]
  public function chartDefinitionAlter(array &$definition, array $element, ?string $chart_id): void {
    if ($this->library($element) !== 'chartjs') {
      return;
    }
    // Colours: Charts' default palette → kit tokens (resolved by the browser).
    foreach ($definition['data']['datasets'] ?? [] as $index => $dataset) {
      foreach (['backgroundColor', 'borderColor'] as $key) {
        if (!array_key_exists($key, $dataset) || $dataset[$key] === '' || $dataset[$key] === NULL) {
          // No colour: the next kit colour, like Charts' own palette would.
          $definition['data']['datasets'][$index][$key] = self::PALETTE[$index % count(self::PALETTE)];
          continue;
        }
        $definition['data']['datasets'][$index][$key] = is_array($dataset[$key])
          ? array_map($this->token(...), $dataset[$key])
          : $this->token($dataset[$key]);
      }
    }
    // Pie / doughnut slices are separated by the card's background.
    if (in_array($definition['type'] ?? '', ['pie', 'doughnut', 'polarArea'], TRUE)) {
      foreach (array_keys($definition['data']['datasets'] ?? []) as $index) {
        $definition['data']['datasets'][$index]['borderColor'] = 'var(--bg-raised)';
      }
    }
    // Counts (whole numbers only): no 0.5 steps on the value axis.
    $values = array_merge(...array_map(static fn ($dataset) => array_filter((array) ($dataset['data'] ?? []), 'is_numeric'), $definition['data']['datasets'] ?? [[]]));
    if ($values && $values === array_filter($values, static fn ($value) => floor((float) $value) == $value)) {
      foreach (array_keys($definition['options']['scales'] ?? []) as $axis) {
        $definition['options']['scales'][$axis]['ticks']['precision'] = 0;
      }
    }
    // A chart with a set height (chart settings → dimensions) fills it.
    if (!empty($element['#height'])) {
      $definition['options']['maintainAspectRatio'] = FALSE;
    }
    // Fixed colours and fonts of Charts' element defaults → the kit's.
    unset($definition['options']['plugins']['title']['color'], $definition['options']['plugins']['title']['font']['size']);
    foreach (array_keys($definition['options']['scales'] ?? []) as $axis) {
      unset(
        $definition['options']['scales'][$axis]['ticks']['color'],
        $definition['options']['scales'][$axis]['title']['color'],
        $definition['options']['scales'][$axis]['grid']['color'],
        $definition['options']['scales'][$axis]['grid']['borderColor'],
      );
    }
  }

  /**
   * A kit token for one of Charts' default colours, else the colour itself.
   *
   * Charts has two default palettes: the site's (charts.settings, used by
   * Views and the chart settings forms) and its element's (code). A colour
   * at position N of either becomes the Nth kit colour.
   */
  public function token(mixed $color): mixed {
    if (!is_string($color) || !str_starts_with($color, '#')) {
      return $color;
    }
    $palettes = [
      (array) $this->configFactory->get('charts.settings')->get('charts_default_settings.display.colors'),
      ChartBase::getDefaultColors(),
    ];
    foreach ($palettes as $palette) {
      $position = array_search(strtolower($color), array_map('strtolower', $palette), TRUE);
      if ($position !== FALSE) {
        return self::PALETTE[$position % count(self::PALETTE)];
      }
    }
    return $color;
  }

  /**
   * The charting library of a chart element (the site default when unset).
   */
  private function library(array $element): string {
    $library = (string) ($element['#chart_library'] ?? '');
    if ($library === '' || $library === 'site_default') {
      $library = (string) $this->configFactory->get('charts.settings')->get('charts_default_settings.library');
    }
    return $library;
  }

}
