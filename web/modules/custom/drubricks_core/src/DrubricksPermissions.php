<?php

declare(strict_types=1);

namespace Drupal\drubricks_core;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drubricks_core\Access\DrubricksAccessControlHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates own / any permissions for Drubricks entity types.
 */
final class DrubricksPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('entity_type.manager'));
  }

  /**
   * Returns the entity types that use the Drubricks access handler.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   Entity types keyed by id.
   */
  public static function entityTypes(EntityTypeManagerInterface $manager): array {
    return array_filter(
      $manager->getDefinitions(),
      static fn (EntityTypeInterface $type) => is_a($type->getHandlerClass('access') ?? '', DrubricksAccessControlHandler::class, TRUE),
    );
  }

  /**
   * Permission callback.
   *
   * @return array<string, array<string, mixed>>
   *   Permissions.
   */
  public function permissions(): array {
    $permissions = [];
    foreach (self::entityTypes($this->entityTypeManager) as $id => $type) {
      $args = ['%type' => $type->getPluralLabel()];
      $dependencies = ['module' => [$type->getProvider()]];
      $permissions["create $id"] = [
        'title' => $this->t('%type: create', $args),
        'dependencies' => $dependencies,
      ];
      $titles = [
        'view own' => $this->t('%type: view own', $args),
        'view any' => $this->t('%type: view any', $args),
        'edit own' => $this->t('%type: edit own', $args),
        'edit any' => $this->t('%type: edit any', $args),
        'delete own' => $this->t('%type: delete own', $args),
        'delete any' => $this->t('%type: delete any', $args),
      ];
      foreach ($titles as $operation => $title) {
        $permissions["$operation $id"] = ['title' => $title, 'dependencies' => $dependencies];
      }
    }
    return $permissions;
  }

}
