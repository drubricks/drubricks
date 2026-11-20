<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DefaultContent\PreEntityImportEvent;
use Drupal\Core\DefaultContent\PreExportEvent;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Recipe\Recipe;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Moves the dates of demo content to the install day, in the site's timezone.
 *
 * A Demo recipe declares the day its content was exported and the timezone
 * of the site it was exported from:
 * @code
 * extra:
 *   drubricks_core:
 *     demo_reference_date: '2026-11-13'
 *     demo_timezone: Europe/Warsaw
 *     demo_shift: weeks
 * @endcode
 * Before each entity of the recipe's content/ directory is created (and
 * validated), every date / datetime value is moved by (today − reference
 * date), so calendars, due dates and "last contacted" look current. Times
 * keep their wall-clock value: a 10:00 booking exported in Warsaw is 10:00 on
 * a site in UTC or New York too (rules like opening hours stay satisfied, and
 * daylight saving changes do not move it). With demo_shift: weeks the move is
 * rounded to whole weeks, so weekdays stay the same.
 *
 * Export (core's `drush content:export`): users are written without their
 * language preferences and timezone, so the content imports into a site in
 * any language (an en-gb site has no "en") and people see times in the
 * site's timezone.
 */
final class DemoContentSubscriber implements EventSubscriberInterface {

  /**
   * Demo settings per recipe directory (NULL: not a demo recipe).
   *
   * @var array<string, array{days: int, from: \DateTimeZone}|null>
   */
  private array $recipes = [];

  public function __construct(
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreEntityImportEvent::class => 'onPreEntityImport',
      PreExportEvent::class => 'onPreExport',
    ];
  }

  /**
   * Leaves site-specific user settings out of exported content.
   */
  public function onPreExport(PreExportEvent $event): void {
    if ($event->entity->getEntityTypeId() === 'user') {
      foreach (['preferred_langcode', 'preferred_admin_langcode', 'timezone'] as $field) {
        $event->setExportable($field, FALSE);
      }
    }
  }

  /**
   * Moves the dates of an entity about to be imported from a demo recipe.
   */
  public function onPreEntityImport(PreEntityImportEvent $event): void {
    $path = $event->metadata['path'] ?? NULL;
    $settings = is_string($path) ? $this->settings($path) : NULL;
    if ($settings === NULL) {
      return;
    }
    $type = (string) $event->metadata['entity_type'];
    $definitions = $this->entityFieldManager->getFieldDefinitions($type, (string) ($event->metadata['bundle'] ?? $type));
    $dates = array_keys(array_filter(
      $definitions,
      fn (FieldDefinitionInterface $definition): bool => in_array($definition->getType(), ['datetime', 'daterange'], TRUE),
    ));
    if (!$dates) {
      return;
    }
    $to = new \DateTimeZone(date_default_timezone_get());
    if (isset($event->data['default']) && is_array($event->data['default'])) {
      $event->data['default'] = $this->moveFields($event->data['default'], $dates, $settings['days'], $settings['from'], $to);
    }
    foreach ($event->data['translations'] ?? [] as $langcode => $values) {
      if (is_array($values)) {
        $event->data['translations'][$langcode] = $this->moveFields($values, $dates, $settings['days'], $settings['from'], $to);
      }
    }
  }

  /**
   * Moves a date or a datetime (storage format, UTC) by a number of days.
   *
   * A datetime keeps its wall-clock time: it is read in $from and written as
   * the same local time in $to.
   *
   * @param string $value
   *   'Y-m-d' or 'Y-m-d\TH:i:s' (UTC).
   * @param int $days
   *   Days to move by.
   * @param \DateTimeZone $from
   *   Timezone of the site the content was exported from.
   * @param \DateTimeZone $to
   *   Timezone of this site.
   *
   * @return string
   *   The moved value in the same format.
   */
  public static function move(string $value, int $days, \DateTimeZone $from, \DateTimeZone $to): string {
    $modify = sprintf('%+d days', $days);
    if (strlen($value) === 10) {
      return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->modify($modify)->format('Y-m-d');
    }
    $utc = new \DateTimeZone('UTC');
    $wall = (new \DateTimeImmutable($value, $utc))->setTimezone($from)->modify($modify)->format('Y-m-d H:i:s');
    return (new \DateTimeImmutable($wall, $to))->setTimezone($utc)->format('Y-m-d\TH:i:s');
  }

  /**
   * Moves the date fields of one set of entity values.
   *
   * @param array<mixed> $values
   *   Field name => items, as in the content file.
   * @param string[] $fields
   *   Names of the date fields.
   * @param int $days
   *   Days to move by.
   * @param \DateTimeZone $from
   *   Export timezone.
   * @param \DateTimeZone $to
   *   Site timezone.
   *
   * @return array<mixed>
   *   The values with moved dates.
   */
  private function moveFields(array $values, array $fields, int $days, \DateTimeZone $from, \DateTimeZone $to): array {
    foreach ($fields as $field) {
      foreach ($values[$field] ?? [] as $delta => $item) {
        foreach (['value', 'end_value'] as $property) {
          if (is_array($item) && !empty($item[$property]) && is_string($item[$property])) {
            $values[$field][$delta][$property] = self::move($item[$property], $days, $from, $to);
          }
        }
      }
    }
    return $values;
  }

  /**
   * The demo settings of the recipe a content file belongs to.
   *
   * @return array{days: int, from: \DateTimeZone}|null
   *   Days to move by and the export timezone, NULL when the file is not
   *   demo content (or nothing has to move).
   */
  private function settings(string $path): ?array {
    // <recipe>/content/<entity type>/<uuid>.yml: look up to recipe.yml.
    $dir = dirname($path);
    for ($i = 0; $i < 4 && !is_file($dir . '/recipe.yml'); $i++) {
      $dir = dirname($dir);
    }
    if (array_key_exists($dir, $this->recipes)) {
      return $this->recipes[$dir];
    }
    $this->recipes[$dir] = NULL;
    if (!is_file($dir . '/recipe.yml')) {
      return NULL;
    }
    $extra = Recipe::createFromDirectory($dir)->getExtra('drubricks_core') ?? [];
    if (empty($extra['demo_reference_date'])) {
      return NULL;
    }
    $site = new \DateTimeZone(date_default_timezone_get());
    $from = new \DateTimeZone($extra['demo_timezone'] ?? $site->getName());
    // Both days at midnight: a whole number of days.
    $today = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->setTimezone($site)->setTime(0, 0);
    $reference = new \DateTimeImmutable($extra['demo_reference_date'], $site);
    $days = (int) $reference->diff($today)->format('%r%a');
    // 'demo_shift: weeks' keeps weekdays (e.g. bookings stay Monday–Friday).
    if (($extra['demo_shift'] ?? 'days') === 'weeks') {
      $days = (int) (round($days / 7) * 7);
    }
    if ($days === 0 && $from->getName() === $site->getName()) {
      return NULL;
    }
    return $this->recipes[$dir] = ['days' => $days, 'from' => $from];
  }

}
