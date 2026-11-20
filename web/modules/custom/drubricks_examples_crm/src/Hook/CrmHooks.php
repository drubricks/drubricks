<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_crm\Hook;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\drubricks_examples_crm\Entity\Contact;

/**
 * Templates of the CRM record screens (details pane and side panel).
 */
final class CrmHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    // fields: ScreenHandler::displayFields() (Manage display, the rest).
    $common = ['compact' => FALSE, 'title' => '', 'color' => NULL, 'feed' => [], 'fields' => []];
    return [
      'crm_contact_detail' => [
        'variables' => $common + [
          'job_title' => '',
          'stage' => NULL,
          'company' => NULL,
          'facts' => [],
          'email' => '',
          'phone' => '',
          'deals' => [],
          'deal_rows' => NULL,
        ],
      ],
      'crm_company_detail' => [
        'variables' => $common + [
          'subtitle' => '',
          'website' => '',
          'stats' => [],
          'facts' => [],
          'contacts' => [],
          'deals' => [],
          'deal_rows' => NULL,
        ],
      ],
      'crm_deal_detail' => [
        'variables' => $common + [
          'value' => '',
          'stage' => NULL,
          'stages' => [],
          'company' => NULL,
          'contact' => NULL,
          'facts' => [],
        ],
      ],
      // Deals as panel rows (company and contact details).
      'crm_deal_rows' => [
        'variables' => ['deals' => [], 'empty' => ''],
      ],
      // Trailing cell of a dashboard row: a deal's value and close date.
      'crm_widget_deal_due' => [
        'variables' => ['value' => '', 'date' => '', 'late' => FALSE],
      ],
      // Trailing cell of a dashboard row: a date.
      'crm_widget_date' => [
        'variables' => ['date' => ''],
      ],
    ];
  }

  /**
   * Implements hook_query_TAG_alter() for crm_contact_by_stage.
   *
   * Orders contacts by stage in funnel order (customer, prospect, lead), which
   * an entity query cannot sort by; the stage order goes before other sorts.
   * Count queries have no sorts and stay as they are.
   */
  #[Hook('query_crm_contact_by_stage_alter')]
  public function queryContactsByStageAlter(AlterableInterface $query): void {
    if (!$query instanceof SelectInterface || !$query->getOrderBy()) {
      return;
    }
    $alias = NULL;
    foreach ($query->getTables() as $table_alias => $info) {
      if (($info['table'] ?? NULL) === 'crm_contact') {
        $alias = $table_alias;
        break;
      }
    }
    if ($alias === NULL) {
      return;
    }
    $order = array_keys(Contact::stages());
    $cases = [];
    $arguments = [];
    foreach (array_reverse($order) as $i => $stage) {
      $cases[] = "WHEN :crm_stage_$i THEN $i";
      $arguments[":crm_stage_$i"] = $stage;
    }
    $query->addExpression("CASE $alias.stage " . implode(' ', $cases) . ' ELSE ' . count($order) . ' END', 'crm_stage_order', $arguments);
    $sorts = &$query->getOrderBy();
    $sorts = ['crm_stage_order' => 'ASC'] + $sorts;
  }

}
