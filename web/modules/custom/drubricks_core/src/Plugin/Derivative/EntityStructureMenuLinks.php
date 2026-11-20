<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drubricks_core\Entity\DrubricksEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Application → Entity structure: a link per Drubricks entity type.
 *
 * To its admin page (the entity type's field_ui_base_route) with the Field UI
 * tabs; records stay under Application → Entity records (the admin views).
 */
final class EntityStructureMenuLinks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleExtensionList $modules,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static($container->get('entity_type.manager'), $container->get('extension.list.module'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
      $route = $entity_type->get('field_ui_base_route');
      if (!$route || !$entity_type->entityClassImplements(DrubricksEntityInterface::class)) {
        continue;
      }
      $this->derivatives[$id] = [
        'title' => $entity_type->getCollectionLabel(),
        'description' => $this->t('@app: fields, form and display of @type.', [
          '@app' => $this->modules->getName($entity_type->getProvider()),
          '@type' => $entity_type->getPluralLabel(),
        ]),
        'route_name' => $route,
        'parent' => 'drubricks_core.admin_app_structure',
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
