<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_examples_booking\Dashboard\BookingDashboardBlockBase;

/**
 * Panel: booked hours per resource in the next seven days (bar rows).
 */
#[Block(
  id: 'drubricks_examples_booking_busiest',
  admin_label: new TranslatableMarkup('Busiest resources · 7 days (Booking)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class BusiestResourcesBlock extends BookingDashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.booking_resource.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $account = $this->currentUser;
    $from = $this->startOfDay();
    $to = $this->startOfDay(7);
    $hours = $resources = [];
    foreach ($this->bookingsBetween($account, $from, $to, 500) as $booking) {
      $resource = $booking->getResource();
      if (!$resource) {
        continue;
      }
      $start = max($this->bookingTime($booking, 'starts'), $from);
      $end = min($this->bookingTime($booking, 'ends'), $to);
      $hours[$resource->id()] = ($hours[$resource->id()] ?? 0) + max(0, $end - $start) / 3600;
      $resources[$resource->id()] = $resource;
    }
    if (!$hours) {
      return $this->emptyLine((string) $this->t('Nothing booked this week'));
    }
    arsort($hours);
    $hours = array_slice($hours, 0, 5, TRUE);
    $max = max($hours);
    $rows = ['#cache' => ['max-age' => $this->untilMidnight()]];
    foreach ($hours as $id => $total) {
      $resource = $resources[$id];
      $rows[$id] = [
        '#type' => 'component',
        '#component' => 'drubricks:bar-row',
        '#props' => [
          'label' => (string) $resource->label(),
          'percent' => round($total / $max * 100, 1),
          'color' => $resource->getColor() ?? ColorTokens::forName((string) $resource->label()),
          'value' => (string) $this->t('@hours h', ['@hours' => round($total, 1)]),
          // Room for longer names; cut with an ellipsis, full name on hover.
          'attributes' => new Attribute([
            'class' => ['[grid-template-columns:minmax(0,9rem)_1fr_3rem]', '[&>span:first-child]:truncate'],
            'title' => (string) $resource->label(),
          ]),
        ],
      ];
    }
    return $rows;
  }

}
