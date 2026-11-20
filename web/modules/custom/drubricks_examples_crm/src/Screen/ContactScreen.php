<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Screen;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_core\Screen\ScreenHandler;
use Drupal\drubricks_examples_crm\Entity\Company;
use Drupal\drubricks_examples_crm\Entity\Contact;

/**
 * Contacts: grouped by stage with avatars in the list; a rich record view.
 */
class ContactScreen extends ScreenHandler {

  use CrmScreenTrait;

  /**
   * {@inheritdoc}
   */
  protected function sort(QueryInterface $query): void {
    // Customers first, then prospects, then leads (the funnel order, see
    // CrmHooks::queryContactsByStageAlter()); by name inside a stage.
    $query->addTag('crm_contact_by_stage')->sort('name');
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
    if ($entity instanceof Contact) {
      $parts = [(string) $entity->get('job_title')->value, $this->referenceLabel($entity, 'company')];
      $row['subtitle'] = trim(implode(' · ', array_filter($parts)));
      $row['text'] = (string) $entity->get('email')->value;
      $row['section'] = (string) (Contact::stages()[$entity->get('stage')->value] ?? '');
      $row['avatar'] = (string) $entity->label();
      $row['color'] = $entity->getColor() ?? ColorTokens::forName((string) $entity->label());
      $row['meta'] = $this->fieldDate($entity, 'last_contacted');
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  protected function view(EntityInterface $entity, bool $compact): array {
    if (!$entity instanceof Contact) {
      return parent::detail($entity);
    }
    // Fields hidden in Manage display are hidden here too.
    $company = $this->shows($entity, 'company') ? $this->referenced($entity, 'company') : NULL;
    $company = $company instanceof Company ? $company : NULL;
    $stage = $this->shows($entity, 'stage') ? (string) $entity->get('stage')->value : '';
    $last = $this->shows($entity, 'last_contacted') ? (string) $entity->get('last_contacted')->value : '';
    $email = $this->shows($entity, 'email') ? (string) $entity->get('email')->value : '';
    $phone = $this->shows($entity, 'phone') ? (string) $entity->get('phone')->value : '';
    $deals = $this->relatedDeals('contact', (int) $entity->id());
    $owner = $entity->getOwnerId() ? $entity->getOwner() : NULL;
    $events = [];
    if ($last) {
      $events[] = ['date' => $last, 'icon' => 'phone', 'color' => 'teal', 'text' => $this->t('Last contact')];
    }
    return [
      '#theme' => 'crm_contact_detail',
      '#compact' => $compact,
      '#title' => (string) $entity->label(),
      '#color' => $entity->getColor() ?? ColorTokens::forName((string) $entity->label()),
      '#job_title' => $this->shows($entity, 'job_title') ? (string) $entity->get('job_title')->value : '',
      '#stage' => $stage ? [
        'label' => (string) (Contact::stages()[$stage] ?? $stage),
        'color' => Contact::STAGE_COLORS[$stage] ?? 'gray',
      ] : NULL,
      '#company' => $this->miniItem($company, implode(' · ', array_filter([
        (string) $company?->get('industry')->value,
        (string) $company?->get('city')->value,
      ])), $company?->getColor()),
      '#email' => $email,
      '#phone' => $phone,
      '#facts' => $this->visibleFacts($entity, array_filter([
        [
          'field' => 'email',
          'label' => $this->t('Email'),
          'value' => $email,
          'url' => $email ? 'mailto:' . $email : '',
        ],
        [
          'field' => 'phone',
          'label' => $this->t('Phone'),
          'value' => $phone,
          'url' => $phone ? 'tel:' . preg_replace('/[^+0-9]/', '', $phone) : '',
        ],
        [
          'field' => 'last_contacted',
          'label' => $this->t('Last contact'),
          'value' => $last ? $this->dayLabel($last, 'j M Y') : '—',
        ],
        [
          'field' => 'uid',
          'label' => $this->t('Owner'),
          'value' => $owner?->getDisplayName() ?? '—',
          'avatar' => $owner?->getDisplayName(),
        ],
      ], static fn ($fact) => $fact['value'] !== '')),
      // Fields added in Manage fields (and others it does not place).
      '#fields' => $this->displayFields($entity, [
        'job_title',
        'stage',
        'company',
        'email',
        'phone',
        'last_contacted',
        'uid',
      ]),
      '#deals' => $this->dealItems($deals),
      '#deal_rows' => [
        '#theme' => 'crm_deal_rows',
        '#deals' => $this->dealItems($deals),
        '#empty' => $this->t('No deals with this contact yet'),
      ],
      '#feed' => $this->timeline($events, $deals),
      '#cache' => [
        'tags' => ['crm_deal_list', 'crm_company_list'],
        'max-age' => $this->untilMidnight(),
      ],
    ];
  }

}
