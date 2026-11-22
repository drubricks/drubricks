<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Views;

use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\views\EntityViewsData;

/**
 * Views data for Drubricks entities: base fields, owner relationship, colour.
 *
 * Use as the 'views_data' handler of the entity type.
 */
class DrubricksViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();
    $table = $this->entityType->getDataTable() ?: $this->entityType->getBaseTable();
    if (isset($data[$table]['color'])) {
      $data[$table]['color']['help'] = $this->t('A UI kit colour token, e.g. teal.');
    }
    // Choice lists (list_string base fields): a select of the allowed values
    // as the filter, like configurable list fields get, not a text box.
    // Multi-value ones live in their own table (column NAME_value), like
    // core's options_field_views_data() for configurable list fields.
    $mapping = $this->storage->getTableMapping();
    foreach ($this->entityFieldManager->getFieldStorageDefinitions($this->entityType->id()) as $name => $definition) {
      // References to other records (single value, in the entity table): core's
      // entity_reference filter — a select (or autocomplete) of the records
      // the user may see, through the field's selection handler — instead of
      // a number box. Core offers it for configurable fields the same way.
      if ($definition->getType() === 'entity_reference' && !$definition->isMultiple()
        && ($data[$table][$name]['filter']['id'] ?? NULL) === 'numeric') {
        $data[$table][$name]['filter']['id'] = 'entity_reference';
        $data[$table][$name]['filter']['field_name'] = $name;
        continue;
      }
      // Money / amounts (decimal, float): core's numeric filter (=, <, >=,
      // between …) — core leaves them with the "standard" one, which has no
      // value to filter by.
      if (in_array($definition->getType(), ['decimal', 'float'], TRUE) && !$definition->isMultiple()
        && ($data[$table][$name]['filter']['id'] ?? NULL) === 'standard') {
        $data[$table][$name]['filter']['id'] = 'numeric';
        continue;
      }
      // Dates: core's datetime filter (a date picker, "between" with min / max)
      // instead of a text box, like datetime_field_views_data() gives
      // configurable date fields.
      if ($definition->getType() === 'datetime' && !$definition->isMultiple()
        && isset($data[$table][$name]['filter']) && $this->moduleHandler->moduleExists('datetime')) {
        $data[$table][$name]['filter']['id'] = 'datetime';
        $data[$table][$name]['filter']['field_name'] = $name;
        // Sorting by day / month / year (core's datetime sort granularity).
        $data[$table][$name]['sort']['id'] = 'datetime';
        $data[$table][$name]['sort']['field_name'] = $name;
        // The period it falls in, for charts with aggregation ("by month").
        $data[$table][$name . '_period'] = [
          'title' => $this->t('@label (period)', ['@label' => $definition->getLabel()]),
          'help' => $this->t('The year, month or day of the date: group by it (aggregation) for a chart per period; sort by the date with the same granularity.'),
          'field' => [
            'id' => 'drubricks_date_period',
            'field_name' => $name,
            'entity_type' => $this->entityType->id(),
            'real field' => $name,
            'click sortable' => FALSE,
          ],
        ];
        continue;
      }
      if (!str_starts_with($definition->getType(), 'list_')) {
        continue;
      }
      if (isset($data[$table][$name]['filter'])) {
        $data[$table][$name]['filter']['id'] = 'list_field';
        $data[$table][$name]['filter']['field_name'] = $name;
      }
      $dedicated = $mapping instanceof DefaultTableMapping && $mapping->requiresDedicatedTableStorage($definition) ? $mapping->getDedicatedDataTableName($definition) : NULL;
      if ($dedicated && isset($data[$dedicated][$name . '_value']['filter'])) {
        $data[$dedicated][$name . '_value']['filter']['id'] = 'list_field';
        $data[$dedicated][$name . '_value']['filter']['field_name'] = $name;
      }
    }
    return $data;
  }

}
