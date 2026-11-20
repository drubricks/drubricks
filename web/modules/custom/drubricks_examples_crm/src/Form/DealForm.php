<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\drubricks_core\Form\DrubricksEntityForm;

/**
 * Deal form: the Contact list depends on the chosen Company.
 *
 * Pattern for dependent fields: the trigger posts the form with HTMX and only
 * the dependent field's wrapper is swapped (core Htmx API, no custom JS).
 * The rule itself ("the contact works at the company") is the entity
 * constraint CrmDealContactCompany, so board moves and the API keep it too;
 * the form only offers the matching contacts.
 */
final class DealForm extends DrubricksEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    if (!isset($form['company']['widget'], $form['contact']['widget'])) {
      return $form;
    }

    // Changing the company re-renders only the contact field.
    $form['contact']['#attributes']['id'] = 'deal-contact-wrapper';
    // Only the company is validated when it changes (its value stays in the
    // form state); the other fields are checked on save.
    $form['company']['widget']['#limit_validation_errors'] = [['company']];
    (new Htmx())
      ->post($this->formUrl())
      ->onlyMainContent()
      ->select('#deal-contact-wrapper')
      ->target('#deal-contact-wrapper')
      ->swap('outerHTML')
      ->trigger('change')
      ->applyTo($form['company']['widget']);

    // The chosen company: the options widget's value ([delta => id] before
    // the widget massages it into items), else the deal's.
    $value = $form_state->getValue(['company', 0]);
    $company = (is_array($value) ? ($value['target_id'] ?? NULL) : $value) ?? $this->entity->get('company')->target_id;
    if ($company && $company !== '_none') {
      $contacts = $this->entityTypeManager->getStorage('crm_contact')->getQuery()
        ->accessCheck(TRUE)
        ->condition('company', $company)
        ->execute();
      $widget = &$form['contact']['widget'];
      $widget['#options'] = array_intersect_key($widget['#options'], ['_none' => TRUE] + $contacts);
      if (count($widget['#options']) <= 1) {
        $widget['#description'] = $this->t('This company has no contacts yet.');
      }
    }
    return $form;
  }

}
