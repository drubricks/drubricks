<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\drubricks_core\Entity\DrubricksEntityInterface;
use Drupal\drubricks_core\Htmx\Swap;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Views tables of Drubricks entities: bulk form and entity operations.
 *
 * Core's entity operations are plain links with ?destination=. On an app
 * screen (route option _drubricks_screen, e.g. the table view) edit and
 * delete open the app forms in a modal instead and come back to the same
 * page, and view opens the record in the page's right-side panel; admin
 * pages keep core's links.
 */
final class TableHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly RequestStack $requestStack,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_views_data_alter().
   *
   * Bulk forms of Drubricks entities offer only the rows and actions the
   * user may use (DrubricksBulkForm), in the app and in the admin views.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    foreach ($this->entityTypeManager->getDefinitions() as $id => $type) {
      if (!$type->entityClassImplements(DrubricksEntityInterface::class)) {
        continue;
      }
      $table = $type->getDataTable() ?: $type->getBaseTable();
      if (($data[$table][$id . '_bulk_form']['field']['id'] ?? NULL) === 'bulk_form') {
        $data[$table][$id . '_bulk_form']['field']['id'] = 'drubricks_bulk_form';
      }
    }
  }

  /**
   * Implements hook_form_alter() for the bulk form of a table view.
   *
   * The table region is boosted (Swap::region(): pager and sort links reload
   * only the table); the bulk form inside it posts as core's form — its
   * confirmation (bulk delete) is a page of its own. The sort links inside
   * the form stay boosted (the form's own hx-boost is not inherited).
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (str_starts_with($form_id, 'views_form_') && $this->isTablePage()) {
      Swap::noBoost($form);
    }
  }

  /**
   * Whether the current page is an app table view (TableController).
   */
  private function isTablePage(): bool {
    return str_ends_with((string) $this->routeMatch->getRouteName(), '.drubricks_table');
  }

  /**
   * Implements hook_entity_operation_alter().
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity, CacheableMetadata $cacheability): void {
    // The links depend on the page they are shown on (and its query).
    $cacheability->addCacheContexts(['route', 'url.query_args']);
    if (!$this->routeMatch->getRouteObject()?->getOption('_drubricks_screen')) {
      return;
    }
    $titles = [
      'edit' => $this->t('Edit @label', ['@label' => $entity->label()]),
      'delete' => $this->t('Delete @label?', ['@label' => $entity->label()]),
    ];
    // "View" opens the record in the right-side panel of the same page
    // (?open=ID), between Edit and Delete; the page itself stays.
    if (isset($operations['view'])) {
      // Keep the page's filters, sorting and page.
      $query = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
      unset($query['destination'], $query['_wrapper_format']);
      $operations['view']['url'] = Url::fromRoute('<current>', [], ['query' => ['open' => $entity->id()] + $query]);
      $operations['view']['weight'] = 50;
    }
    foreach ($titles as $key => $title) {
      if (isset($operations[$key]['url']) && $operations[$key]['url'] instanceof Url) {
        $url = $operations[$key]['url'];
        $element = ['#attributes' => []];
        Swap::modal($element, $url, (string) $title);
        // Core's own dialog (use-ajax, data-dialog-*) gives way to the app
        // modal; other attributes (aria-label) stay.
        $attributes = array_filter($url->getOption('attributes') ?? [], static fn ($name) => $name !== 'class' && !str_starts_with((string) $name, 'data-dialog'), ARRAY_FILTER_USE_KEY);
        $url->setOption('attributes', $element['#attributes'] + $attributes);
      }
    }
  }

}
