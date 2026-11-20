<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Screen;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_examples_booking\Entity\Booking;
use Drupal\drubricks_examples_booking\Entity\Resource;

/**
 * Bookings: a calendar (the main view) and a list grouped by day.
 */
class BookingScreen extends ScreenHandler {

  use BookingScreenTrait;

  /**
   * {@inheritdoc}
   */
  public function calendar(): array {
    return [
      'start' => 'starts',
      'end' => 'ends',
      'resource' => 'resource',
      'view' => 'week',
      'hours' => [7, 20],
      'step' => 15,
      'default' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function event(EntityInterface $entity): array {
    $event = parent::event($entity);
    if ($entity instanceof Booking) {
      // The colour comes from the resource (column), a pending booking is
      // tentative, a cancelled one grey and read-only.
      unset($event['color']);
      $state = $entity->get('state')->value;
      $event['tentative'] = $state === 'pending';
      if ($state === 'cancelled') {
        $event['color'] = 'gray';
        $event['readOnly'] = TRUE;
      }
    }
    return $event;
  }

  /**
   * {@inheritdoc}
   */
  public function calendarResource(EntityInterface $resource): array {
    if (!$resource instanceof Resource) {
      return [];
    }
    $kind = (string) (Resource::kinds()[$resource->get('kind')->value] ?? '');
    return [
      'sub' => trim($kind . ' · ' . $resource->get('location')->value, ' ·'),
      'hours' => ['start' => $resource->opens(), 'end' => $resource->closes(), 'days' => [1, 2, 3, 4, 5]],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function sort(QueryInterface $query): void {
    $query->sort('starts', 'DESC');
  }

  /**
   * {@inheritdoc}
   */
  public function rows(array $entities, ?EntityInterface $active): array {
    // Referenced records of the whole page in one query each, not per row.
    $this->preloadReferences($entities, 'resource');
    return parent::rows($entities, $active);
  }

  /**
   * {@inheritdoc}
   */
  public function row(EntityInterface $entity): array {
    $row = parent::row($entity);
    if ($entity instanceof Booking) {
      $resource = $this->referenced($entity, 'resource');
      $resource = $resource instanceof Resource ? $resource : NULL;
      [$start, $end] = $this->span($entity);
      $row['color'] = $resource?->getColor();
      $row['subtitle'] = (string) $resource?->label();
      $row['section'] = $this->localTime($start, 'l, j F Y');
      $row['meta'] = $start ? $this->localTime($start) . ($end ? '–' . $this->localTime($end) : '') : '';
      $row['text'] = trim($row['subtitle'] . ' · ' . $resource?->get('location')->value, ' ·');
      $row['subtitle'] = '';
      // Confirmed is the normal case: only the exceptions get a pill.
      if ($entity->get('state')->value !== 'confirmed') {
        $row['pill'] = $this->statusPill($entity);
      }
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function view(EntityInterface $entity, bool $compact): array {
    if (!$entity instanceof Booking) {
      return parent::detail($entity);
    }
    // Fields hidden in Manage display are hidden here too.
    $resource = $this->shows($entity, 'resource') ? $this->referenced($entity, 'resource') : NULL;
    $resource = $resource instanceof Resource ? $resource : NULL;
    [$start, $end] = $this->span($entity);
    $show_state = $this->shows($entity, 'state');
    $color = $resource?->getColor() ?? ColorTokens::forName((string) $resource?->label());
    $when = [];
    if ($start && $this->shows($entity, 'starts')) {
      $when[] = [
        'icon' => 'calendar',
        'title' => $this->localTime($start, 'l, j F Y'),
        'subtitle' => $this->daysFromToday($start),
      ];
      $when[] = [
        'icon' => 'clock',
        'title' => $this->localTime($start) . '–' . $this->localTime($end),
        'subtitle' => $this->duration($start, $end),
      ];
    }
    if ($resource) {
      $kind = (string) $resource->get('kind')->value;
      $when[] = [
        'icon' => Resource::KIND_ICONS[$kind] ?? 'map-pin',
        'title' => (string) $resource->label(),
        'subtitle' => implode(' · ', array_filter([
          (string) (Resource::kinds()[$kind] ?? ''),
          (string) $resource->get('location')->value,
        ])),
        'link' => $this->recordLink($resource),
      ];
    }
    $attendees = $this->shows($entity, 'attendees') ? array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) $entity->get('attendees')->value) ?: []))) : [];
    $owner = $entity->getOwnerId() ? $entity->getOwner() : NULL;
    return [
      '#theme' => 'booking_detail',
      '#compact' => $compact,
      '#title' => (string) $entity->label(),
      '#color' => $color,
      '#status' => $show_state ? $this->statusPill($entity) : NULL,
      '#resource' => $resource ? [
        'title' => (string) $resource->label(),
        'link' => $this->recordLink($resource),
      ] : NULL,
      '#when' => $when,
      '#attendees' => array_map(static fn ($name) => [
        'name' => $name,
        'color' => ColorTokens::forName($name),
      ], $attendees),
      '#notes' => $this->shows($entity, 'notes') ? (string) $entity->get('notes')->value : '',
      '#facts' => $this->visibleFacts($entity, [
        ['field' => 'state', 'label' => $this->t('Status'), 'pill' => $this->statusPill($entity)],
        [
          'field' => 'uid',
          'label' => $this->t('Booked by'),
          'value' => $owner?->getDisplayName() ?? '—',
          'avatar' => $owner?->getDisplayName(),
        ],
        [
          'label' => $this->t('Booked on'),
          'value' => $this->dateFormatter->format($entity->getCreatedTime(), 'custom', 'j M Y, H:i'),
        ],
      ]),
      // Fields added in Manage fields (and others it does not place).
      '#fields' => $this->displayFields($entity, ['resource', 'starts', 'ends', 'attendees', 'notes', 'state', 'uid']),
      '#cache' => ['max-age' => $this->untilMidnight()],
    ];
  }

}
