<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Screen;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_examples_booking\Entity\Booking;
use Drupal\drubricks_examples_booking\Entity\Resource;

/**
 * Resources: grouped by kind; location and capacity in the list.
 */
class ResourceScreen extends ScreenHandler {

  use BookingScreenTrait;

  /**
   * {@inheritdoc}
   */
  protected function sort(QueryInterface $query): void {
    $query->sort('kind', 'DESC')->sort('name');
  }

  /**
   * {@inheritdoc}
   */
  public function row(EntityInterface $entity): array {
    $row = parent::row($entity);
    if ($entity instanceof Resource) {
      $row['section'] = (string) (Resource::kinds()[$entity->get('kind')->value] ?? '');
      $row['subtitle'] = (string) $entity->get('location')->value;
      $capacity = (int) $entity->get('capacity')->value;
      $row['meta'] = $capacity ? (string) $this->formatPlural($capacity, '1 seat', '@count seats') : '';
      $args = ['@opens' => $entity->opens(), '@closes' => $entity->closes()];
      $row['text'] = (string) $this->t('Open @opens:00–@closes:00', $args);
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBlockers(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
    $cacheability?->addCacheTags($this->entityTypeManager->getDefinition('booking')->getListCacheTags());
    $count = (int) $this->entityTypeManager->getStorage('booking')->getQuery()
      ->accessCheck(FALSE)
      ->condition('resource', $entity->id())
      ->count()
      ->execute();
    return $count ? [(string) $this->t('@count bookings use this resource.', ['@count' => $count])] : [];
  }

  /**
   * {@inheritdoc}
   */
  protected function view(EntityInterface $entity, bool $compact): array {
    if (!$entity instanceof Resource) {
      return parent::detail($entity);
    }
    // Upcoming bookings (from today), one query; the hours of the next week.
    $today = $this->startOfDay();
    $week_end = $this->startOfDay(7);
    $ids = $this->accessibleQuery('booking', $this->currentUser)
      ?->condition('resource', $entity->id())
        ->condition('state', 'cancelled', '<>')
        ->condition('ends', $this->storageValue($today), '>')
        ->sort('starts')
        ->range(0, 50)
        ->execute() ?? [];
    $bookings = array_filter($this->entityTypeManager->getStorage('booking')->loadMultiple($ids), static fn ($booking) => $booking instanceof Booking);
    $hours = 0;
    $items = [];
    foreach ($bookings as $booking) {
      [$start, $end] = $this->span($booking);
      if ($start < $week_end) {
        $hours += (min($end, $week_end) - max($start, $today)) / 3600;
      }
      if (count($items) < 8) {
        $items[] = [
          'title' => (string) $booking->label(),
          'subtitle' => $this->dayLabel($this->localTime($start, 'Y-m-d'), 'D j M') . ' · ' . $this->localTime($start) . '–' . $this->localTime($end),
          'status' => $this->statusPill($booking),
          'link' => $this->recordLink($booking),
        ];
      }
    }
    // Fields hidden in Manage display are hidden here too.
    $kind = $this->shows($entity, 'kind') ? (string) $entity->get('kind')->value : '';
    $location = $this->shows($entity, 'location') ? (string) $entity->get('location')->value : '';
    $show_capacity = $this->shows($entity, 'capacity');
    $show_hours = $this->shows($entity, 'opens') || $this->shows($entity, 'closes');
    $capacity = (int) $entity->get('capacity')->value;
    $opens = sprintf('%02d:00', $entity->opens());
    $closes = sprintf('%02d:00', $entity->closes());
    $owner = $entity->getOwnerId() ? $entity->getOwner() : NULL;
    // Bookable on weekdays (the calendar's business days).
    $weekday = (int) $this->localTime($this->time->getRequestTime(), 'N') <= 5;
    $hours_rows = [
      [
        'label' => (string) $this->t('Monday – Friday'),
        'value' => $opens . '–' . $closes,
        'today' => $weekday,
        'open' => TRUE,
      ],
      [
        'label' => (string) $this->t('Saturday – Sunday'),
        'value' => (string) $this->t('Closed'),
        'today' => !$weekday,
        'open' => FALSE,
      ],
    ];
    return [
      '#theme' => 'booking_resource_detail',
      '#compact' => $compact,
      '#title' => (string) $entity->label(),
      '#color' => $entity->getColor() ?? ColorTokens::forName((string) $entity->label()),
      '#kind' => isset(Resource::KIND_ICONS[$kind]) ? [
        'label' => (string) Resource::kinds()[$kind],
        'icon' => Resource::KIND_ICONS[$kind],
      ] : NULL,
      '#location' => $location,
      '#stats' => array_values(array_filter([
        $show_capacity ? [
          'label' => (string) $this->t('Capacity'),
          'icon' => 'users',
          'color' => 'teal',
          'value' => $capacity ?: '—',
          'sub' => $capacity ? (string) $this->formatPlural($capacity, '1 seat', '@count seats') : (string) $this->t('No seats'),
        ] : NULL,
        $show_hours ? [
          'label' => (string) $this->t('Open'),
          'icon' => 'clock',
          'color' => 'blue',
          'value' => $entity->closes() - $entity->opens(),
          'unit' => (string) $this->t('h a day'),
          'sub' => $opens . '–' . $closes . ', ' . $this->t('Mon–Fri'),
        ] : NULL,
        [
          'label' => (string) $this->t('Next 7 days'),
          'icon' => 'calendar-check',
          'color' => 'purple',
          'value' => round($hours, 1),
          'unit' => (string) $this->t('h'),
          'sub' => (string) $this->formatPlural(count(array_filter($bookings, fn ($b) => $this->span($b)[0] < $week_end)), '1 booking', '@count bookings'),
        ],
      ])),
      '#hours' => $show_hours ? $hours_rows : [],
      // NULL: hidden in Manage display; []: no rules.
      '#rules' => $this->shows($entity, 'rules') ? array_values(array_filter(array_map('trim', preg_split('/\n+|(?<=\.)\s+/', (string) $entity->get('rules')->value) ?: []))) : NULL,
      '#bookings' => $items,
      '#facts' => $this->visibleFacts($entity, [
        [
          'field' => 'kind',
          'label' => $this->t('Kind'),
          'value' => (string) (Resource::kinds()[$kind] ?? '—'),
          'icon' => Resource::KIND_ICONS[$kind] ?? '',
        ],
        [
          'field' => 'location',
          'label' => $this->t('Location'),
          'value' => $location ?: '—',
          'icon' => 'map-pin',
        ],
        [
          'field' => 'uid',
          'label' => $this->t('Owner'),
          'value' => $owner?->getDisplayName() ?? '—',
          'avatar' => $owner?->getDisplayName(),
        ],
      ]),
      // Fields added in Manage fields (and others it does not place).
      '#fields' => $this->displayFields($entity, ['kind', 'location', 'capacity', 'opens', 'closes', 'rules', 'uid']),
      '#cache' => ['tags' => ['booking_list'], 'max-age' => $this->untilMidnight()],
    ];
  }

}
