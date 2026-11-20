<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Dashboard;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AccountInterface;
use Drupal\drubricks_core\Dashboard\DashboardBlockBase;
use Drupal\drubricks_examples_booking\Datetime\BookingTimeTrait;
use Drupal\drubricks_examples_booking\Entity\Booking;

/**
 * Shared helpers of the booking dashboard blocks.
 */
abstract class BookingDashboardBlockBase extends DashboardBlockBase {

  use BookingTimeTrait;

  /**
   * Active bookings overlapping [from, to) (timestamps), by start.
   *
   * Resources are loaded in the same go (one query each).
   *
   * @return \Drupal\drubricks_examples_booking\Entity\Booking[]
   *   Bookings keyed by id.
   */
  protected function bookingsBetween(AccountInterface $account, int $from, int $to, int $limit = 200): array {
    $ids = $this->accessibleQuery('booking', $account)
      ->condition('state', 'cancelled', '<>')
      ->condition('ends', $this->storageValue($from), '>')
      ->condition('starts', $this->storageValue($to), '<')
      ->sort('starts')
      ->range(0, $limit)
      ->execute();
    $bookings = $this->entityTypeManager->getStorage('booking')->loadMultiple($ids);
    $this->preloadReferences($bookings, 'resource');
    return array_filter($bookings, static fn ($booking) => $booking instanceof Booking);
  }

  /**
   * Returns "Today 09:00–10:30" or "Mon 16 Nov 09:00–10:30" for a booking.
   */
  protected function when(Booking $booking): string {
    $start = $this->bookingTime($booking, 'starts');
    $end = $this->bookingTime($booking, 'ends');
    $label = abs($this->daysFromNow($start)) <= 1
      ? $this->dayLabel($this->localTime($start, 'Y-m-d'))
      : $this->localTime($start, 'D j M');
    return $label . ' ' . $this->localTime($start) . '–' . $this->localTime($end);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['booking_list', 'booking_resource_list']);
  }

}
