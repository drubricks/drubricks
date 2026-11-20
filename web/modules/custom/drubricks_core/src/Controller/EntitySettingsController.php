<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Admin page of an entity type: the base route of its Field UI tabs.
 *
 * Apps route it in MODULE.routing.yml (entity.TYPE.settings, with the
 * default entity_type_id) and set it as the entity type's
 * field_ui_base_route. Not a link template: core's Navigation would take a
 * link template path for a record page.
 */
final class EntitySettingsController extends ControllerBase {

  public function __construct(
    private readonly RouteProviderInterface $routeProvider,
  ) {}

  /**
   * Title: "Contacts settings".
   */
  public function title(string $entity_type_id): TranslatableMarkup {
    return $this->t('@type settings', ['@type' => $this->entityTypeManager()->getDefinition($entity_type_id)->getCollectionLabel()]);
  }

  /**
   * What the tabs do, with links to the records and their admin list.
   */
  public function page(string $entity_type_id): array {
    $type = $this->entityTypeManager()->getDefinition($entity_type_id);
    $links = [];
    $targets = [
      "entity.$entity_type_id.field_ui_fields" => $this->t('Manage fields'),
      "view.{$entity_type_id}_admin.page_1" => $this->t('Admin list of the records'),
    ];
    foreach ($targets as $route => $title) {
      $url = Url::fromRoute($route);
      if ($this->routeExists($route) && $url->access()) {
        $links[] = Link::fromTextAndUrl($title, $url);
      }
    }
    return [
      'text' => [
        '#markup' => '<p>' . $this->t('Fields of %type records: the base fields come with the app (code); add your own in <em>Manage fields</em>, arrange the add / edit form in <em>Manage form display</em> and the record details in <em>Manage display</em>. The app screens follow these settings.', [
          '%type' => $type->getPluralLabel(),
        ]) . '</p>',
      ],
      'links' => [
        '#theme' => 'item_list',
        '#items' => $links,
      ],
      '#cache' => ['contexts' => ['user.permissions']],
    ];
  }

  /**
   * Whether a route exists (Field UI or the admin view may be missing).
   */
  private function routeExists(string $route): bool {
    try {
      $this->routeProvider->getRouteByName($route);
      return TRUE;
    }
    catch (\Exception) {
      return FALSE;
    }
  }

}
