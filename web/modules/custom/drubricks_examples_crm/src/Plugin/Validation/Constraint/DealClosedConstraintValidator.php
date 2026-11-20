<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Plugin\Validation\Constraint;

use Drupal\drubricks_examples_crm\Entity\Deal;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the CrmDealClosed constraint.
 */
final class DealClosedConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof Deal || !$constraint instanceof DealClosedConstraint || !$value->isClosed()) {
      return;
    }
    if ($value->get('close_date')->isEmpty()) {
      $this->context->buildViolation($constraint->missingDate)->atPath('close_date')->addViolation();
    }
    if ($value->getStage() === 'won' && (float) $value->get('value')->value <= 0) {
      $this->context->buildViolation($constraint->missingValue)->atPath('value')->addViolation();
    }
  }

}
