<?php

declare(strict_types=1);

namespace Drupal\drubricks_installer;

use Drupal\Core\Recipe\Recipe;

/**
 * Finds the application recipes (type "App") in the project recipes folder.
 *
 * Like Drupal CMS finds "Site" templates: the list is built from recipe.yml
 * files, so a new app recipe shows up in the installer without code changes.
 */
final class AppRecipes {

  /**
   * Returns the App recipes keyed by machine name.
   *
   * @return array<string, array{name: string, description: string, path: string, demo: ?string, example: bool}>
   *   Recipes, examples last, then by name.
   */
  public static function all(string $dir): array {
    $apps = [];
    foreach (glob($dir . '/*/recipe.yml') ?: [] as $file) {
      $recipe = Recipe::createFromDirectory(dirname($file));
      if ($recipe->type !== 'App') {
        continue;
      }
      $id = basename(dirname($file));
      $demo = $dir . '/' . $id . '_demo';
      $apps[$id] = [
        'name' => $recipe->name,
        'description' => $recipe->description,
        'path' => $recipe->path,
        'demo' => is_file($demo . '/recipe.yml') ? $demo : NULL,
        'example' => str_contains($id, '_examples_') || str_ends_with($id, '_design_system'),
      ];
    }
    uasort($apps, static fn ($a, $b) => [$a['example'], $a['name']] <=> [$b['example'], $b['name']]);
    return $apps;
  }

}
