<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\drubricks_core\Htmx\Swap;
use Drupal\drubricks_core\Screen\ScreenActions;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_core\Screen\ScreenViews;
use Symfony\Component\HttpFoundation\Request;

/**
 * Renders the "list + details + inspector" screen of an entity type.
 *
 * Every route returns the whole screen: a direct visit gets a full page, an
 * HTMX request gets the same markup as main content and selects the level it
 * needs (#drubricks-detail, #drubricks-inspector, …).
 */
final class ScreenController extends ControllerBase {

  /**
   * The list with an empty details pane.
   */
  public function collection(Request $request, string $entity_type_id): array {
    return $this->screen($request, $entity_type_id);
  }

  /**
   * The list with a record in the details pane.
   */
  public function canonical(Request $request, RouteMatchInterface $route_match, string $entity_type_id): array {
    $entity = $route_match->getParameter($entity_type_id);
    return $this->screen($request, $entity_type_id, $entity);
  }

  /**
   * The list with the record and the inspector open.
   */
  public function inspector(Request $request, RouteMatchInterface $route_match, string $entity_type_id): array {
    $entity = $route_match->getParameter($entity_type_id);
    return $this->screen($request, $entity_type_id, $entity, NULL, TRUE);
  }

  /**
   * The list with the "add" form in the details pane (or a modal).
   */
  public function add(Request $request, string $entity_type_id): array {
    $entity = $this->entityTypeManager()->getStorage($entity_type_id)->create();
    $this->prefill($request, $entity);
    $form = $this->entityFormBuilder()->getForm($entity, 'add');
    return $this->screen($request, $entity_type_id, NULL, $form);
  }

  /**
   * The list with the edit form of a record.
   */
  public function edit(Request $request, RouteMatchInterface $route_match, string $entity_type_id): array {
    $entity = $route_match->getParameter($entity_type_id);
    $form = $this->entityFormBuilder()->getForm($entity, 'edit');
    return $this->screen($request, $entity_type_id, $entity, $form);
  }

  /**
   * The list with the delete confirmation of a record.
   */
  public function delete(Request $request, RouteMatchInterface $route_match, string $entity_type_id): array {
    $entity = $route_match->getParameter($entity_type_id);
    $form = $this->entityFormBuilder()->getForm($entity, 'delete');
    return $this->screen($request, $entity_type_id, $entity, $form);
  }

  /**
   * Prefills start / end / resource of a new record from a calendar slot.
   *
   * The calendar view opens the add form with ?start=…&end=…&resource=…
   * (local times) after a drag on empty time.
   */
  private function prefill(Request $request, EntityInterface $entity): void {
    $type = $entity->getEntityType();
    if (!$entity instanceof FieldableEntityInterface || !$type->hasHandlerClass('drubricks_screen')) {
      return;
    }
    $calendar = $this->entityTypeManager()->getHandler($type->id(), 'drubricks_screen')->calendar();
    if (!$calendar) {
      return;
    }
    foreach (['start', 'end'] as $key) {
      $value = CalendarController::toStorage((string) $request->query->get($key, ''));
      if ($value) {
        $entity->set($calendar[$key], $value);
      }
    }
    if (!empty($calendar['resource']) && ($resource = (int) $request->query->get('resource', 0))) {
      $entity->set($calendar['resource'], $resource);
    }
  }

  /**
   * Title of the list, board and calendar routes.
   */
  public function collectionTitle(string $entity_type_id): string {
    return (string) $this->entityTypeManager()->getDefinition($entity_type_id)->getCollectionLabel();
  }

  /**
   * Title of a record's page (like core's EntityController::title()).
   */
  public function title(RouteMatchInterface $route_match, string $entity_type_id): string {
    $entity = $route_match->getParameter($entity_type_id);
    return $entity instanceof EntityInterface ? (string) $entity->label() : $this->collectionTitle($entity_type_id);
  }

  /**
   * Title of the add form.
   */
  public function addTitle(string $entity_type_id): string {
    $type = $this->entityTypeManager()->getDefinition($entity_type_id);
    return (string) $this->t('New @type', ['@type' => $type->getSingularLabel()]);
  }

  /**
   * Title of the edit form.
   */
  public function editTitle(RouteMatchInterface $route_match, string $entity_type_id): string {
    $entity = $route_match->getParameter($entity_type_id);
    return $entity instanceof EntityInterface ? (string) $this->t('Edit @label', ['@label' => $entity->label()]) : $this->collectionTitle($entity_type_id);
  }

  /**
   * Title of the delete form.
   */
  public function deleteTitle(RouteMatchInterface $route_match, string $entity_type_id): string {
    $entity = $route_match->getParameter($entity_type_id);
    return $entity instanceof EntityInterface ? (string) $this->t('Delete @label', ['@label' => $entity->label()]) : $this->collectionTitle($entity_type_id);
  }

  /**
   * Builds the screen.
   */
  private function screen(Request $request, string $entity_type_id, ?EntityInterface $entity = NULL, ?array $form = NULL, bool $inspector_open = FALSE): array {
    $type = $this->entityTypeManager()->getDefinition($entity_type_id);
    /** @var \Drupal\drubricks_core\Screen\ScreenHandler $handler */
    $handler = $type->hasHandlerClass('drubricks_screen')
      ? $this->entityTypeManager()->getHandler($entity_type_id, 'drubricks_screen')
      : $this->entityTypeManager()->createHandlerInstance(ScreenHandler::class, $type);

    $collection_url = Url::fromRoute("entity.$entity_type_id.collection");
    $entities = $handler->load($request);
    $count = $handler->count($request);

    $search = ['#attributes' => []];
    Swap::search($search, $collection_url);

    $actions = [];
    $actions_cacheability = new CacheableMetadata();
    if ($entity && $form === NULL) {
      foreach ($handler->actions($entity) as $key => $action) {
        $access = $action['url']->access(NULL, TRUE);
        $actions_cacheability->addCacheableDependency($access);
        if (!$access->isAllowed()) {
          // Forbidden with a reason (e.g. Delete while other records use
          // this one): a disabled button that says why.
          if ($reason = ScreenActions::reason($access)) {
            $actions[$key] = ScreenActions::disabled($action, $reason);
          }
          continue;
        }
        $element = ['#attributes' => []];
        if (!empty($action['modal'])) {
          Swap::modal($element, $action['url'], $action['modal_title'] ?? $action['title']);
        }
        elseif (!empty($action['level'])) {
          Swap::link($element, $action['url'], $action['level'], $action['level'] !== Swap::INSPECTOR);
        }
        $actions[$key] = $action + ['attributes' => $element['#attributes']];
        $actions[$key]['url'] = $action['url']->toString();
      }
    }

    // The inspector only when there is something to show: a record (not a
    // form) whose handler returns facts for it.
    $inspector = $entity && $form === NULL ? $handler->inspector($entity) : [];

    $build = [
      '#theme' => 'drubricks_screen',
      '#entity_type' => $entity_type_id,
      '#list' => [
        '#theme' => 'drubricks_list',
        '#title' => $handler->title(),
        '#subtitle' => ScreenViews::count($count),
        '#rows' => $handler->rows($entities, $entity),
        '#add' => ScreenViews::add($type, $handler),
        '#search' => (string) $request->query->get('q', ''),
        '#search_attributes' => $search['#attributes'],
        '#pager' => ['#type' => 'pager'],
        '#views' => ScreenViews::links($type, $handler, 'list'),
      ],
      '#detail' => [
        '#theme' => 'drubricks_detail',
        '#title' => $entity && $form === NULL ? $entity->label() : NULL,
        '#actions' => $actions,
        '#content' => $form ?? ($entity ? $handler->detail($entity) : NULL),
        '#empty' => $handler->emptyText(),
        '#back_url' => $collection_url->toString(),
        '#inspector' => (bool) $inspector,
      ],
      '#inspector' => [
        '#theme' => 'drubricks_inspector',
        '#title' => $inspector ? $entity->label() : NULL,
        '#content' => $inspector ?: NULL,
        '#open' => $inspector_open && $inspector,
      ],
    ];

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['url.query_args:q', 'url.query_args:page', 'user.permissions', 'user'])
      ->addCacheTags($type->getListCacheTags());
    $cache->addCacheableDependency($handler->cacheability(array_filter([...array_values($entities), $entity])));
    $cache->addCacheableDependency($actions_cacheability);
    $cache->applyTo($build);
    return $build;
  }

}
