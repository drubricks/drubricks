<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Datetime;

/**
 * Short day labels for dates in screens and widgets.
 *
 * Needs $this->dateFormatter and $this->t().
 */
trait RelativeDayTrait {

  /**
   * Today / Tomorrow / Yesterday or a short date, for 'Y-m-d…' values.
   */
  protected function dayLabel(string $date, string $format = 'j M'): string {
    $today = $this->today();
    $day = new \DateTimeImmutable(substr($date, 0, 10), $today->getTimezone());
    $diff = (int) $today->diff($day)->format('%r%a');
    return match ($diff) {
      0 => (string) $this->t('Today'),
      1 => (string) $this->t('Tomorrow'),
      -1 => (string) $this->t('Yesterday'),
      default => $this->dateFormatter->format($day->getTimestamp(), 'custom', $format),
    };
  }

  /**
   * Whether a 'Y-m-d…' value is before today.
   */
  protected function isPast(string $date): bool {
    return substr($date, 0, 10) < $this->today()->format('Y-m-d');
  }

  /**
   * Seconds until the next midnight (cache max-age of "today" content).
   */
  protected function untilMidnight(): int {
    $now = $this->time->getRequestTime();
    return max(60, $this->today()->modify('tomorrow')->getTimestamp() - $now);
  }

  /**
   * Today at midnight in the user's timezone, from the request time.
   */
  protected function today(): \DateTimeImmutable {
    $timezone = new \DateTimeZone(date_default_timezone_get());
    return (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->setTimezone($timezone)->setTime(0, 0);
  }

}
