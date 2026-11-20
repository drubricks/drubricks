<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_booking\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\drubricks_examples_booking\Entity\Booking;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates BookingSlot: order, same day, opening hours, no double booking.
 */
final class BookingSlotConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof Booking || !$constraint instanceof BookingSlotConstraint) {
      return;
    }
    $starts = $value->get('starts')->value;
    $ends = $value->get('ends')->value;
    $resource = $value->getResource();
    if (!$starts || !$ends || !$resource) {
      // Required-field validation reports these.
      return;
    }
    if ($ends <= $starts) {
      $this->context->buildViolation($constraint->order)->atPath('ends')->addViolation();
      return;
    }

    // Opening hours are local time (the site's / user's timezone).
    $local = new \DateTimeZone(date_default_timezone_get());
    $utc = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $start = (new \DateTimeImmutable($starts, $utc))->setTimezone($local);
    $end = (new \DateTimeImmutable($ends, $utc))->setTimezone($local);
    if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
      $this->context->buildViolation($constraint->sameDay)->atPath('ends')->addViolation();
      return;
    }
    $from = (int) $start->format('G') + (int) $start->format('i') / 60;
    $to = (int) $end->format('G') + (int) $end->format('i') / 60;
    if ($from < $resource->opens() || $to > $resource->closes()) {
      $this->context->buildViolation($constraint->hours)
        ->setParameter('%resource', (string) $resource->label())
        ->setParameter('@opens', (string) $resource->opens())
        ->setParameter('@closes', (string) $resource->closes())
        ->atPath('starts')
        ->addViolation();
      return;
    }

    if (!$value->isActive()) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('booking');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('resource', $resource->id())
      ->condition('state', 'cancelled', '<>')
      ->condition('starts', $ends, '<')
      ->condition('ends', $starts, '>')
      ->range(0, 1);
    if (!$value->isNew()) {
      $query->condition('id', $value->id(), '<>');
    }
    $ids = $query->execute();
    if ($ids) {
      $other = $storage->load(reset($ids));
      if (!$other instanceof Booking) {
        return;
      }
      $other_start = (new \DateTimeImmutable($other->get('starts')->value, $utc))->setTimezone($local);
      $other_end = (new \DateTimeImmutable($other->get('ends')->value, $utc))->setTimezone($local);
      $this->context->buildViolation($constraint->overlap)
        ->setParameter('%resource', (string) $resource->label())
        ->setParameter('@time', $other_start->format('H:i') . '–' . $other_end->format('H:i'))
        ->setParameter('%title', (string) $other->label())
        ->atPath('starts')
        ->addViolation();
    }
  }

}
