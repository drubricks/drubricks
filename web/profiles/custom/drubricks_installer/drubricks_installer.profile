<?php

/**
 * @file
 * Install tasks for the Drubricks installer profile.
 *
 * Steps after the core ones (language, database, site name and account):
 * 1. apply the drubricks_base recipe (batch),
 * 2. "Choose applications": App recipes found in recipes/ (each with its
 *    demo data, recipes/<app>_demo, when there is one),
 * 3. apply the chosen recipes (batch with a progress bar),
 * 4. import interface translations when an App recipe added a language (core
 *    does it itself only for a site installed in another language).
 * The profile uninstalls itself afterwards (keep_profile: false), which also
 * rebuilds the router (core's install_finished()).
 */

declare(strict_types=1);

use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\drubricks_installer\Form\AppsForm;

/**
 * Implements hook_install_tasks().
 */
function drubricks_installer_install_tasks(array &$install_state): array {
  return [
    'drubricks_installer_apply_base' => [
      'display_name' => t('Install Drubricks'),
      'type' => 'batch',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
    ],
    'drubricks_installer_apps' => [
      'display_name' => t('Choose applications'),
      'type' => 'form',
      'function' => AppsForm::class,
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
    ],
    'drubricks_installer_apply_apps' => [
      'display_name' => t('Install applications'),
      'type' => 'batch',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
    ],
    'drubricks_installer_import_translations' => [
      'display_name' => t('Import translations'),
      'display' => FALSE,
      'type' => 'batch',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
    ],
  ];
}

/**
 * Applies the drubricks_base recipe as a batch.
 */
function drubricks_installer_apply_base(array &$install_state): array {
  $recipe = Recipe::createFromDirectory(drubricks_installer_recipes_dir() . '/drubricks_base');
  return [
    'operations' => RecipeRunner::toBatchOperations($recipe),
    'title' => t('Installing Drubricks'),
    'error_message' => t('Drubricks installation failed.'),
  ];
}

/**
 * Applies the chosen App recipes and their Demo recipes as one batch.
 */
function drubricks_installer_apply_apps(array &$install_state): array {
  $operations = [];
  foreach ($install_state['drubricks_recipes'] ?? [] as $path) {
    $operations = array_merge($operations, RecipeRunner::toBatchOperations(Recipe::createFromDirectory($path)));
  }
  return [
    'operations' => $operations,
    'title' => t('Installing applications'),
    'progress_message' => t('Step @current of @total.'),
    'error_message' => t('Installing the applications failed.'),
  ];
}

/**
 * Imports translations for the languages the App recipes added.
 *
 * A recipe can add a language (e.g. Polish as the default) but locale imports
 * translations only when a module is installed into a site that already has
 * the language, outside the installer, or when the site is installed in that
 * language (core's install_finish_translations() task). The apps are
 * installed during the installation, in English, so this task runs core's
 * own install_finish_translations(): translations of every project (core,
 * contrib from localize.drupal.org, custom modules with an "interface
 * translation server pattern" from their translations/ folder) and the
 * translated configuration.
 */
function drubricks_installer_import_translations(array &$install_state): array {
  $languages = \Drupal::languageManager()->getLanguages();
  $english_only = count($languages) === 1 && isset($languages['en']);
  // Installed in another language: core's own task does the same.
  $core_does_it = ($install_state['parameters']['langcode'] ?? 'en') !== 'en';
  if (!\Drupal::moduleHandler()->moduleExists('locale') || $english_only || $core_does_it) {
    return [];
  }
  return install_finish_translations($install_state);
}

/**
 * Returns the absolute path of the project recipes directory.
 *
 * <project>/recipes, the installer path of "drupal-recipe" packages in core's
 * drupal/recommended-project (composer.json installer-paths); core resolves
 * recipes by directory too (RecipeConfigurator::getIncludedRecipe()).
 */
function drubricks_installer_recipes_dir(): string {
  return dirname(DRUPAL_ROOT) . '/recipes';
}
