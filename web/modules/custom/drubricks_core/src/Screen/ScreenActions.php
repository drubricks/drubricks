<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Screen;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;

/**
 * Toolbar actions the user may not run, shown with the reason.
 *
 * An action whose route access is forbidden with a reason (e.g. Delete while
 * other records use the record — DrubricksAccessControlHandler) stays in the
 * toolbar, disabled, with the reason as its tooltip. Actions merely not
 * allowed (no permission) are left out.
 */
final class ScreenActions {

  /**
   * The reason of a forbidden access result, or ''.
   */
  public static function reason(AccessResultInterface $access): string {
    return $access->isForbidden() && $access instanceof AccessResultReasonInterface ? (string) $access->getReason() : '';
  }

  /**
   * An action as a disabled toolbar button with the reason.
   *
   * @param array<string, mixed> $action
   *   The action from ScreenHandler::actions().
   * @param string $reason
   *   Why it cannot be run.
   *
   * @return array<string, mixed>
   *   The action for the templates: disabled, reason, no URL.
   */
  public static function disabled(array $action, string $reason): array {
    unset($action['url']);
    return $action + ['disabled' => TRUE, 'reason' => $reason, 'attributes' => []];
  }

}
