<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Plugin\views\field;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\FieldAPIHandlerTrait;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A date field as a period: the year, month or day it falls in.
 *
 * For charts and summaries with aggregation: rows group by the period
 * ("Revenue by month"). The SQL expression is core's (views.date_sql through
 * the query's getDateFormat()) with the same formats as core's datetime sort
 * granularity (Y, Ym, Ymd), so a "datetime" sort with the same granularity
 * orders by the grouped expression — valid on every database. Datetime values
 * are grouped in the site / user timezone, dates (date-only) as stored.
 *
 * DrubricksViewsData adds it as "<field> (period)" for the datetime base
 * fields of Drubricks entities.
 */
#[ViewsField('drubricks_date_period')]
final class DatePeriod extends FieldPluginBase {

  use FieldAPIHandlerTrait;

  /**
   * Core's date formatter (the period labels).
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * Granularity => [SQL date format (core's sort granularity), label format].
   */
  private const FORMATS = [
    'year' => ['Y', 'Y'],
    'month' => ['Ym', 'M Y'],
    'day' => ['Ymd', 'D j M'],
  ];

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['granularity'] = ['default' => 'month'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['granularity'] = [
      '#type' => 'radios',
      '#title' => new TranslatableMarkup('Period'),
      '#options' => [
        'year' => new TranslatableMarkup('Year'),
        'month' => new TranslatableMarkup('Month'),
        'day' => new TranslatableMarkup('Day'),
      ],
      '#default_value' => $this->options['granularity'],
      '#description' => new TranslatableMarkup('Sort by the date (its sort with the same granularity).'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $granularity = isset(self::FORMATS[$this->options['granularity']]) ? $this->options['granularity'] : 'month';
    $date_only = $this->getFieldStorageDefinition()->getSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE;
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $field = $query->getDateField("$this->tableAlias.$this->realField", TRUE, !$date_only);
    $formula = $query->getDateFormat($field, self::FORMATS[$granularity][0], TRUE);
    $this->field_alias = $query->addField(NULL, $formula, $this->tableAlias . '_' . $this->field . '_' . $granularity);
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    if ($value === NULL || $value === '') {
      return '';
    }
    $granularity = isset(self::FORMATS[$this->options['granularity']]) ? $this->options['granularity'] : 'month';
    [$sql_format, $label_format] = self::FORMATS[$granularity];
    // The period is already in the right timezone: read and print it as is.
    $date = \DateTimeImmutable::createFromFormat('!' . $sql_format, (string) $value, new \DateTimeZone('UTC'));
    if (!$date) {
      return $this->sanitizeValue((string) $value);
    }
    return $this->dateFormatter->format($date->getTimestamp(), 'custom', $label_format, 'UTC');
  }

}
