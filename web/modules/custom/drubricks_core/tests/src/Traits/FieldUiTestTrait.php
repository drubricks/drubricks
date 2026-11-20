<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Traits;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Checks of an app's fields UI and default displays, for kernel tests.
 *
 * @code
 * $this->assertFieldUi('drubricks_examples_crm', 'crm_contact');
 * @endcode
 * The test installs the entity schema first and has a current user who may
 * create the records. Installing the module's config validates the displays
 * against the config schema.
 */
trait FieldUiTestTrait {

  /**
   * Field UI tabs, default displays as config, the form follows them.
   */
  protected function assertFieldUi(string $module, string $entity_type_id): void {
    $this->enableModules(['field', 'field_ui', 'text']);
    // System config: the date formats of datetime widgets.
    $this->installConfig(['system', $module]);
    $this->container->get('router.builder')->rebuild();

    // The admin page of the entity type carries the Field UI tabs.
    $type = $this->container->get('entity_type.manager')->getDefinition($entity_type_id);
    $base_route = "entity.$entity_type_id.settings";
    $this->assertSame($base_route, $type->get('field_ui_base_route'));
    $routes = $this->container->get('router.route_provider');
    $settings_path = $routes->getRouteByName($base_route)->getPath();
    $this->assertStringStartsWith('/admin/app/structure/', $settings_path);
    $this->assertSame("$settings_path/fields", $routes->getRouteByName("entity.$entity_type_id.field_ui_fields")->getPath());
    foreach (['entity_form_display', 'entity_view_display'] as $display) {
      $path = $routes->getRouteByName("entity.$display.$entity_type_id.default")->getPath();
      $this->assertStringStartsWith("$settings_path/", $path, "Manage $display tab");
    }

    // Default displays ship as config: every base field with display options
    // is a component, like the runtime defaults (what the UI showed before).
    $form_display = EntityFormDisplay::load("$entity_type_id.$entity_type_id.default");
    $view_display = EntityViewDisplay::load("$entity_type_id.$entity_type_id.default");
    $this->assertNotNull($form_display, "form display of $entity_type_id");
    $this->assertNotNull($view_display, "view display of $entity_type_id");
    $fields = $this->container->get('entity_field.manager')->getBaseFieldDefinitions($entity_type_id);
    $shown = static fn (?array $options) => $options && ($options['region'] ?? 'content') !== 'hidden';
    foreach ($fields as $name => $field) {
      if ($field->isDisplayConfigurable('form') && $shown($field->getDisplayOptions('form'))) {
        $this->assertNotNull($form_display->getComponent($name), "$name in the form display of $entity_type_id");
      }
      if ($field->isDisplayConfigurable('view') && $shown($field->getDisplayOptions('view'))) {
        $this->assertNotNull($view_display->getComponent($name), "$name in the view display of $entity_type_id");
      }
    }

    // A field added in the UI (storage + field + form component, as Field UI
    // saves them) shows up in the app's add form.
    FieldStorageConfig::create([
      'field_name' => 'field_test_notes',
      'entity_type' => $entity_type_id,
      'type' => 'string_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test_notes',
      'entity_type' => $entity_type_id,
      'bundle' => $entity_type_id,
      'label' => 'Notes',
    ])->save();
    $form_display->setComponent('field_test_notes', ['type' => 'string_textarea'])->save();
    $entity = $this->container->get('entity_type.manager')->getStorage($entity_type_id)->create([]);
    $form = $this->container->get('entity.form_builder')->getForm($entity, 'add');
    $this->assertArrayHasKey('field_test_notes', $form, "the new field in the $entity_type_id form");

    // A component hidden in Manage form display leaves the form.
    $others = array_diff_key($form_display->getComponents(), ['name' => TRUE, 'field_test_notes' => TRUE]);
    $hidden = array_key_first($others);
    $form_display->removeComponent($hidden)->save();
    $form = $this->container->get('entity.form_builder')->getForm($entity, 'add');
    $this->assertArrayNotHasKey($hidden, $form, "$hidden hidden in the $entity_type_id form");

    FieldStorageConfig::loadByName($entity_type_id, 'field_test_notes')->delete();
  }

}
