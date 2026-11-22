<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_examples_booking\Kernel;

use Drupal\Core\DefaultContent\Existing;
use Drupal\Core\DefaultContent\Finder;
use Drupal\Core\DefaultContent\Importer;
use Drupal\drubricks_examples_booking\Entity\Booking;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The booking demo content imports on a site in any timezone.
 *
 * The content was exported on a site in Europe/Warsaw; drubricks_core keeps
 * the wall-clock times, so every booking stays within opening hours (the
 * entity validation of the import) and moves to the current week.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class DemoContentTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'options',
    'datetime',
    'charts',
    'charts_chartjs',
    'drubricks_core',
    'drubricks_examples_booking',
  ];

  /**
   * Timezones of the site the demo is installed on.
   *
   * @return array<string, array{string}>
   *   Timezone names.
   */
  public static function timezones(): array {
    return [
      'export timezone' => ['Europe/Warsaw'],
      'UTC (CI)' => ['UTC'],
      'west' => ['America/New_York'],
      'east' => ['Asia/Tokyo'],
    ];
  }

  /**
   * Imports the demo content and checks the times of a known booking.
   */
  #[DataProvider('timezones')]
  public function testImport(string $timezone): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('booking_resource');
    $this->installEntitySchema('booking');
    $this->installConfig(['system']);
    $this->config('system.date')->set('timezone.default', $timezone)->save();
    date_default_timezone_set($timezone);
    // The importer runs as the administrator (user 1).
    $this->setUpCurrentUser(['uid' => 1]);

    $dir = $this->root . '/../recipes/drubricks_examples_booking_demo/content';
    $this->container->get(Importer::class)->importContent(new Finder($dir), Existing::Skip);

    $files = glob($dir . '/booking/*.yml') ?: [];
    $this->assertCount(count($files), Booking::loadMultiple());

    // "Back pain follow-up": 12:00–12:45 in the export (the physiotherapist's
    // first hour), still 12:00–12:45 here, in the current week.
    $booking = $this->container->get('entity.repository')->loadEntityByUuid('booking', 'd968c367-9c0c-5e49-8078-efabfc09a6c2');
    $this->assertInstanceOf(Booking::class, $booking);
    $local = new \DateTimeZone($timezone);
    $starts = $booking->get('starts')->date->setTimezone($local);
    $ends = $booking->get('ends')->date->setTimezone($local);
    $this->assertSame('12:00', $starts->format('H:i'));
    $this->assertSame('12:45', $ends->format('H:i'));
    // Moved by whole weeks: still a Wednesday, within a week of today.
    $this->assertSame('Wed', $starts->format('D'));
    $today = new \DateTimeImmutable('@' . $this->container->get('datetime.time')->getRequestTime());
    $this->assertLessThanOrEqual(7 * 86400, abs($starts->getTimestamp() - $today->getTimestamp()));
  }

}
