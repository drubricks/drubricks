<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Datetime;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\drubricks_examples_booking\Entity\Booking;

/**
 * Booking times with core's date APIs, in the user's timezone.
 *
 * "Now" is the request time (datetime.time), times come from the fields'
 * computed "date" property, output goes through the date formatter (which
 * formats in the user's timezone) and stored values are written in the
 * storage timezone (UTC). Needs $this->time and $this->dateFormatter (the
 * drubricks_core base classes of screens and dashboard widgets have both).
 */
trait BookingTimeTrait {

  /**
   * Local midnight of today + $days, as a timestamp.
   */
  protected function startOfDay(int $days = 0): int {
    $date = DrupalDateTime::createFromTimestamp($this->time->getRequestTime());
    $date->setTime(0, 0);
    if ($days) {
      $date->modify(sprintf('%+d days', $days));
    }
    return $date->getTimestamp();
  }

  /**
   * A timestamp moved by whole local days (daylight saving safe).
   */
  protected function daysLater(int $timestamp, int $days): int {
    $date = DrupalDateTime::createFromTimestamp($timestamp);
    $date->modify(sprintf('%+d days', $days));
    return $date->getTimestamp();
  }

  /**
   * The stored (UTC) value of a timestamp, to compare with datetime fields.
   */
  protected function storageValue(int $timestamp): string {
    return $this->dateFormatter->format($timestamp, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE);
  }

  /**
   * Timestamp of a booking's start or end (0 when not set).
   */
  protected function bookingTime(Booking $booking, string $field): int {
    $date = $booking->get($field)->date;
    return $date instanceof DrupalDateTime ? $date->getTimestamp() : 0;
  }

  /**
   * A timestamp in the user's timezone, e.g. "09:30" (default) or "Y-m-d".
   */
  protected function localTime(int $timestamp, string $format = 'H:i'): string {
    return $timestamp ? $this->dateFormatter->format($timestamp, 'custom', $format) : '';
  }

  /**
   * Whole local days from today to the day of a timestamp (negative: past).
   */
  protected function daysFromNow(int $timestamp): int {
    $day = DrupalDateTime::createFromTimestamp($timestamp);
    $day->setTime(0, 0);
    // Rounded: a daylight saving change makes a day 23 or 25 hours long.
    return (int) round(($day->getTimestamp() - $this->startOfDay()) / 86400);
  }

}
