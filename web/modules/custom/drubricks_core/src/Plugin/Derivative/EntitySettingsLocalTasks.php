<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drubricks_core\Entity\DrubricksEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "Settings" tab of each Drubricks entity type's admin page.
 *
 * The page is the entity type's field_ui_base_route (a route of the app
 * module, linked under Application → Entity structure); Field UI adds its
 * tabs (Manage fields, Manage form display, Manage display) next to this one,
 * like core's "Settings" tab of entity.user.admin_form.
 */
final class EntitySettingsLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
      $route = $entity_type->get('field_ui_base_route');
      if ($route && $entity_type->entityClassImplements(DrubricksEntityInterface::class)) {
        $this->derivatives[$id] = [
          'title' => $this->t('Settings'),
          'route_name' => $route,
          'base_route' => $route,
        ] + $base_plugin_definition;
      }
    }
    return $this->derivatives;
  }

}
