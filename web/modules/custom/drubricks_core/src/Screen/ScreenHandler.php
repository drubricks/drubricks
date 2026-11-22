<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Screen;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\drubricks_core\Access\AccessibleRecordsTrait;
use Drupal\drubricks_core\Datetime\RelativeDayTrait;
use Drupal\drubricks_core\Entity\PreloadReferencesTrait;
use Drupal\drubricks_core\Entity\DrubricksEntityInterface;
use Drupal\drubricks_core\Htmx\Swap;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Describes the "list + details" screen of an entity type.
 *
 * Register it as the 'drubricks_screen' handler of the entity type and
 * override only what differs: row(), detail(), inspector(), query().
 *
 * @phpstan-consistent-constructor
 */
class ScreenHandler implements EntityHandlerInterface {

  use StringTranslationTrait;
  use AccessibleRecordsTrait;
  use PreloadReferencesTrait;
  use RelativeDayTrait;

  /**
   * View mode of the details and the side panel.
   */
  public const VIEW_MODE = 'full';

  /**
   * Records per page.
   */
  protected int $limit = 50;

  /**
   * The time service (request time for "today").
   */
  protected TimeInterface $time;

  public function __construct(
    protected readonly EntityTypeInterface $entityType,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountInterface $currentUser,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $instance = new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('date.formatter'),
    );
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * Title of the list column.
   */
  public function title(): string {
    return (string) $this->entityType->getCollectionLabel();
  }

  /**
   * Base query of the list, access checked (own / any: RecordAccessHooks).
   */
  public function query(Request $request): QueryInterface {
    $type = $this->entityType;
    $query = $this->entityTypeManager->getStorage($type->id())->getQuery()->accessCheck(TRUE);
    $search = trim((string) $request->query->get('q', ''));
    if ($search !== '' && $type->hasKey('label')) {
      $query->condition($type->getKey('label'), $search, 'CONTAINS');
    }
    $this->sort($query);
    return $query;
  }

  /**
   * Sort order of the list.
   */
  protected function sort(QueryInterface $query): void {
    if ($this->entityType->hasKey('label')) {
      $query->sort($this->entityType->getKey('label'));
    }
  }

  /**
   * Loads the records of the current page.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Entities.
   */
  public function load(Request $request): array {
    $ids = $this->query($request)->pager($this->limit)->execute();
    return $this->entityTypeManager->getStorage($this->entityType->id())->loadMultiple($ids);
  }

  /**
   * Counts all records the user may see (without paging).
   */
  public function count(Request $request): int {
    return (int) $this->query($request)->count()->execute();
  }

  /**
   * Values of one list row (kit .row): title, meta, subtitle, text, colour.
   *
   * Optional: 'avatar' (a name: an avatar in the colour instead of the dot)
   * and 'pill' (['label' => …, 'color' => token]: a status pill on the right
   * of the title).
   *
   * @return array{title: string, meta?: string, subtitle?: string, text?: string, color?: ?string, section?: string, avatar?: string, pill?: array{label: string, color?: string}}
   *   Row values; plain strings, escaped by the template.
   */
  public function row(EntityInterface $entity): array {
    $row = ['title' => (string) $entity->label()];
    if ($entity instanceof DrubricksEntityInterface) {
      $row['meta'] = $this->dateFormatter->format($entity->getChangedTime(), 'custom', 'j M');
      $row['color'] = $entity->getColor();
    }
    return $row;
  }

  /**
   * Builds the list rows with HTMX links to the details.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   Entities.
   * @param \Drupal\Core\Entity\EntityInterface|null $active
   *   The record shown in the details pane.
   */
  public function rows(array $entities, ?EntityInterface $active): array {
    $rows = [];
    foreach ($entities as $entity) {
      $url = $entity->toUrl();
      $link = ['#attributes' => []];
      Swap::link($link, $url, Swap::DETAIL);
      $rows[] = $this->row($entity) + [
        'id' => $entity->id(),
        'url' => $url->toString(),
        'selected' => $active && $active->id() === $entity->id(),
        'attributes' => $link['#attributes'],
      ];
    }
    return $rows;
  }

  /**
   * Content of the details pane.
   *
   * Set '#drubricks_header' => TRUE on the returned array when it renders
   * its own heading (drubricks:detail-header); the pane then leaves out its
   * plain title.
   */
  public function detail(EntityInterface $entity): array {
    return $this->entityTypeManager->getViewBuilder($this->entityType->id())->view($entity, self::VIEW_MODE);
  }

  /**
   * The view display of the details and the side panel (Manage display).
   *
   * View mode "full"; without its own display, the default one (core's
   * fallback).
   */
  protected function display(FieldableEntityInterface $entity): EntityViewDisplayInterface {
    return EntityViewDisplay::collectRenderDisplay($entity, self::VIEW_MODE);
  }

  /**
   * Whether the details show a field: in Manage display and viewable.
   *
   * Designed screens ask this for every field they place (a field hidden in
   * Manage display is hidden there too) and add displayFields() to the
   * render array, which carries the display's cache tags.
   */
  protected function shows(FieldableEntityInterface $entity, string $field_name): bool {
    return $entity->hasField($field_name)
      && $this->display($entity)->getComponent($field_name) !== NULL
      && $entity->get($field_name)->access('view', $this->currentUser);
  }

  /**
   * Facts (drubricks-facts rows) of the fields the details show.
   *
   * A fact with 'field' => NAME is left out when Manage display hides that
   * field (or the user may not view it); facts without 'field' stay.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The record.
   * @param array<int, array> $facts
   *   Facts, each optionally with 'field'.
   *
   * @return array<int, array>
   *   The visible facts, without the 'field' key.
   */
  protected function visibleFacts(FieldableEntityInterface $entity, array $facts): array {
    $visible = array_filter($facts, fn (array $fact) => !isset($fact['field']) || $this->shows($entity, $fact['field']));
    return array_values(array_map(static fn (array $fact) => array_diff_key($fact, ['field' => TRUE]), $visible));
  }

  /**
   * The fields of Manage display that a designed screen does not place.
   *
   * Fields added in Manage fields and other visible components, in the
   * display's order, with their formatters and labels. Designed screens put
   * it below the fields they place themselves.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The record.
   * @param string[] $placed
   *   Fields (display components) the screen renders itself.
   *
   * @return array
   *   A drubricks_fields render array, with the display's cache metadata.
   */
  protected function displayFields(FieldableEntityInterface $entity, array $placed): array {
    $display = $this->display($entity);
    $build = [
      '#theme' => 'drubricks_fields',
      '#content' => array_diff_key($display->build($entity), array_flip($placed)),
    ];
    CacheableMetadata::createFromObject($display)->applyTo($build);
    return $build;
  }

  /**
   * Content of the inspector (right sidebar): facts about the record.
   *
   * Return [] when the record has nothing for the inspector: the screen then
   * has no right sidebar and no toggle for it.
   */
  public function inspector(EntityInterface $entity): array {
    $facts = [];
    if ($entity instanceof DrubricksEntityInterface) {
      $facts[] = ['label' => $this->t('Owner'),
        'value' => $entity->getOwnerId() ? $entity->getOwner()->getDisplayName() : '—',
      ];
      $facts[] = ['label' => $this->t('Created'),
        'value' => $this->dateFormatter->format($entity->getCreatedTime(), 'short'),
      ];
      $facts[] = ['label' => $this->t('Changed'),
        'value' => $this->dateFormatter->format($entity->getChangedTime(), 'short'),
      ];
      $facts[] = ['label' => $this->t('Status'),
        'value' => $entity->isPublished() ? $this->t('Active') : $this->t('Inactive'),
      ];
    }
    return $facts ? [
      '#theme' => 'drubricks_facts',
      '#facts' => $facts,
    ] : [];
  }

  /**
   * Toolbar actions of the details pane.
   *
   * @return array<string, array{title: string, modal_title?: string, url: \Drupal\Core\Url, icon: string, modal?: bool, level?: string, danger?: bool}>
   *   Actions keyed by id; only the accessible ones are rendered.
   */
  public function actions(EntityInterface $entity): array {
    $actions = [];
    if ($entity->hasLinkTemplate('edit-form')) {
      $actions['edit'] = [
        'title' => (string) $this->t('Edit'),
        'modal_title' => (string) $this->t('Edit @label', ['@label' => $entity->label()]),
        'url' => $entity->toUrl('edit-form'),
        'icon' => 'pencil',
        'modal' => TRUE,
      ];
    }
    if ($entity->hasLinkTemplate('delete-form')) {
      $actions['delete'] = [
        'title' => (string) $this->t('Delete'),
        'modal_title' => (string) $this->t('Delete @label?', ['@label' => $entity->label()]),
        'url' => $entity->toUrl('delete-form'),
        'icon' => 'trash',
        'modal' => TRUE,
        'danger' => TRUE,
      ];
    }
    return $actions;
  }

  /**
   * The record in the right-side panel of a board (details + facts).
   *
   * Boards never navigate away: a card opens here (rule in AGENTS.md).
   */
  public function panel(EntityInterface $entity): array {
    return [
      'detail' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['drubricks-detail-body']],
        'view' => $this->detail($entity),
      ],
      'facts' => $this->inspector($entity),
    ];
  }

  /**
   * Board view of the records (kanban), or NULL for none.
   *
   * Needs the link template "drubricks-board" on the entity type. Keys:
   * - field: list_string field whose value is the lane (required);
   * - lanes: [value => [label, kit colour token]] (required; the label as
   *   TranslatableMarkup, e.g. from the field's allowed values method);
   * - order: integer field saved with the position inside a lane (optional);
   * - sum: numeric field summed per lane in the lane header (optional);
   * - filter: entity reference field offered as a filter above the board;
   * - default: TRUE when the board is the main view (sidebar, Board / List
   *   switch first, redirect after a delete).
   * A move is saved through the generic PATCH endpoint with field access
   * and entity validation; a refused move is reverted with the message.
   *
   * @return array{field: string, lanes: array<string, array{0: string, 1: string}>, order?: string, sum?: string, filter?: string, default?: bool}|null
   *   The board definition.
   */
  public function board(): ?array {
    return NULL;
  }

  /**
   * Calendar view of the records, or NULL for none.
   *
   * Needs the link template "drubricks-calendar" on the entity type. Keys:
   * - start, end: datetime fields (required);
   * - resource: entity reference field; its targets become the columns of
   *   the "resources" view and give events their colour (optional);
   * - view: 'week' (default), 'day', 'resources' or 'agenda';
   * - hours: business hours [start, end] (whole hours, default [8, 18]);
   * - days: business days as ISO weekdays (1 = Monday … 7 = Sunday, default
   *   [1, 2, 3, 4, 5]); other days are shaded;
   * - step: snapping in minutes (default 15);
   * - default: TRUE when the calendar is the main view.
   * Dragging / resizing an event is saved by the generic PATCH endpoint
   * (field access + entity validation, so a conflict is refused and the event
   * snaps back); dragging on empty time opens the add form prefilled; an
   * event opens in the right-side panel.
   *
   * @return array{start: string, end: string, resource?: string, view?: string, hours?: array{0: int, 1: int}, days?: int[], step?: int, default?: bool}|null
   *   The calendar definition.
   */
  public function calendar(): ?array {
    return NULL;
  }

  /**
   * Table view of the records: a core View, or NULL for none.
   *
   * Needs the link template "drubricks-table" on the entity type. Keys:
   * - view: the View id (config of the app module, built in the Views UI:
   *   table style, exposed filters, the bulk form, entity operations, a full
   *   pager) — the drubricks theme gives it the UI kit look;
   * - display: its display (default: 'default');
   * - default: TRUE when the table is the main view.
   * Edit / delete operations open in modals and come back to the table.
   *
   * @return array{view: string, display?: string, default?: bool}|null
   *   The table definition.
   */
  public function table(): ?array {
    return NULL;
  }

  /**
   * Chart view of the records: a core View with the Chart style, or NULL.
   *
   * Needs the link template "drubricks-chart" on the entity type and the
   * contrib "charts" module with its Chart.js library. Keys like table():
   * - view: the View id (config of the app module, built in the Views UI:
   *   "Chart" format, usually with aggregation, e.g. a sum per month of the
   *   "<date> (period)" field; exposed filters show in the header's toolbar
   *   row and reload only the chart);
   * - display: its display (default: 'default');
   * - default: TRUE when the chart is the main view.
   *
   * @return array{view: string, display?: string, default?: bool}|null
   *   The chart definition.
   */
  public function chart(): ?array {
    return NULL;
  }

  /**
   * One calendar event: title, colour token (optional), read-only flag.
   *
   * @return array<string, mixed>
   *   Event values; start, end, resource and id are added by the view.
   */
  public function event(EntityInterface $entity): array {
    $event = ['title' => (string) $entity->label()];
    if ($entity instanceof DrubricksEntityInterface && $entity->getColor()) {
      $event['color'] = $entity->getColor();
    }
    return $event;
  }

  /**
   * Extra values of a calendar resource column.
   *
   * Keys: 'sub' (second line) and 'hours'
   * (['start' => 8, 'end' => 18, 'days' => [1, 2, 3, 4, 5]]).
   *
   * @return array<string, mixed>
   *   Values merged into the resource (id, title and colour are set).
   */
  public function calendarResource(EntityInterface $resource): array {
    return [];
  }

  /**
   * One board card: title, subtitle, meta, colour, icon, avatar name.
   *
   * @return array<string, mixed>
   *   Card values (plain strings, escaped by the JS).
   */
  public function card(EntityInterface $entity): array {
    $row = $this->row($entity);
    $card = [
      'title' => $row['title'],
      'subtitle' => $row['subtitle'] ?? '',
      'meta' => $row['meta'] ?? '',
    ];
    if ($entity instanceof DrubricksEntityInterface && $entity->getOwnerId()) {
      $card['avatar'] = $entity->getOwner()->getDisplayName();
    }
    return $card;
  }

  /**
   * Props 'url' + 'attributes' of a component linking to another record.
   *
   * The link swaps the whole app (the record's own screen), like a sidebar
   * click. Use it in detail(), panel() and inspector() content.
   *
   * @return array{url?: string, attributes?: \Drupal\Core\Template\Attribute}
   *   Empty when there is no record or the user may not view it.
   */
  protected function recordLink(?EntityInterface $entity): array {
    if (!$entity || !$entity->hasLinkTemplate('canonical') || !$entity->access('view')) {
      return [];
    }
    $url = $entity->toUrl();
    $link = ['#attributes' => []];
    Swap::link($link, $url, Swap::CONTENT);
    return ['url' => $url->toString(), 'attributes' => new Attribute($link['#attributes'])];
  }

  /**
   * Cache metadata of what the screen shows for these records.
   *
   * The records and the records they reference (a contact's company, a
   * task's project, owners): rows, cards, events and details print their
   * labels, so a change to them must invalidate the page. Override to add
   * more (e.g. the list tag of records counted per row).
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The records shown.
   */
  public function cacheability(array $entities): CacheableMetadata {
    $cache = new CacheableMetadata();
    foreach ($entities as $entity) {
      $cache->addCacheableDependency($entity);
      if ($entity instanceof ContentEntityInterface) {
        foreach ($entity->referencedEntities() as $referenced) {
          $cache->addCacheableDependency($referenced);
        }
      }
    }
    return $cache;
  }

  /**
   * The record a reference field points to, if the user may see its label.
   *
   * Like core's entity reference formatters: a record the user may not see
   * is left out (the screens' cache varies by user already).
   */
  protected function referenced(ContentEntityInterface $entity, string $field): ?EntityInterface {
    $target = $entity->get($field)->entity;
    return $target instanceof EntityInterface && $target->access('view label') ? $target : NULL;
  }

  /**
   * Label of the record a reference field points to, or '' (see referenced()).
   */
  protected function referenceLabel(ContentEntityInterface $entity, string $field): string {
    return (string) $this->referenced($entity, $field)?->label();
  }

  /**
   * Reasons why a record must not be deleted (e.g. other records use it).
   *
   * Asked by DrubricksAccessControlHandler: a record with reasons is not
   * deletable at all ("delete" access is forbidden with the reasons) — in the
   * UI, bulk actions, Views operations and APIs.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The record.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheability
   *   Collects what the answer depends on — add the list cache tags of the
   *   entity types you count (e.g. crm_contact_list), so a cached answer
   *   changes when those records do.
   *
   * @return string[]
   *   Messages for the user; empty when deleting is fine.
   */
  public function deleteBlockers(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
    return [];
  }

  /**
   * Text of the empty details pane.
   */
  public function emptyText(): string {
    return (string) $this->t('Select an item');
  }

  /**
   * The "add" URL, or NULL when the user may not create records.
   */
  public function addUrl(): ?Url {
    if (!$this->entityType->hasLinkTemplate('add-form')) {
      return NULL;
    }
    $access = $this->entityTypeManager->getAccessControlHandler($this->entityType->id())->createAccess(NULL, $this->currentUser);
    return $access ? Url::fromRoute('entity.' . $this->entityType->id() . '.add_form') : NULL;
  }

}
