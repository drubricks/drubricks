<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\BulkForm;

/**
 * Bulk form that offers only what the user may do.
 *
 * Core's bulk form puts a checkbox on every row and lists every configured
 * action; a forbidden one is refused only after submit ("No access to
 * execute …"). This one asks the same question up front — the action
 * plugin's access() for the row's entity and the viewing user: a row gets a
 * checkbox when an action may run on it, the action list keeps the actions
 * allowed on at least one row, and when no row allows any action the column
 * and the action bar are left out (a read-only user sees a plain table).
 * Submit keeps core's check. Used for Drubricks entities by
 * TableHooks::viewsDataAlter() instead of core's "bulk_form".
 */
#[ViewsField('drubricks_bulk_form')]
class DrubricksBulkForm extends BulkForm {

  /**
   * Action ids the user may run, keyed by result row index.
   *
   * @var array<int, string[]>
   */
  protected array $allowed = [];

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    parent::preRender($values);
    $this->allowed = [];
    $account = $this->view->getUser();
    $actions = array_intersect_key($this->actions, $this->getBulkOptions());
    foreach ($values as $index => $row) {
      $entity = $this->getEntity($row);
      if ($entity === NULL) {
        continue;
      }
      $entity = $this->getEntityTranslationByRelationship($entity, $row);
      foreach ($actions as $id => $action) {
        if ($action->getPlugin()->access($entity, $account)) {
          $this->allowed[$index][] = $id;
        }
      }
    }
    if (!$this->allowed) {
      // Nothing to select: no column (and no "select all" in its header).
      $this->options['exclude'] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewsForm(&$form, FormStateInterface $form_state) {
    parent::viewsForm($form, $form_state);
    if (empty($this->view->result)) {
      return;
    }
    $id = $this->options['id'];
    if (!$this->allowed) {
      unset($form[$id], $form['header'][$id], $form['actions']);
      if (empty($form['header']) || !array_filter(array_keys($form['header']), static fn ($key) => !str_starts_with((string) $key, '#'))) {
        unset($form['header']);
      }
      return;
    }
    foreach (array_keys($this->view->result) as $index) {
      if (empty($this->allowed[$index])) {
        // Like core's row without an entity: an empty cell.
        $form[$id][$index] = [];
      }
    }
    $allowed = array_merge(...array_values($this->allowed));
    $form['header'][$id]['action']['#options'] = array_intersect_key($form['header'][$id]['action']['#options'], array_flip($allowed));
  }

}
