<?php

declare(strict_types=1);

namespace Drupal\drubricks_design_system\Controller;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Render\Markup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\drubricks_design_system\Form\DemoForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The design system page: every SDC component of the theme, with examples.
 *
 * Sections and their examples are listed in sections.yml (groups and
 * order). Each example is a Twig file in
 * templates/examples/: the page renders it and shows its source, so the
 * snippet a developer copies is exactly the code that produced the preview.
 */
final class DesignSystemController extends ControllerBase {

  /**
   * Provider (theme) whose components are documented.
   */
  private const PROVIDER = 'drubricks';

  public function __construct(
    private readonly ModuleExtensionList $modules,
    private readonly ComponentPluginManager $components,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('extension.list.module'),
      $container->get('plugin.manager.sdc'),
    );
  }

  /**
   * Builds the page.
   */
  public function page(): array {
    $path = $this->modules->getPath('drubricks_design_system');
    $definition = Yaml::decode((string) file_get_contents($path . '/sections.yml')) ?: [];

    $components = $this->componentIndex();
    $groups = [];
    foreach ($definition as $id => $section) {
      $examples = [];
      foreach ($section['examples'] ?? [] as $i => $example) {
        $file = $path . '/templates/examples/' . $example['file'] . '.twig';
        $source = is_file($file) ? trim((string) file_get_contents($file)) : '';
        $examples[] = [
          'id' => $id . '-' . ($i + 1),
          'title' => Markup::create(Xss::filterAdmin($example['title'] ?? '')),
          'template' => '@drubricks_design_system/examples/' . $example['file'] . '.twig',
          'source' => ($example['source'] ?? TRUE) ? $source : '',
          'render' => $example['render'] ?? TRUE,
          'block' => $example['block'] ?? FALSE,
          'wrap' => $example['wrap'] ?? '',
          'preview' => $example['preview'] ?? '',
          'lang' => $example['lang'] ?? 'twig',
        ];
      }
      $used = [];
      foreach ($section['components'] ?? [] as $name) {
        $component_id = self::PROVIDER . ':' . $name;
        if (isset($components[$component_id])) {
          $components[$component_id]['sections'][] = ['id' => $id, 'title' => $section['title']];
          $used[] = ['id' => $component_id, 'name' => $components[$component_id]['name']];
        }
      }
      $group = $section['group'] ?? 'Components';
      $groups[$group]['title'] = $group;
      $groups[$group]['sections'][] = [
        'id' => $id,
        'title' => $section['title'],
        'icon' => $section['icon'] ?? 'circle',
        'keywords' => $section['keywords'] ?? '',
        'lead' => Markup::create(Xss::filterAdmin($section['lead'] ?? '')),
        'notes' => isset($section['notes']) ? Markup::create(Xss::filterAdmin($section['notes'])) : NULL,
        'kit' => $section['kit'] ?? $id,
        'components' => $used,
        'examples' => $examples,
        'form' => !empty($section['form']),
      ];
    }

    return [
      '#theme' => 'drubricks_design_system',
      '#groups' => array_values($groups),
      '#components' => array_values($components),
      '#count' => count($components),
      '#form' => $this->formBuilder()->getForm(DemoForm::class),
      '#attached' => ['library' => ['drubricks_design_system/page']],
      '#cache' => ['tags' => ['component_plugins'], 'contexts' => ['user.permissions']],
    ];
  }

  /**
   * All components of the theme: id, name, description, props, slots.
   *
   * @return array<string, array<string, mixed>>
   *   Keyed by component id.
   */
  private function componentIndex(): array {
    $index = [];
    foreach ($this->components->getAllComponents() as $component) {
      $id = $component->getPluginId();
      if (!str_starts_with($id, self::PROVIDER . ':')) {
        continue;
      }
      $metadata = $component->metadata;
      $props = array_diff(array_keys($metadata->schema['properties'] ?? []), ['attributes']);
      $index[$id] = [
        'id' => $id,
        'anchor' => Html::getId('component-' . $id),
        'name' => $metadata->name,
        'description' => $metadata->description,
        'group' => $metadata->group,
        'props' => array_values($props),
        'required' => $metadata->schema['required'] ?? [],
        'slots' => array_keys($metadata->slots),
        'js' => (bool) preg_match('/\.js$/', implode(' ', array_keys($component->library['js'] ?? []))),
        'sections' => [],
      ];
    }
    ksort($index);
    return $index;
  }

}
