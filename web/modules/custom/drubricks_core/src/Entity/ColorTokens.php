<?php

declare(strict_types=1);

namespace Drupal\drubricks_core\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Colour names of the UI kit. Data stores the token name ('teal'), never hex.
 *
 * Rendered as `var(--teal)`, so every palette and dark mode applies.
 */
final class ColorTokens {

  /**
   * Token names, in the order the kit documents them.
   */
  public const NAMES = ['blue', 'teal', 'green', 'yellow', 'orange', 'red', 'pink', 'purple', 'indigo', 'gray'];

  /**
   * Options for a list_string field or a select element.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Token name => label.
   */
  public static function options(): array {
    $options = [];
    foreach (self::NAMES as $name) {
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $options[$name] = new TranslatableMarkup(ucfirst($name));
    }
    return $options;
  }

  /**
   * A stable token for a name (avatars, tiles of records without a colour).
   *
   * Same palette and hash as the kit demos, so a name keeps its colour.
   */
  public static function forName(string $name): string {
    $palette = ['teal', 'blue', 'purple', 'green', 'orange', 'pink', 'indigo', 'gray'];
    $hash = array_sum(array_map('mb_ord', mb_str_split($name) ?: ['?']));
    return $palette[$hash % count($palette)];
  }

  /**
   * Returns a CSS value for a token name, falling back to gray.
   */
  public static function css(?string $name): string {
    return 'var(--' . (in_array($name, self::NAMES, TRUE) ? $name : 'gray') . ')';
  }

}
