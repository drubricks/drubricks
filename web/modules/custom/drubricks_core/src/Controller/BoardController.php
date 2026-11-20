<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Controller;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Url;
use Drupal\drubricks_core\Api\ApiResponse;
use Drupal\drubricks_core\Api\EntityApiControllerBase;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_core\Screen\RecordPanelTrait;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_core\Screen\ScreenViews;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Board (kanban) view of any Drubricks entity type, and saving moves.
 *
 * The screen handler declares the board (ScreenHandler::board()); this
 * controller renders it as a JS island (js/board.js, UI.board) and saves a
 * move with the generic PATCH endpoint — field access + entity validation,
 * so a refused move comes back as 422 and the island reverts it.
 */
final class BoardController extends EntityApiControllerBase {

  use RecordPanelTrait;

  /**
   * Records shown on a board at most.
   */
  private const LIMIT = 500;

  /**
   * The entity field manager.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * The board page.
   */
  public function board(Request $request, string $entity_type_id): array {
    $type = $this->entityTypeManager()->getDefinition($entity_type_id);
    $handler = $this->handler($entity_type_id);
    $board = $handler->board() ?? throw new NotFoundHttpException();

    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    $ids = $handler->query($request)->range(0, self::LIMIT)->execute();
    $items = [];
    $records = $storage->loadMultiple($ids);
    $cache = $handler->cacheability($records);
    foreach ($records as $entity) {
      if ($entity instanceof ContentEntityInterface) {
        $items[] = $this->item($entity, $handler, $board);
      }
    }

    $lanes = [];
    foreach ($board['lanes'] as $value => [$label, $color]) {
      // Labels come translated from the screen handler (TranslatableMarkup).
      $lanes[] = ['id' => (string) $value, 'title' => (string) $label, 'color' => $color];
    }

    $filter = NULL;
    if (!empty($board['filter'])) {
      $ids = array_unique(array_filter(array_column($items, 'filter')));
      $definition = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id)[$board['filter']];
      $target = $definition->getSetting('target_type');
      $options = [];
      if ($target && $ids) {
        foreach ($this->entityTypeManager()->getStorage($target)->loadMultiple($ids) as $option) {
          if ($option->access('view label')) {
            $options[] = ['id' => (int) $option->id(), 'label' => (string) $option->label()];
          }
        }
        $cache->addCacheTags($this->entityTypeManager()->getDefinition($target)->getListCacheTags());
        usort($options, static fn ($a, $b) => strcasecmp($a['label'], $b['label']));
      }
      $selected = (int) $request->query->get($board['filter'], 0);
      $filter = [
        'field' => $board['filter'],
        'label' => (string) $definition->getLabel(),
        'options' => $options,
        'selected' => in_array($selected, array_column($options, 'id'), TRUE) ? $selected : 0,
      ];
    }

    $build = [
      '#theme' => 'drubricks_board',
      '#inspector' => $this->recordPanel($request, $entity_type_id, $handler),
      '#title' => $handler->title(),
      '#subtitle' => ScreenViews::count(count($items)),
      '#views' => ScreenViews::links($type, $handler, 'board'),
      '#add' => ScreenViews::add($type, $handler),
      '#filter' => $filter,
      '#board' => [
        'lanes' => $lanes,
        'items' => $items,
        'endpoint' => Url::fromRoute('drubricks_core.board_move', ['entity_type_id' => $entity_type_id, 'entity' => 0])->toString(),
        'field' => $board['field'],
        'order' => $board['order'] ?? NULL,
        'sum' => !empty($board['sum']),
        'filter' => $filter['field'] ?? NULL,
        'key' => 'drubricks-board-' . $entity_type_id,
      ],
      '#attached' => ['library' => ['drubricks_core/board']],
    ];
    $cache
      ->addCacheTags($type->getListCacheTags())
      ->addCacheContexts(['user', 'user.permissions', 'url.query_args'])
      ->applyTo($build);
    return $build;
  }

  /**
   * PATCH /apps/_board/{entity_type_id}/{entity}: { "<lane field>": "…", "<order field>": 2 }.
   */
  public function move(Request $request, string $entity_type_id, ContentEntityInterface $entity): JsonResponse {
    $handler = $this->handler($entity_type_id);
    $board = $handler->board() ?? throw new NotFoundHttpException();
    // The route loads the record and checks update access (_entity_access).
    // Only the board's own fields can be changed here.
    $allowed = [$board['field'] => TRUE];
    if (!empty($board['order'])) {
      $allowed[$board['order']] = TRUE;
    }
    $values = array_intersect_key($this->jsonBody($request), $allowed);
    if (isset($values[$board['field']]) && !isset($board['lanes'][$values[$board['field']]])) {
      return ApiResponse::error(422, (string) $this->t('Unknown lane.'));
    }
    if ($error = $this->updateEntity($entity, $values)) {
      return $error;
    }
    return ApiResponse::ok($this->item($entity, $handler, $board));
  }

  /**
   * The screen handler of an entity type.
   */
  private function handler(string $entity_type_id): ScreenHandler {
    $handler = $this->entityTypeManager()->getHandler($entity_type_id, 'drubricks_screen');
    if (!$handler instanceof ScreenHandler) {
      throw new NotFoundHttpException();
    }
    return $handler;
  }

  /**
   * A record as the board island needs it.
   */
  private function item(ContentEntityInterface $entity, ScreenHandler $handler, array $board): array {
    $card = $handler->card($entity);
    $item = $card + [
      'id' => (int) $entity->id(),
      'lane' => (string) $entity->get($board['field'])->value,
      'url' => $entity->toUrl()->toString(),
      'editable' => $entity->access('update'),
    ];
    if (!empty($board['order'])) {
      $item['order'] = (int) $entity->get($board['order'])->value;
    }
    if (!empty($board['sum'])) {
      $item['sum'] = (float) $entity->get($board['sum'])->value;
    }
    if (!empty($board['filter'])) {
      $item['filter'] = (int) $entity->get($board['filter'])->target_id;
    }
    if (isset($item['color'])) {
      $item['color'] = ColorTokens::css($item['color']);
    }
    return $item;
  }

}
