<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Screen;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_examples_crm\Entity\Company;
use Drupal\drubricks_examples_crm\Entity\Contact;
use Drupal\drubricks_examples_crm\Entity\Deal;

/**
 * Deals: value, company and stage pill in the list; stage progress view.
 */
class DealScreen extends ScreenHandler {

  use CrmScreenTrait;

  /**
   * {@inheritdoc}
   */
  protected function sort(QueryInterface $query): void {
    $query->sort('close_date')->sort('name');
  }

  /**
   * {@inheritdoc}
   */
  public function table(): array {
    return ['view' => 'crm_deal_table'];
  }

  /**
   * {@inheritdoc}
   */
  public function board(): array {
    $lanes = [];
    foreach (Deal::stages() as $stage => $label) {
      $lanes[$stage] = [(string) $label, Deal::STAGE_COLORS[$stage]];
    }
    return [
      'field' => 'stage',
      'lanes' => $lanes,
      'sum' => 'value',
      'default' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function card(EntityInterface $entity): array {
    $card = parent::card($entity);
    if ($entity instanceof Deal) {
      $card['subtitle'] = $this->referenceLabel($entity, 'company');
      $close = $this->fieldDate($entity, 'close_date');
      $card['meta'] = number_format((float) $entity->get('value')->value, 0, '.', ' ') . ($close ? ' · ' . $close : '');
    }
    return $card;
  }

  /**
   * {@inheritdoc}
   */
  public function rows(array $entities, ?EntityInterface $active): array {
    // Referenced records of the whole page in one query each, not per row.
    $this->preloadReferences($entities, 'company');
    return parent::rows($entities, $active);
  }

  /**
   * {@inheritdoc}
   */
  public function row(EntityInterface $entity): array {
    $row = parent::row($entity);
    if ($entity instanceof Deal) {
      $color = Deal::STAGE_COLORS[$entity->getStage()] ?? 'gray';
      $row['color'] = $color;
      $row['meta'] = number_format((float) $entity->get('value')->value, 0, '.', ' ');
      $row['subtitle'] = $this->referenceLabel($entity, 'company');
      $close = $entity->get('close_date')->value;
      $row['pill'] = ['label' => (string) (Deal::stages()[$entity->getStage()] ?? ''), 'color' => $color];
      $row['text'] = $close ? (string) $this->t('closes @date', ['@date' => $this->dayLabel($close, 'j M Y')]) : '';
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function view(EntityInterface $entity, bool $compact): array {
    if (!$entity instanceof Deal) {
      return parent::detail($entity);
    }
    $stage = $entity->getStage();
    // Fields hidden in Manage display are hidden here too.
    $company = $this->shows($entity, 'company') ? $this->referenced($entity, 'company') : NULL;
    $company = $company instanceof Company ? $company : NULL;
    $contact = $this->shows($entity, 'contact') ? $this->referenced($entity, 'contact') : NULL;
    $contact = $contact instanceof Contact ? $contact : NULL;
    $close = $this->shows($entity, 'close_date') ? (string) $entity->get('close_date')->value : '';
    $show_value = $this->shows($entity, 'value');
    $show_stage = $this->shows($entity, 'stage');
    $owner = $entity->getOwnerId() ? $entity->getOwner() : NULL;
    // Stage progress: the path to "won"; a lost deal marks where it stopped.
    $path = array_diff_key(Deal::stages(), ['lost' => 1]);
    $position = array_search($stage === 'lost' ? 'won' : $stage, array_keys($path), TRUE);
    $steps = [];
    foreach (array_keys($path) as $i => $key) {
      $steps[] = [
        'label' => (string) $path[$key],
        'color' => Deal::STAGE_COLORS[$key],
        'state' => match (TRUE) {
          $stage === 'lost' => 'past',
          $i < $position => 'past',
          $i === $position => 'current',
          default => 'next',
        },
      ];
    }
    if ($stage === 'lost') {
      $steps[] = ['label' => (string) Deal::stages()['lost'], 'color' => 'red', 'state' => 'current'];
    }
    $late = $close && !$entity->isClosed() && $this->isPast($close);
    return [
      '#theme' => 'crm_deal_detail',
      '#compact' => $compact,
      '#title' => (string) $entity->label(),
      '#value' => $show_value ? self::money((float) $entity->get('value')->value) : '',
      '#stage' => $show_stage ? [
        'label' => (string) (Deal::stages()[$stage] ?? $stage),
        'color' => Deal::STAGE_COLORS[$stage] ?? 'gray',
      ] : NULL,
      '#stages' => $show_stage ? $steps : [],
      '#company' => $this->miniItem($company, implode(' · ', array_filter([
        (string) $company?->get('industry')->value,
        (string) $company?->get('city')->value,
      ])), $company?->getColor()),
      '#contact' => $contact ? [
        'name' => (string) $contact->label(),
        'color' => $contact->getColor() ?? ColorTokens::forName((string) $contact->label()),
        'subtitle' => (string) $contact->get('job_title')->value,
        'link' => $this->recordLink($contact),
      ] : NULL,
      '#facts' => $this->visibleFacts($entity, [
        ['field' => 'value', 'label' => $this->t('Value'), 'value' => self::money((float) $entity->get('value')->value)],
        [
          'field' => 'close_date',
          'label' => $this->t('Close date'),
          'value' => $close ? $this->dayLabel($close, 'j M Y') : '—',
          'late' => $late,
          'icon' => 'calendar',
        ],
        [
          'field' => 'uid',
          'label' => $this->t('Owner'),
          'value' => $owner?->getDisplayName() ?? '—',
          'avatar' => $owner?->getDisplayName(),
        ],
      ]),
      // Fields added in Manage fields (and others it does not place).
      '#fields' => $this->displayFields($entity, ['value', 'stage', 'company', 'contact', 'close_date', 'uid']),
      '#feed' => $this->timeline([], [$entity]),
      '#cache' => ['max-age' => $this->untilMidnight()],
    ];
  }

}
