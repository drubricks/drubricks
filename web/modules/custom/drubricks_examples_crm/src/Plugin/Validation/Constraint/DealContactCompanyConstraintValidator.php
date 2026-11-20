<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Plugin\Validation\Constraint;

use Drupal\drubricks_examples_crm\Entity\Contact;
use Drupal\drubricks_examples_crm\Entity\Deal;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the CrmDealContactCompany constraint.
 */
final class DealContactCompanyConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof Deal || !$constraint instanceof DealContactCompanyConstraint) {
      return;
    }
    $contact = $value->get('contact')->entity;
    $company = $value->get('company')->target_id;
    if (!$contact instanceof Contact || !$company || (string) $contact->get('company')->target_id === (string) $company) {
      return;
    }
    $this->context->buildViolation($constraint->otherCompany)
      ->setParameter('%contact', (string) $contact->label())
      ->setParameter('%company', (string) $value->get('company')->entity?->label())
      ->atPath('contact')
      ->addViolation();
  }

}
