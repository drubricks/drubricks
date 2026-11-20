<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Screen;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\drubricks_core\Htmx\Swap;
use Symfony\Component\HttpFoundation\Request;

/**
 * The right-side panel of a view (board, calendar): the record from ?open=ID.
 *
 * Records opened from a view never leave it: the panel shows the record with
 * edit / delete (modals) and a link to the full page.
 */
trait RecordPanelTrait {

  /**
   * Inspector render array: the opened record, or an empty inspector.
   */
  protected function recordPanel(Request $request, string $entity_type_id, ScreenHandler $handler): array {
    $entity = NULL;
    if ($id = (int) $request->query->get('open', 0)) {
      $entity = $this->entityTypeManager()->getStorage($entity_type_id)->load($id);
      $entity = $entity && $entity->access('view') ? $entity : NULL;
    }
    if (!$entity) {
      return ['#theme' => 'drubricks_inspector'];
    }
    $actions = [];
    $actions_cacheability = new CacheableMetadata();
    foreach ($handler->actions($entity) as $key => $action) {
      $access = $action['url']->access(NULL, TRUE);
      $actions_cacheability->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        if ($reason = ScreenActions::reason($access)) {
          $actions[$key] = ScreenActions::disabled($action, $reason);
        }
        continue;
      }
      $element = ['#attributes' => []];
      Swap::modal($element, $action['url'], $action['modal_title'] ?? $action['title']);
      $actions[$key] = $action + ['attributes' => $element['#attributes']];
      $actions[$key]['url'] = $action['url']->toString();
    }
    $actions['open'] = [
      'title' => (string) $this->t('Open the full page'),
      'url' => $entity->toUrl()->toString(),
      'icon' => 'arrow-up-right',
      'attributes' => [],
    ];
    $build = [
      '#theme' => 'drubricks_inspector',
      '#title' => $entity->label(),
      '#content' => $handler->panel($entity),
      '#actions' => $actions,
      '#open' => TRUE,
    ];
    $handler->cacheability([$entity])->merge($actions_cacheability)->applyTo($build);
    return $build;
  }

}
