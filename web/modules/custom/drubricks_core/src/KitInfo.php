<?php

declare(strict_types=1);

namespace Drupal\drubricks_core;

use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * What the active theme says about the UI kit it ships.
 *
 * A theme declares the kit in its .info.yml:
 * @code
 * drubricks:
 *   kit: kit                     # directory of the kit (ui.css, ui.js, strings.json)
 * @endcode
 * Icons: the theme's "kit" icon pack (core Icon API, drubricks.icons.yml);
 * the kit's JavaScript gets the same sprite URL (UI.init iconSprite).
 */
final class KitInfo {

  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
    private readonly ThemeExtensionList $themeList,
    private readonly LanguageManagerInterface $languageManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TranslationInterface $translation,
    private readonly IconPackManagerInterface $iconPackManager,
  ) {}

  /**
   * The drubricks key of the active theme (or its nearest base theme).
   */
  public function themeInfo(): array {
    $theme = $this->themeManager->getActiveTheme();
    foreach (array_merge([$theme->getName()], array_keys($theme->getBaseThemeExtensions())) as $name) {
      $info = $this->themeList->getExtensionInfo($name);
      if (!empty($info['drubricks'])) {
        return $info['drubricks'] + ['path' => $this->themeList->getPath($name)];
      }
    }
    return [];
  }

  /**
   * Options for UI.init(): locale, currency and the kit strings translated.
   */
  public function initOptions(): array {
    $language = $this->languageManager->getCurrentLanguage()->getId();
    $settings = $this->configFactory->get('drubricks_core.settings');
    $options = [
      'key' => 'drubricks',
      'locale' => $settings->get('locale') ?: $language,
      'currency' => $settings->get('currency') ?: 'EUR',
    ];
    // The sprite of the theme's "kit" icon pack (versioned URL), for the
    // icons the kit's JavaScript draws.
    $icons = $this->iconPackManager->getIcons(['kit']);
    $first = $icons ? $this->iconPackManager->getIcon((string) array_key_first($icons)) : NULL;
    if ($first && $first->getSource()) {
      $options['iconSprite'] = $first->getSource();
    }
    // Kit texts go through Drupal translation (context "UI kit").
    $strings_file = $this->kitFile('strings.json');
    if ($strings_file && $language !== 'en') {
      $strings = json_decode((string) file_get_contents($strings_file), TRUE) ?: [];
      foreach ($strings as $key => $english) {
        if (!is_string($english)) {
          continue;
        }
        // Kit placeholders are {name}; Drupal translations keep them as is.
        $context = ['context' => 'UI kit', 'langcode' => $language];
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        $translated = (string) $this->translation->translate($english, [], $context);
        if ($translated !== $english) {
          $options['strings'][$key] = $translated;
        }
      }
    }
    return $options;
  }

  /**
   * Absolute path of a file in the theme's kit directory, or NULL.
   */
  private function kitFile(string $name): ?string {
    $info = $this->themeInfo();
    if (empty($info['kit'])) {
      return NULL;
    }
    $file = DRUPAL_ROOT . '/' . $info['path'] . '/' . $info['kit'] . '/' . $name;
    return is_file($file) ? $file : NULL;
  }

}
