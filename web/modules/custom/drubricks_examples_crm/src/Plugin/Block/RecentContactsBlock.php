<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_examples_crm\Dashboard\CrmDashboardBlockBase;
use Drupal\drubricks_examples_crm\Entity\Contact;

/**
 * Panel: contacts by last contact date, with avatars.
 */
#[Block(
  id: 'drubricks_examples_crm_recent_contacts',
  admin_label: new TranslatableMarkup('Recently contacted (CRM)'),
  category: new TranslatableMarkup('Dashboard'),
)]
final class RecentContactsBlock extends CrmDashboardBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.crm_contact.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $account = $this->currentUser;
    $ids = $this->accessibleQuery('crm_contact', $account)
      ->exists('last_contacted')
      ->sort('last_contacted', 'DESC')
      ->range(0, 5)
      ->execute();
    if (!$ids) {
      return $this->emptyLine((string) $this->t('No contacts yet'));
    }
    $contacts = $this->entityTypeManager->getStorage('crm_contact')->loadMultiple($ids);
    $this->preloadReferences($contacts, 'company');
    $rows = ['#cache' => ['max-age' => $this->untilMidnight()]];
    foreach ($contacts as $contact) {
      assert($contact instanceof Contact);
      $rows[$contact->id()] = [
        '#type' => 'component',
        '#component' => 'drubricks:p-row',
        '#props' => [
          'title' => (string) $contact->label(),
          'subtitle' => implode(' · ', array_filter([
            (string) $contact->get('job_title')->value,
            (string) $contact->getCompany()?->label(),
          ])),
        ] + $this->recordLink($contact),
        '#slots' => [
          'leading' => [
            '#type' => 'component',
            '#component' => 'drubricks:avatar',
            '#props' => [
              'name' => (string) $contact->label(),
              'size' => 'sm',
              'color' => $contact->getColor() ?? ColorTokens::forName((string) $contact->label()),
            ],
          ],
          'trailing' => [
            '#theme' => 'crm_widget_date',
            '#date' => $this->dayLabel((string) $contact->get('last_contacted')->value),
          ],
        ],
      ];
    }
    return $rows;
  }

}
