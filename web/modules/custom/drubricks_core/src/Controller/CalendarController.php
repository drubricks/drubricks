<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Controller;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\drubricks_core\Api\ApiResponse;
use Drupal\drubricks_core\Api\EntityApiControllerBase;
use Drupal\drubricks_core\Entity\DrubricksEntityInterface;
use Drupal\drubricks_core\Screen\RecordPanelTrait;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_core\Screen\ScreenViews;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Calendar view of any Drubricks entity type with start / end dates.
 *
 * The screen handler declares it (ScreenHandler::calendar()); this
 * controller renders the UI.calendar island (js/calendar.js) and saves a
 * moved or resized event with a generic PATCH endpoint — field access +
 * entity validation, so a refused change (e.g. a double booking) returns 422
 * and the event snaps back with the message.
 */
final class CalendarController extends EntityApiControllerBase {

  use RecordPanelTrait;

  /**
   * Events shown at most.
   */
  private const LIMIT = 1000;

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
   * The calendar page. ?date=YYYY-MM-DD picks the week, ?open=ID a record.
   */
  public function calendar(Request $request, string $entity_type_id): array {
    $type = $this->entityTypeManager()->getDefinition($entity_type_id);
    $handler = $this->handler($entity_type_id);
    $calendar = $handler->calendar() ?? throw new NotFoundHttpException();
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);

    $ids = $handler->query($request)->range(0, self::LIMIT)->execute();
    $events = [];
    $records = $storage->loadMultiple($ids);
    $cache = $handler->cacheability($records);
    foreach ($records as $entity) {
      if ($entity instanceof ContentEntityInterface && ($event = $this->event($entity, $handler, $calendar))) {
        $events[] = $event;
      }
    }

    $resources = [];
    if (!empty($calendar['resource'])) {
      $target = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id)[$calendar['resource']]->getSetting('target_type');
      $resource_storage = $this->entityTypeManager()->getStorage($target);
      $resource_ids = $resource_storage->getQuery()->accessCheck(TRUE)->sort($this->entityTypeManager()->getDefinition($target)->getKey('label') ?: 'id')->execute();
      $cache->addCacheTags($this->entityTypeManager()->getDefinition($target)->getListCacheTags());
      foreach ($resource_storage->loadMultiple($resource_ids) as $resource) {
        $resources[] = $handler->calendarResource($resource) + [
          'id' => (string) $resource->id(),
          'title' => (string) $resource->label(),
          'color' => $resource instanceof DrubricksEntityInterface ? ($resource->getColor() ?? 'blue') : 'blue',
        ];
      }
    }

    $add = ScreenViews::add($type, $handler);
    $date = (string) $request->query->get('date', '');
    $build = [
      '#theme' => 'drubricks_calendar',
      '#title' => $handler->title(),
      '#views' => ScreenViews::links($type, $handler, 'calendar'),
      '#add' => $add,
      '#calendar' => [
        'events' => $events,
        'resources' => $resources,
        'view' => (string) $request->query->get('view', $calendar['view'] ?? 'week'),
        'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : NULL,
        'hours' => [
          'start' => $calendar['hours'][0] ?? 8,
          'end' => $calendar['hours'][1] ?? 18,
          // ISO weekdays (1 = Monday … 7 = Sunday); Monday to Friday by default.
          'days' => array_values(array_map('intval', $calendar['days'] ?? [1, 2, 3, 4, 5])),
        ],
        'step' => $calendar['step'] ?? 15,
        'endpoint' => Url::fromRoute('drubricks_core.calendar_move', [
          'entity_type_id' => $entity_type_id,
          'entity' => 0,
        ])->toString(),
        'addUrl' => $add['url'] ?? NULL,
        'addTitle' => $add['title'] ?? NULL,
        'key' => 'drubricks-calendar-' . $entity_type_id,
      ],
      '#inspector' => $this->recordPanel($request, $entity_type_id, $handler),
      '#attached' => ['library' => ['drubricks_core/calendar']],
    ];
    $cache
      ->addCacheTags($type->getListCacheTags())
      ->addCacheContexts(['user', 'user.permissions', 'url.query_args', 'timezone'])
      ->applyTo($build);
    return $build;
  }

  /**
   * PATCH /apps/_calendar/{entity_type_id}/{entity}: { start, end, resource }.
   *
   * Times are local "YYYY-MM-DDTHH:mm" in the user's timezone.
   */
  public function move(Request $request, string $entity_type_id, ContentEntityInterface $entity): JsonResponse {
    $handler = $this->handler($entity_type_id);
    $calendar = $handler->calendar() ?? throw new NotFoundHttpException();
    // The route loads the record and checks update access (_entity_access).
    $body = $this->jsonBody($request);
    $values = [];
    foreach (['start', 'end'] as $key) {
      if (isset($body[$key])) {
        $storage_value = self::toStorage((string) $body[$key]);
        if ($storage_value === NULL) {
          return ApiResponse::error(422, (string) $this->t('Invalid date.'));
        }
        $values[$calendar[$key]] = $storage_value;
      }
    }
    if (!empty($calendar['resource']) && isset($body['resource']) && $body['resource'] !== '') {
      $values[$calendar['resource']] = (int) $body['resource'];
    }
    if ($error = $this->updateEntity($entity, $values)) {
      return $error;
    }
    return ApiResponse::ok($this->event($entity, $handler, $calendar));
  }

  /**
   * An entity as a UI.calendar event, or NULL without dates.
   */
  private function event(ContentEntityInterface $entity, ScreenHandler $handler, array $calendar): ?array {
    $start = $entity->get($calendar['start'])->value;
    $end = $entity->get($calendar['end'])->value;
    if (!$start) {
      return NULL;
    }
    $event = $handler->event($entity) + [
      'id' => (string) $entity->id(),
      'start' => self::toLocal($start),
      'end' => $end ? self::toLocal($end) : NULL,
      'readOnly' => !$entity->access('update'),
    ];
    if (!empty($calendar['resource'])) {
      $event['resource'] = (string) $entity->get($calendar['resource'])->target_id;
    }
    return $event;
  }

  /**
   * Storage value (UTC) → local "YYYY-MM-DDTHH:mm" in the user's timezone.
   */
  public static function toLocal(string $value): string {
    $date = new \DateTimeImmutable($value, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    return $date->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d\TH:i');
  }

  /**
   * Local "YYYY-MM-DDTHH:mm" (user's timezone) → storage value (UTC), or NULL.
   */
  public static function toStorage(string $local): ?string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $local)) {
      return NULL;
    }
    $date = new \DateTimeImmutable($local, new \DateTimeZone(date_default_timezone_get()));
    return $date->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE))->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
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

}
