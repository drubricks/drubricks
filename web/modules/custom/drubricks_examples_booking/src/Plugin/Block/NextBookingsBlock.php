<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_examples_booking\Dashboard\BookingDashboardBlockBase;
use Drupal\drubricks_examples_booking\Entity\Booking;

/**
 * Panel: the next bookings (running now or later), with their status.
 */
#[Block(
  id: 'drubricks_examples_booking_next',
  admin_label: new TranslatableMarkup('Next bookings (Booking)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class NextBookingsBlock extends BookingDashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.booking.drubricks_calendar');
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkTitle(): string {
    return (string) $this->t('Calendar');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $account = $this->currentUser;
    $now = $this->time->getRequestTime();
    $bookings = $this->bookingsBetween($account, $now, $this->daysLater($now, 30), 6);
    if (!$bookings) {
      return $this->emptyLine((string) $this->t('Nothing booked in the next 30 days'));
    }
    $rows = ['#cache' => ['max-age' => 300]];
    foreach ($bookings as $booking) {
      $resource = $booking->getResource();
      $state = (string) $booking->get('state')->value;
      $rows[$booking->id()] = [
        '#type' => 'component',
        '#component' => 'drubricks:p-row',
        '#props' => [
          'title' => (string) $booking->label(),
          'subtitle' => implode(' · ', array_filter([$this->when($booking), (string) $resource?->label()])),
        ] + $this->recordLink($booking),
        '#slots' => [
          'leading' => [
            '#type' => 'component',
            '#component' => 'drubricks:tile',
            '#props' => [
              'name' => (string) ($resource?->label() ?? $booking->label()),
              'color' => $resource?->getColor() ?? ColorTokens::forName((string) $resource?->label()),
            ],
          ],
          'trailing' => [
            '#type' => 'component',
            '#component' => 'drubricks:pill',
            '#props' => [
              'label' => (string) (Booking::states()[$state] ?? $state),
              'color' => Booking::STATE_COLORS[$state] ?? 'gray',
              'icon' => $state === 'pending' ? 'hourglass' : 'checkmark',
            ],
          ],
        ],
      ];
    }
    return $rows;
  }

}
