<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_examples_booking\Dashboard\BookingDashboardBlockBase;

/**
 * Stat tile: bookings today (pending ones in the sub line).
 */
#[Block(
  id: 'drubricks_examples_booking_today_stats',
  admin_label: new TranslatableMarkup('Bookings today (Booking)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class TodayStatsBlock extends BookingDashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.booking.drubricks_calendar');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $account = $this->currentUser;
    $bookings = $this->bookingsBetween($account, $this->startOfDay(), $this->startOfDay(1));
    $pending = count(array_filter($bookings, static fn ($booking) => $booking->get('state')->value === 'pending'));
    return [
      '#cache' => ['max-age' => $this->untilMidnight()],
    ] + $this->stat([
      'label' => (string) $this->t('Bookings today'),
      'icon' => 'calendar-check',
      'color' => 'teal',
      'value' => count($bookings),
      'sub' => $pending ? (string) $this->formatPlural($pending, '1 pending approval', '@count pending approval') : (string) $this->t('All confirmed'),
    ], $this->getUrl());
  }

}
