<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Screen;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_examples_crm\Entity\Company;
use Drupal\drubricks_examples_crm\Entity\Contact;
use Drupal\drubricks_examples_crm\Dashboard\CrmDashboardBlockBase;

/**
 * Companies: industry and city in the list; stats, people and deals.
 */
class CompanyScreen extends ScreenHandler {

  use CrmScreenTrait;

  /**
   * {@inheritdoc}
   */
  public function row(EntityInterface $entity): array {
    $row = parent::row($entity);
    if ($entity instanceof Company) {
      $row['subtitle'] = (string) $entity->get('industry')->value;
      $row['meta'] = (string) $entity->get('city')->value;
      $size = $entity->get('size')->value;
      $row['text'] = $size ? (string) $this->formatPlural((int) $size, '1 employee', '@count employees') : '';
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBlockers(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
    $blockers = [];
    $types = [
      'crm_contact' => $this->t('contacts'),
      'crm_deal' => $this->t('deals'),
    ];
    foreach ($types as $type => $label) {
      $cacheability?->addCacheTags($this->entityTypeManager->getDefinition($type)->getListCacheTags());
      $count = (int) $this->entityTypeManager->getStorage($type)->getQuery()
        ->accessCheck(FALSE)
        ->condition('company', $entity->id())
        ->count()
        ->execute();
      if ($count) {
        $args = ['@count' => $count, '@things' => $label];
        $blockers[] = (string) $this->t('@count @things belong to this company.', $args);
      }
    }
    return $blockers;
  }

  /**
   * {@inheritdoc}
   */
  protected function view(EntityInterface $entity, bool $compact): array {
    if (!$entity instanceof Company) {
      return parent::detail($entity);
    }
    $deals = $this->relatedDeals('company', (int) $entity->id());
    $ids = $this->accessibleQuery('crm_contact', $this->currentUser)?->condition('company', $entity->id())->sort('name')->execute() ?? [];
    $contacts = array_filter($this->entityTypeManager->getStorage('crm_contact')->loadMultiple($ids), static fn ($contact) => $contact instanceof Contact);
    $open = array_filter($deals, static fn ($deal) => !$deal->isClosed());
    $won = array_filter($deals, static fn ($deal) => $deal->getStage() === 'won');
    $sum = static fn (array $list) => array_sum(array_map(static fn ($deal) => (float) $deal->get('value')->value, $list));
    // Fields hidden in Manage display are hidden here too.
    $website = $this->shows($entity, 'website') ? (string) $entity->get('website')->uri : '';
    $size = $this->shows($entity, 'size') ? (int) $entity->get('size')->value : 0;
    $industry = $this->shows($entity, 'industry') ? (string) $entity->get('industry')->value : '';
    $city = $this->shows($entity, 'city') ? (string) $entity->get('city')->value : '';
    $owner = $entity->getOwnerId() ? $entity->getOwner() : NULL;
    $people = [];
    foreach ($contacts as $contact) {
      $stage = (string) $contact->get('stage')->value;
      $people[] = [
        'name' => (string) $contact->label(),
        'color' => $contact->getColor() ?? ColorTokens::forName((string) $contact->label()),
        'subtitle' => (string) $contact->get('job_title')->value,
        'stage' => [
          'label' => (string) (Contact::stages()[$stage] ?? $stage),
          'color' => Contact::STAGE_COLORS[$stage] ?? 'gray',
        ],
        'link' => $this->recordLink($contact),
      ];
    }
    return [
      '#theme' => 'crm_company_detail',
      '#compact' => $compact,
      '#title' => (string) $entity->label(),
      '#color' => $entity->getColor() ?? ColorTokens::forName((string) $entity->label()),
      '#subtitle' => implode(' · ', array_filter([
        $industry,
        $city,
        $size ? (string) $this->formatPlural($size, '1 employee', '@count employees') : '',
      ])),
      '#website' => $website,
      '#stats' => [
        ['label' => (string) $this->t('People'), 'icon' => 'users', 'color' => 'teal', 'value' => count($contacts)],
        [
          'label' => (string) $this->t('Open deals'),
          'icon' => 'dollar',
          'color' => 'blue',
          'value' => CrmDashboardBlockBase::short($sum($open)),
          'sub' => (string) $this->formatPlural(count($open), '1 deal', '@count deals'),
        ],
        [
          'label' => (string) $this->t('Won'),
          'icon' => 'trophy',
          'color' => 'green',
          'value' => CrmDashboardBlockBase::short($sum($won)),
          'sub' => (string) $this->formatPlural(count($won), '1 deal', '@count deals'),
        ],
      ],
      '#facts' => $this->visibleFacts($entity, array_filter([
        ['label' => $this->t('Industry'), 'value' => $industry],
        ['label' => $this->t('City'), 'value' => $city],
        ['label' => $this->t('Employees'), 'value' => $size ? (string) $size : ''],
        ['label' => $this->t('Website'), 'value' => preg_replace('#^https?://#', '', $website), 'url' => $website],
        [
          'field' => 'uid',
          'label' => $this->t('Owner'),
          'value' => $owner?->getDisplayName() ?? '—',
          'avatar' => $owner?->getDisplayName(),
        ],
      ], static fn ($fact) => $fact['value'] !== '')),
      // Fields added in Manage fields (and others it does not place).
      '#fields' => $this->displayFields($entity, ['industry', 'city', 'size', 'website', 'uid']),
      '#contacts' => $people,
      '#deals' => $this->dealItems($deals, FALSE),
      '#deal_rows' => [
        '#theme' => 'crm_deal_rows',
        '#deals' => $this->dealItems($deals, FALSE),
        '#empty' => $this->t('No deals with this company yet'),
      ],
      '#cache' => [
        'tags' => ['crm_deal_list', 'crm_contact_list'],
        'max-age' => $this->untilMidnight(),
      ],
    ];
  }

}
