<?php

declare(strict_types=1);

namespace Drupal\drubricks\Hook;

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Preprocess hooks of the drubricks theme.
 */
final class ThemeHooks {

  use StringTranslationTrait;

  /**
   * The kit's palettes (kit/ui.css, managed by the kit's JS).
   */
  private const KIT_PALETTES = ['cobalt', 'lagoon', 'sage', 'berry', 'indigo'];

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly ThemeSettingsProvider $themeSettings,
    private readonly PagerManagerInterface $pagerManager,
  ) {}

  /**
   * App screens render their own panes; other pages get a details pane.
   *
   * App screens are routes with the _drubricks_screen option; every other
   * page shows its title and tabs in the details pane.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $route = $this->routeMatch->getRouteObject();
    $variables['drubricks_screen'] = (bool) $route?->getOption('_drubricks_screen');
    $variables['#cache']['contexts'][] = 'route';
  }

  /**
   * Kit palette before the first paint.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $variables['attributes']['class'][] = 'drubricks';
    // Kit palette of the site (theme.js reads it before the first paint). Theme
    // setting "default_palette"; users choose only light / dark / auto.
    $palette = $this->themeSettings->getSetting('default_palette') ?: 'cobalt';
    if (in_array($palette, self::KIT_PALETTES, TRUE)) {
      $variables['html_attributes']['data-default-palette'] = $palette;
    }
    else {
      // A palette of this theme (css/palettes.css): the kit's JS knows only
      // its own palettes and resets data-palette to its default (lagoon), so
      // the theme's palette has its own attribute, rendered on the server.
      $variables['html_attributes']['data-default-palette'] = 'lagoon';
      $variables['html_attributes']['data-theme-palette'] = $palette;
    }
  }

  /**
   * A local task is a kit tab: the link keeps its options and cache metadata.
   */
  #[Hook('preprocess_menu_local_task')]
  public function preprocessMenuLocalTask(array &$variables): void {
    $variables['link']['#options']['attributes']['class'][] = 'tab';
    if (!empty($variables['is_active'])) {
      $variables['link']['#options']['attributes']['class'][] = 'active';
      $variables['link']['#options']['attributes']['aria-current'] = 'page';
    }
  }

  /**
   * Kit alert type and icon of each message type.
   */
  #[Hook('preprocess_status_messages')]
  public function preprocessStatusMessages(array &$variables): void {
    $variables['alert_types'] = [
      'status' => ['class' => 'success', 'icon' => 'checkmark-circle'],
      'warning' => ['class' => 'warning', 'icon' => 'alert'],
      'error' => ['class' => 'danger', 'icon' => 'alert'],
    ];
  }

  /**
   * Props of the kit pager component (drubricks:pager).
   *
   * 1-based current page, total and the links of the pages the component
   * shows (first, last, current ± 1), built like core's PagerPreprocess: the
   * pager's route and parameters, the other pagers' pages (PagerManager).
   */
  #[Hook('preprocess_pager')]
  public function preprocessPager(array &$variables): void {
    $element = $variables['pager']['#element'] ?? 0;
    $pager = $this->pagerManager->getPager($element);
    if (!$pager) {
      $variables['drubricks_pager'] = ['total' => 0, 'current' => 1];
      return;
    }
    $current = $pager->getCurrentPage() + 1;
    $total = $pager->getTotalPages();
    $links = [];
    foreach (array_unique([1, $current - 1, $current, $current + 1, $total]) as $page) {
      if ($page < 1 || $page > $total) {
        continue;
      }
      $query = $this->pagerManager->getUpdatedParameters($variables['pager']['#parameters'] ?? [], $element, $page - 1);
      unset($query['_wrapper_format'], $query['ajax_page_state']);
      $links[$page] = Url::fromRoute($variables['pager']['#route_name'] ?? '<none>', $variables['pager']['#route_parameters'] ?? [], ['query' => $query])->toString();
    }
    $variables['drubricks_pager'] = ['current' => $current, 'total' => $total, 'links' => $links];
    $variables['#cache']['contexts'][] = 'url.query_args';
  }

  /**
   * One inline error per date + time widget.
   *
   * A widget fieldset (e.g. a date + time field) and the element inside it can
   * both get the same inline error; show it once, next to the inputs.
   */
  #[Hook('preprocess_fieldset')]
  public function preprocessFieldset(array &$variables): void {
    $element = $variables['element'] ?? [];
    if (empty($element['#errors'])) {
      return;
    }
    $error = (string) $element['#errors'];
    $child_has_it = static function (array $element) use (&$child_has_it, $error): bool {
      foreach (Element::children($element) as $key) {
        if (!is_array($element[$key])) {
          continue;
        }
        if (isset($element[$key]['#errors']) && (string) $element[$key]['#errors'] === $error) {
          return TRUE;
        }
        if ($child_has_it($element[$key])) {
          return TRUE;
        }
      }
      return FALSE;
    };
    if ($child_has_it($element)) {
      $variables['errors'] = NULL;
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for views_exposed_form.
   *
   * Date filters of a View (core's datetime / date filter on days) get the
   * browser's date picker instead of a text box; the filter reads the
   * Y-m-d value it sends like typed text.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $view = $form_state->get('view');
    if (!$view) {
      return;
    }
    $identifiers = [];
    foreach ($view->display_handler->getHandlers('filter') as $filter) {
      if ($filter->isExposed() && in_array($filter->getPluginId(), ['datetime', 'date'], TRUE)
        && ($filter->options['value']['type'] ?? 'date') === 'date') {
        $identifiers[] = $filter->options['expose']['identifier'];
      }
    }
    $walk = static function (array &$element, bool $inside) use (&$walk, $identifiers): void {
      foreach (Element::children($element) as $key) {
        $in = $inside || in_array($key, $identifiers, TRUE);
        if ($in && ($element[$key]['#type'] ?? NULL) === 'textfield') {
          $element[$key]['#type'] = 'date';
          unset($element[$key]['#size'], $element[$key]['#maxlength']);
        }
        if (is_array($element[$key])) {
          $walk($element[$key], $in);
        }
      }
    };
    if ($identifiers) {
      $walk($form, FALSE);
    }
  }

  /**
   * The default kit palette on the theme's settings page.
   *
   * Saved by core's theme settings form into drubricks.settings (schema:
   * config/schema/drubricks.schema.yml); users can still pick their own.
   */
  #[Hook('form_system_theme_settings_alter')]
  public function formSystemThemeSettingsAlter(array &$form): void {
    if (($form['config_key']['#value'] ?? NULL) !== 'drubricks.settings') {
      return;
    }
    $form['drubricks'] = [
      '#type' => 'details',
      '#title' => $this->t('UI kit'),
      '#open' => TRUE,
      '#weight' => -10,
    ];
    $form['drubricks']['default_palette'] = [
      '#type' => 'select',
      '#title' => $this->t('Colour palette'),
      '#description' => $this->t('Colours of the app for every user. Users choose only light, dark or auto (the system setting) in the account menu.'),
      '#options' => [
        'cobalt' => $this->t('Cobalt', [], ['context' => 'Colour palette']),
        'lagoon' => $this->t('Lagoon', [], ['context' => 'Colour palette']),
        'indigo' => $this->t('Indigo', [], ['context' => 'Colour palette']),
        'berry' => $this->t('Berry', [], ['context' => 'Colour palette']),
        'sage' => $this->t('Sage', [], ['context' => 'Colour palette']),
        // The project's own palettes: css/palettes.css.
        'orange' => $this->t('Orange', [], ['context' => 'Colour palette']),
      ],
      '#default_value' => $this->themeSettings->getSetting('default_palette', 'drubricks') ?: 'cobalt',
    ];
  }

}
