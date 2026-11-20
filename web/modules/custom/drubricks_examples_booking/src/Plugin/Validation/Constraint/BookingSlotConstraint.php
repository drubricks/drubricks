<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A booking ends after it starts, fits the opening hours, overlaps nothing.
 */
#[Constraint(
  id: 'BookingSlot',
  label: new TranslatableMarkup('Free slot within opening hours', [], ['context' => 'Validation']),
  type: 'entity:booking',
)]
final class BookingSlotConstraint extends SymfonyConstraint {

  /**
   * End not after start.
   */
  public string $order = 'A booking must end after it starts.';

  /**
   * Starts and ends on different days.
   */
  public string $sameDay = 'A booking must start and end on the same day.';

  /**
   * Outside the opening hours.
   */
  public string $hours = '%resource can be booked between @opens:00 and @closes:00.';

  /**
   * Overlaps another booking.
   */
  public string $overlap = '%resource is already booked @time (%title).';

}
