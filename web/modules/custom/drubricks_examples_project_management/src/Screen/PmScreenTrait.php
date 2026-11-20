<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Screen;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\drubricks_core\Entity\ColorTokens;
use Drupal\drubricks_examples_project_management\Entity\Task;
use Drupal\user\UserInterface;

/**
 * Pieces shared by the project and task screens.
 */
trait PmScreenTrait {

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
   * Status pill values of a task.
   *
   * @return array{label: string, color: string}
   *   Label and colour token.
   */
  protected function statusPill(Task $task): array {
    $state = $task->getState();
    return ['label' => (string) (Task::statuses()[$state] ?? $state), 'color' => Task::STATUS_COLORS[$state] ?? 'gray'];
  }

  /**
   * Due date of a task: label, overdue flag (open tasks only).
   *
   * @return array{label: string, late: bool}
   *   Empty label without a due date.
   */
  protected function due(Task $task): array {
    $due = (string) $task->get('due_date')->value;
    return [
      'label' => $due ? $this->dayLabel($due, 'j M Y') : '',
      'late' => $due !== '' && $task->getState() !== 'done' && $this->isPast($due),
    ];
  }

  /**
   * Assignee name and avatar colour, or NULL.
   *
   * @return array{name: string, color: string}|null
   *   The assignee.
   */
  protected function assignee(Task $task): ?array {
    $user = $task->get('assignee')->entity;
    if (!$user instanceof UserInterface) {
      return NULL;
    }
    return ['name' => $user->getDisplayName(), 'color' => ColorTokens::forName($user->getDisplayName())];
  }

}
