<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A won or lost deal needs a close date and (when won) a value.
 */
#[Constraint(
  id: 'CrmDealClosed',
  label: new TranslatableMarkup('Closed deal has a close date', [], ['context' => 'Validation']),
  type: 'entity:crm_deal',
)]
final class DealClosedConstraint extends SymfonyConstraint {

  /**
   * Message when the close date is missing.
   */
  public string $missingDate = 'A won or lost deal needs a close date.';

  /**
   * Message when a won deal has no value.
   */
  public string $missingValue = 'A won deal needs a value.';

}
