<?php

declare(strict_types=1);

namespace Drupal\drubricks_examples_project_management\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Templates of the project and task screens (details pane and side panel).
 */
final class ProjectManagementHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    // fields: ScreenHandler::displayFields() (Manage display, the rest).
    $common = ['compact' => FALSE, 'title' => '', 'color' => NULL, 'facts' => [], 'description' => '', 'fields' => []];
    return [
      'pm_project_detail' => [
        'variables' => $common + ['status' => NULL, 'due' => NULL, 'progress' => [], 'stats' => [], 'groups' => []],
      ],
      'pm_task_detail' => [
        'variables' => $common + ['key' => '', 'status' => NULL, 'priority' => NULL, 'project' => NULL],
      ],
      // Trailing cell of a dashboard row: a due date, overdue said in words.
      'pm_widget_due' => [
        'variables' => ['date' => '', 'late' => FALSE],
      ],
    ];
  }

}
