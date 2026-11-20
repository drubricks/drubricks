<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * The contact of a deal works at the deal's company.
 */
#[Constraint(
  id: 'CrmDealContactCompany',
  label: new TranslatableMarkup('Deal contact works at the deal company', [], ['context' => 'Validation']),
  type: 'entity:crm_deal',
)]
final class DealContactCompanyConstraint extends SymfonyConstraint {

  /**
   * Message when the contact works elsewhere.
   */
  public string $otherCompany = '%contact does not work at %company.';

}
