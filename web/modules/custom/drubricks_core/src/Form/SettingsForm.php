<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Drubricks settings: locale and currency passed to UI.init(), user edit.
 *
 * Values are saved through #config_target (ConfigFormBase); changing
 * user_edit_in_app_theme rebuilds the router (UserRoutesSubscriber).
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drubricks_core_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['drubricks_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['locale'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Locale'),
      '#description' => $this->t('BCP 47 tag for dates and numbers, e.g. pl-PL. Empty: the current language.'),
      '#config_target' => new ConfigTarget(
        'drubricks_core.settings',
        'locale',
        toConfig: fn (?string $value) => trim((string) $value),
      ),
    ];
    $form['currency'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Currency'),
      '#description' => $this->t('ISO 4217 code, e.g. EUR or PLN.'),
      '#size' => 3,
      '#maxlength' => 3,
      '#config_target' => new ConfigTarget(
        'drubricks_core.settings',
        'currency',
        toConfig: fn (?string $value) => strtoupper(trim((string) $value)),
      ),
    ];
    $form['user_edit_in_app_theme'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Edit the user profile in the app theme'),
      '#description' => $this->t('Drupal core shows /user/ID/edit in the administration theme.'),
      '#config_target' => 'drubricks_core.settings:user_edit_in_app_theme',
    ];
    return parent::buildForm($form, $form_state);
  }

}
