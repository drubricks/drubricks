<?php

declare(strict_types=1);

namespace Drupal\drubricks_installer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drubricks_installer\AppRecipes;

/**
 * Installer step "Choose applications".
 *
 * Every App recipe in recipes/ is an option; example apps are unchecked by
 * default. An app comes with its demo data: when recipes/<app>_demo exists
 * it is applied together with the app. Without a browser:
 * @code
 * drush site:install drubricks_installer \
 *   drubricks_installer_apps.apps=drubricks_examples_crm,drubricks_examples_project_management
 *   drubricks_installer_apps.apps=all   (every App recipe)
 * @endcode
 */
final class AppsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drubricks_installer_apps';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array &$install_state = NULL): array {
    $apps = AppRecipes::all(drubricks_installer_recipes_dir());
    $interactive = $install_state['interactive'] ?? TRUE;
    $form['#title'] = $this->t('Choose applications');

    if (!$interactive) {
      // Drush site:install passes "a,b" strings; checkboxes would reject them.
      $form['apps'] = ['#type' => 'textfield', '#default_value' => ''];
    }
    else {
      $form['intro'] = [
        '#markup' => '<p>' . $this->t('Pick the applications to install. You can add more later with <code>drush recipe recipes/NAME</code>.') . '</p>',
      ];
      $options = [];
      $defaults = [];
      foreach ($apps as $id => $app) {
        $options[$id] = $app['name'] . ($app['example'] ? ' (' . $this->t('example') . ')' : '');
        if (!$app['example']) {
          $defaults[] = $id;
        }
      }
      $form['apps'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Applications'),
        '#options' => $options,
        '#default_value' => $defaults,
      ];
      foreach ($apps as $id => $app) {
        $form['apps'][$id]['#description'] = $app['demo']
          ? $this->t('@description Comes with demo data (dates moved to today).', ['@description' => $app['description']])
          : $app['description'];
      }
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and continue'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $known = array_keys(AppRecipes::all(drubricks_installer_recipes_dir()));
    $unknown = array_diff($this->selected($form_state), $known);
    if ($unknown) {
      $form_state->setErrorByName('apps', $this->t('Unknown application recipes: @list.', ['@list' => implode(', ', $unknown)]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $apps = AppRecipes::all(drubricks_installer_recipes_dir());
    $recipes = [];
    foreach ($this->selected($form_state) as $id) {
      $recipes[] = $apps[$id]['path'];
      if ($apps[$id]['demo']) {
        $recipes[] = $apps[$id]['demo'];
      }
    }
    $GLOBALS['install_state']['drubricks_recipes'] = $recipes;
  }

  /**
   * Chosen recipe ids from checkboxes or a comma separated string.
   *
   * @return string[]
   *   Recipe machine names.
   */
  private function selected(FormStateInterface $form_state): array {
    $value = $form_state->getValue('apps');
    // "all": every App recipe (drush / CI without listing them).
    if ($value === 'all') {
      return array_keys(AppRecipes::all(drubricks_installer_recipes_dir()));
    }
    if (is_string($value)) {
      return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
    return array_values(array_filter((array) $value));
  }

}
