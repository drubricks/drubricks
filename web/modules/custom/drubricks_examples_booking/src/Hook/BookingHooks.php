<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Templates of the booking and resource screens (details pane, side panel).
 */
final class BookingHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    // fields: ScreenHandler::displayFields() (Manage display, the rest).
    $common = ['compact' => FALSE, 'title' => '', 'color' => NULL, 'facts' => [], 'fields' => []];
    return [
      'booking_detail' => [
        'variables' => $common + ['status' => NULL, 'resource' => NULL, 'when' => [], 'attendees' => [], 'notes' => ''],
      ],
      'booking_resource_detail' => [
        'variables' => $common + [
          'kind' => NULL,
          'location' => '',
          'stats' => [],
          'hours' => [],
          'rules' => [],
          'bookings' => [],
        ],
      ],
    ];
  }

}
