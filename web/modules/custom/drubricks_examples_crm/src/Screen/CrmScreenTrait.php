<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Screen;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_examples_crm\Entity\Deal;

/**
 * Pieces shared by the CRM record screens.
 */
trait CrmScreenTrait {

  /**
   * A date field formatted like core's datetime formatters, or ''.
   *
   * From the field's "date" property; date-only fields in the storage
   * timezone (the day does not move), date + time in the user's timezone.
   */
  protected function fieldDate(ContentEntityInterface $entity, string $field, string $format = 'j M'): string {
    $date = $entity->get($field)->date;
    if (!$date instanceof DrupalDateTime) {
      return '';
    }
    $timezone = $entity->getFieldDefinition($field)?->getSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE
      ? DateTimeItemInterface::STORAGE_TIMEZONE
      : NULL;
    return $this->dateFormatter->format($date->getTimestamp(), 'custom', $format, $timezone);
  }

  /**
   * The side panel of a board: the compact record view + the facts.
   */
  public function panel(EntityInterface $entity): array {
    return [
      'detail' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['drubricks-detail-body']],
        'view' => $this->view($entity, TRUE),
      ],
      'facts' => ['#title' => $this->t('Record')] + $this->inspector($entity),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function detail(EntityInterface $entity): array {
    return $this->view($entity, FALSE) + ['#drubricks_header' => TRUE];
  }

  /**
   * The record rendered with components; compact = side panel.
   */
  abstract protected function view(EntityInterface $entity, bool $compact): array;

  /**
   * Deals the user may see, matching a field value, by close date.
   *
   * @return \Drupal\drubricks_examples_crm\Entity\Deal[]
   *   Deals keyed by id (companies preloaded).
   */
  protected function relatedDeals(string $field, int|string $id): array {
    $ids = $this->accessibleQuery('crm_deal', $this->currentUser)?->condition($field, $id)->sort('close_date')->execute() ?? [];
    $deals = array_filter($this->entityTypeManager->getStorage('crm_deal')->loadMultiple($ids), static fn ($deal) => $deal instanceof Deal);
    $this->preloadReferences($deals, 'company');
    return $deals;
  }

  /**
   * Template items of deals: title, sub line, value, stage pill, link.
   *
   * @param \Drupal\drubricks_examples_crm\Entity\Deal[] $deals
   *   Deals.
   * @param bool $with_company
   *   Name the company in the sub line.
   */
  protected function dealItems(array $deals, bool $with_company = TRUE): array {
    $items = [];
    foreach ($deals as $deal) {
      $stage = $deal->getStage();
      $close = (string) $deal->get('close_date')->value;
      $items[] = [
        'title' => (string) $deal->label(),
        'subtitle' => implode(' · ', array_filter([
          $with_company ? $this->referenceLabel($deal, 'company') : '',
          match (TRUE) {
            !$close => '',
            $deal->isClosed() => (string) $this->t('closed @date', ['@date' => $this->dayLabel($close, 'j M Y')]),
            $this->isPast($close) => (string) $this->t('overdue since @date', ['@date' => $this->dayLabel($close, 'j M Y')]),
            default => (string) $this->t('closes @date', ['@date' => $this->dayLabel($close, 'j M Y')]),
          },
        ])),
        'value' => self::money((float) $deal->get('value')->value),
        'stage' => [
          'label' => (string) (Deal::stages()[$stage] ?? $stage),
          'color' => Deal::STAGE_COLORS[$stage] ?? 'gray',
        ],
        'link' => $this->recordLink($deal),
      ];
    }
    return $items;
  }

  /**
   * A mini card of a record: title, sub line, tile colour, link.
   */
  protected function miniItem(?EntityInterface $entity, string $subtitle = '', ?string $color = NULL): ?array {
    if (!$entity) {
      return NULL;
    }
    return [
      'title' => (string) $entity->label(),
      'subtitle' => $subtitle,
      'color' => $color ?? ColorTokens::forName((string) $entity->label()),
      'link' => $this->recordLink($entity),
    ];
  }

  /**
   * Timeline lines (kit feed): the given events and the deals' close dates.
   *
   * @param array<int, array{date: string, icon: string, color: string, text: \Stringable|string, meta?: string}> $events
   *   Events with a local Y-m-d date.
   * @param \Drupal\drubricks_examples_crm\Entity\Deal[] $deals
   *   Related deals: won / lost / expected to close on their close date.
   */
  protected function timeline(array $events, array $deals): array {
    foreach ($deals as $deal) {
      $close = (string) $deal->get('close_date')->value;
      if (!$close) {
        continue;
      }
      $args = ['@deal' => $deal->label()];
      $value = self::money((float) $deal->get('value')->value);
      $events[] = match ($deal->getStage()) {
        'won' => ['icon' => 'trophy', 'color' => 'green', 'text' => $this->t('Won <b>@deal</b>', $args)],
        'lost' => ['icon' => 'xmark', 'color' => 'red', 'text' => $this->t('Lost <b>@deal</b>', $args)],
        default => ['icon' => 'calendar-clock', 'color' => Deal::STAGE_COLORS[$deal->getStage()] ?? 'gray',
          'text' => $this->t('<b>@deal</b> is expected to close', $args),
        ],
      } + ['date' => $close, 'meta' => $value];
    }
    usort($events, static fn ($a, $b) => $b['date'] <=> $a['date']);
    return array_map(fn ($event) => [
      'meta' => implode(' · ', array_filter([$this->dayLabel($event['date'], 'j M Y'), $event['meta'] ?? ''])),
    ] + $event, array_slice($events, 0, 8));
  }

  /**
   * A full amount with thin spaces: 120 000.
   */
  public static function money(float $value): string {
    return number_format($value, 0, '.', ' ');
  }

}
