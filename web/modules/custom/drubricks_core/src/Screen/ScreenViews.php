<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Screen;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_core\Htmx\HtmxResponses;
use Drupal\drubricks_core\Htmx\Swap;
use Symfony\Component\HttpFoundation\Request;

/**
 * The views of an entity type: list, board, calendar, table, chart.
 *
 * A view other than the list exists when the screen handler declares it
 * (board(), calendar(), table(), chart()) and the entity type has its link
 * template (drubricks-board, drubricks-calendar, drubricks-table,
 * drubricks-chart). The view marked 'default' comes
 * first in the switch, is the sidebar target and the place to return to.
 */
final class ScreenViews {

  /**
   * View key => [link template, route suffix, title, icon].
   */
  private const VIEWS = [
    'board' => ['drubricks-board', 'drubricks_board', 'board'],
    'calendar' => ['drubricks-calendar', 'drubricks_calendar', 'calendar'],
    'table' => ['drubricks-table', 'drubricks_table', 'table'],
    'chart' => ['drubricks-chart', 'drubricks_chart', 'chart'],
  ];

  /**
   * Available views, the default first.
   *
   * @return array<string, array{route: string, title: string, icon: string, default: bool}>
   *   Views keyed by board / calendar / list.
   */
  public static function available(EntityTypeInterface $type, ScreenHandler $handler): array {
    $views = [];
    $titles = ['board' => t('Board'), 'calendar' => t('Calendar'), 'table' => t('Table'), 'chart' => t('Chart')];
    foreach (self::VIEWS as $key => [$template, $suffix, $icon]) {
      $definition = $type->hasLinkTemplate($template) ? $handler->{$key}() : NULL;
      if ($definition) {
        $views[$key] = [
          'route' => 'entity.' . $type->id() . '.' . $suffix,
          'title' => (string) $titles[$key],
          'icon' => $icon,
          'default' => !empty($definition['default']),
        ];
      }
    }
    if (!$views) {
      return [];
    }
    $views['list'] = [
      'route' => 'entity.' . $type->id() . '.collection',
      'title' => (string) t('List'),
      'icon' => 'list',
      'default' => FALSE,
    ];
    uasort($views, static fn ($a, $b) => $b['default'] <=> $a['default']);
    return $views;
  }

  /**
   * Links of the view switch (kit segmented control), or [].
   *
   * @return array<int, array<string, mixed>>
   *   Links.
   */
  public static function links(EntityTypeInterface $type, ScreenHandler $handler, string $active): array {
    $links = [];
    foreach (self::available($type, $handler) as $key => $view) {
      $url = Url::fromRoute($view['route']);
      $element = ['#attributes' => []];
      Swap::link($element, $url, Swap::CONTENT);
      $links[] = [
        'title' => $view['title'],
        'icon' => $view['icon'],
        'url' => $url->toString(),
        'active' => $key === $active,
        'attributes' => $element['#attributes'],
      ];
    }
    return $links;
  }

  /**
   * The "add" button of the view header (opens the add form in a modal), or NULL.
   *
   * @return array{url: string, title: string, attributes: array}|null
   *   Values for drubricks-screen-header.html.twig.
   */
  public static function add(EntityTypeInterface $type, ScreenHandler $handler): ?array {
    $url = $handler->addUrl();
    if (!$url) {
      return NULL;
    }
    $title = (string) t('New @type', ['@type' => $type->getSingularLabel()]);
    $element = ['#attributes' => []];
    Swap::modal($element, $url, $title);
    return ['url' => $url->toString(), 'title' => $title, 'attributes' => $element['#attributes']];
  }

  /**
   * The subtitle of the view header: the number of records ("9 items").
   */
  public static function count(int $count): PluralTranslatableMarkup {
    return new PluralTranslatableMarkup($count, '1 item', '@count items');
  }

  /**
   * The main view: the default board / calendar, else the list.
   */
  public static function defaultUrl(EntityTypeInterface $type, ScreenHandler $handler): Url {
    foreach (self::available($type, $handler) as $view) {
      if ($view['default']) {
        return Url::fromRoute($view['route']);
      }
    }
    return Url::fromRoute('entity.' . $type->id() . '.collection');
  }

  /**
   * The board / calendar an HTMX request came from (with its query), or NULL.
   *
   * Lets add / edit / delete started in a view return to that view: a view
   * never sends the user to another page (rule in AGENTS.md).
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Entity\EntityTypeInterface $type
   *   The entity type.
   * @param \Drupal\drubricks_core\Screen\ScreenHandler $handler
   *   Its screen handler.
   * @param array $query
   *   Query values to set (e.g. ['open' => 5]); NULL values are removed.
   */
  public static function cameFrom(Request $request, EntityTypeInterface $type, ScreenHandler $handler, array $query = []): ?Url {
    $current = HtmxResponses::currentPage($request);
    if (!$current) {
      return NULL;
    }
    foreach (self::available($type, $handler) as $key => $view) {
      if ($key === 'list') {
        continue;
      }
      $url = Url::fromRoute($view['route']);
      if (parse_url($current, PHP_URL_PATH) === $url->toString()) {
        parse_str((string) parse_url($current, PHP_URL_QUERY), $current_query);
        $merged = array_filter($query + $current_query, static fn ($v) => $v !== NULL);
        return $url->setOption('query', $merged);
      }
    }
    return NULL;
  }

}
