<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_examples_booking\Kernel;

use Drupal\drubricks_examples_booking\Entity\Booking;
use Drupal\drubricks_examples_booking\Entity\Resource;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\drubricks_core\Traits\AdminViewTestTrait;
use Drupal\Tests\drubricks_core\Traits\FieldUiTestTrait;
use Drupal\Tests\drubricks_core\Traits\RevisionsTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Booking rules: order, same day, opening hours, no double booking.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class BookingTest extends KernelTestBase {

  use AdminViewTestTrait;
  use FieldUiTestTrait;
  use RevisionsTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'inline_form_errors',
    'options',
    'datetime',
    'drubricks_core',
    'drubricks_examples_booking',
  ];

  /**
   * The room under test.
   */
  private Resource $room;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('booking_resource');
    $this->installEntitySchema('booking');
    $this->installConfig(['system']);
    $this->config('system.date')->set('timezone.default', 'UTC')->save();
    date_default_timezone_set('UTC');
    $this->createUser();
    // References are validated against what the current user may see.
    $this->setUpCurrentUser([], ['administer booking']);
    $this->room = Resource::create(['name' => 'Fjord', 'opens' => 8, 'closes' => 18]);
    $this->room->save();
  }

  /**
   * Builds a booking of the room (UTC times).
   */
  private function booking(string $starts, string $ends, string $state = 'confirmed'): Booking {
    return Booking::create([
      'name' => 'Meeting',
      'resource' => $this->room->id(),
      'starts' => $starts,
      'ends' => $ends,
      'state' => $state,
    ]);
  }

  /**
   * Returns the messages of a booking's violations.
   */
  private function messages(Booking $booking): string {
    $messages = [];
    foreach ($booking->validate() as $violation) {
      $messages[] = strip_tags((string) $violation->getMessage());
    }
    return implode(' | ', $messages);
  }

  /**
   * End after start, same day, opening hours.
   */
  public function testTimes(): void {
    $this->assertSame('', $this->messages($this->booking('2026-11-16T09:00:00', '2026-11-16T10:00:00')));
    $this->assertStringContainsString('end after it starts', $this->messages($this->booking('2026-11-16T10:00:00', '2026-11-16T09:00:00')));
    $this->assertStringContainsString('same day', $this->messages($this->booking('2026-11-16T17:00:00', '2026-11-17T09:00:00')));
    $this->assertStringContainsString('between 8:00 and 18:00', $this->messages($this->booking('2026-11-16T07:00:00', '2026-11-16T09:00:00')));
    $this->assertStringContainsString('between 8:00 and 18:00', $this->messages($this->booking('2026-11-16T17:00:00', '2026-11-16T18:30:00')));
  }

  /**
   * Overlaps are refused; touching slots and cancelled bookings are fine.
   */
  public function testNoDoubleBooking(): void {
    $this->booking('2026-11-16T10:00:00', '2026-11-16T11:00:00')->save();
    $this->assertStringContainsString('already booked 10:00–11:00', $this->messages($this->booking('2026-11-16T10:30:00', '2026-11-16T11:30:00')));
    $this->assertSame('', $this->messages($this->booking('2026-11-16T11:00:00', '2026-11-16T12:00:00')));
    $this->assertSame('', $this->messages($this->booking('2026-11-16T10:30:00', '2026-11-16T11:30:00', 'cancelled')));

    // Editing a booking does not conflict with itself.
    $existing = Booking::load(1);
    $existing->set('ends', '2026-11-16T11:30:00');
    $this->assertSame('', $this->messages($existing));
  }

  /**
   * Every change of a resource or booking is kept as a revision.
   */
  public function testRevisions(): void {
    $this->assertRevisionable('booking_resource');
    $this->assertRevisionable('booking');
    $editor = $this->createUser(['edit any booking']);
    $this->assertEditCreatesRevision($this->booking('2026-11-16T09:00:00', '2026-11-16T10:00:00'), 'ends', '2026-11-16T11:00:00', $editor);
  }

  /**
   * The admin views of resources and bookings.
   */
  public function testAdminViews(): void {
    $this->setUpCurrentUser([], ['administer booking']);
    $this->booking('2026-11-16T09:00:00', '2026-11-16T10:00:00')->save();
    $this->assertAdminView('drubricks_examples_booking', 'booking_resource');
    $this->assertAdminView('drubricks_examples_booking', 'booking');
  }

  /**
   * Manage fields / form display / display, and the default displays.
   */
  public function testFieldUi(): void {
    $this->setUpCurrentUser([], ['administer booking']);
    $this->assertFieldUi('drubricks_examples_booking', 'booking_resource');
    $this->assertFieldUi('drubricks_examples_booking', 'booking');
  }

}
