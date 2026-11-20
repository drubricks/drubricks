<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteTagsWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Several references as a list (one per row) with an "Add" search field.
 *
 * Built on core's tags-style autocomplete: the form value, validation (only
 * records the user may reference — the selection handler runs an
 * access-checked query) and saving stay core's. js/reference-list.js hides
 * the comma-separated input and shows the chosen records one under another,
 * each with a remove button, plus a search field with suggestions (arrow
 * keys, Enter, Escape). Without JS the tags input still works.
 */
#[FieldWidget(
  id: 'drubricks_reference_list',
  label: new TranslatableMarkup('List with search (Drubricks)'),
  description: new TranslatableMarkup('Chosen records one under another, with a search field to add more.'),
  field_types: ['entity_reference'],
  multiple_values: TRUE,
)]
class ReferenceListWidget extends EntityReferenceAutocompleteTagsWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $target_type = $this->getFieldSetting('target_type');
    $element['target_id']['#attributes']['data-drubricks-reference-list'] = $target_type;
    $element['target_id']['#attributes']['data-add-label'] = $target_type === 'user'
      ? (string) $this->t('Add a person…')
      : (string) $this->t('Add…');
    $element['target_id']['#attributes']['data-empty-label'] = (string) $this->t('Nobody yet.');
    $element['target_id']['#attached']['library'][] = 'drubricks_core/reference-list';
    return $element;
  }

}
