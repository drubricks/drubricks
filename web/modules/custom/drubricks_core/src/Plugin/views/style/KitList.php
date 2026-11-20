<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsStyle;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Renders rows as kit list rows (.row): title, meta, subtitle, text.
 *
 * Map fields of the view to the parts of a row in the style options.
 * Use it without "Use AJAX": the shell swaps the region with HTMX.
 */
#[ViewsStyle(
  id: 'drubricks_list',
  title: new TranslatableMarkup('Drubricks list (UI kit rows)'),
  help: new TranslatableMarkup('Rows of the UI kit list column.'),
  theme: 'drubricks_views_list',
  display_types: ['normal'],
)]
class KitList extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    foreach (['title', 'meta', 'subtitle', 'text', 'link'] as $part) {
      $options[$part] = ['default' => ''];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $fields = ['' => $this->t('- None -')] + $this->displayHandler->getFieldLabels(TRUE);
    $labels = [
      'title' => $this->t('Title field'),
      'meta' => $this->t('Meta field (right of the title)'),
      'subtitle' => $this->t('Subtitle field'),
      'text' => $this->t('Text field (two lines)'),
      'link' => $this->t('Link field (URL of the row, e.g. "Link to entity" as URL)'),
    ];
    foreach ($labels as $part => $label) {
      $form[$part] = [
        '#type' => 'select',
        '#title' => $label,
        '#options' => $fields,
        '#default_value' => $this->options[$part],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $rows = [];
    foreach ($this->view->result as $index => $row) {
      $item = [];
      foreach (['title', 'meta', 'subtitle', 'text', 'link'] as $part) {
        $field = $this->options[$part];
        $item[$part] = $field ? trim(strip_tags((string) $this->getField($index, $field))) : NULL;
      }
      $rows[] = $item;
    }
    return [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => $rows,
    ];
  }

}
