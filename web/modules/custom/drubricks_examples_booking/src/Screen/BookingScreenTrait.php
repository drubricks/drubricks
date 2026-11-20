<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Screen;

use Drupal\Core\Entity\EntityInterface;
use Drupal\drubricks_examples_booking\Datetime\BookingTimeTrait;
use Drupal\drubricks_examples_booking\Entity\Booking;

/**
 * Pieces shared by the booking and resource screens.
 */
trait BookingScreenTrait {

  use BookingTimeTrait;

  /**
   * The side panel of the calendar: the compact record view + the facts.
   */
  public function panel(EntityInterface $entity): array {
    return [
      'detail' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['drubricks-detail-body']],
        'view' => $this->view($entity, TRUE),
      ],
      'facts' => ['#title' => $this->t('Record')] + $this->inspector($entity),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function detail(EntityInterface $entity): array {
    return $this->view($entity, FALSE) + ['#drubricks_header' => TRUE];
  }

  /**
   * The record rendered with components; compact = side panel.
   */
  abstract protected function view(EntityInterface $entity, bool $compact): array;

  /**
   * Start and end of a booking.
   *
   * @return array{0: int, 1: int}
   *   Timestamps; 0 when not set.
   */
  protected function span(Booking $booking): array {
    return [$this->bookingTime($booking, 'starts'), $this->bookingTime($booking, 'ends')];
  }

  /**
   * Status pill values of a booking (with an icon: not colour alone).
   *
   * @return array{label: string, color: string, icon: string}
   *   Pill props.
   */
  protected function statusPill(Booking $booking): array {
    $state = (string) $booking->get('state')->value;
    return [
      'label' => (string) (Booking::states()[$state] ?? $state),
      'color' => Booking::STATE_COLORS[$state] ?? 'gray',
      'icon' => ['confirmed' => 'checkmark', 'pending' => 'hourglass', 'cancelled' => 'xmark'][$state] ?? 'circle',
    ];
  }

  /**
   * Returns "Today", "Tomorrow", "In 3 days" or "2 days ago" for a time.
   */
  protected function daysFromToday(int $timestamp): string {
    $diff = $this->daysFromNow($timestamp);
    return match (TRUE) {
      abs($diff) <= 1 => $this->dayLabel($this->localTime($timestamp, 'Y-m-d')),
      $diff > 1 => (string) $this->formatPlural($diff, 'In 1 day', 'In @count days'),
      default => (string) $this->formatPlural(-$diff, '1 day ago', '@count days ago'),
    };
  }

  /**
   * A duration, e.g. "1 hour 30 min" (core's date formatter).
   */
  protected function duration(int $start, int $end): string {
    return (string) $this->dateFormatter->formatInterval(max(0, $end - $start), 2);
  }

}
