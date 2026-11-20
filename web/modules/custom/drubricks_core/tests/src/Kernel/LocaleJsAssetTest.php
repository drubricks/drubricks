<?php

declare(strict_types=1);

namespace Drupal\Tests\drubricks_core\Kernel;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The JS translation file is never requested as an aggregate of its own.
 *
 * Core's asset controller answers 400 to ?libraries=locale/translations.
 */
#[Group('drubricks')]
#[RunTestsInSeparateProcesses]
final class LocaleJsAssetTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'language',
    'locale',
    'block',
    'views',
    'inline_form_errors',
    'options',
    'drubricks_core',
  ];

  /**
   * The page links the translation file itself, without an aggregate URL.
   */
  public function testTranslationFileIsNotAggregated(): void {
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location', 'locale_file']);
    $this->installConfig(['language', 'locale']);
    $language = ConfigurableLanguage::createFromLangcode('pl');
    $language->save();
    $this->container->get('state')->set('locale.translation.javascript', ['pl' => 'test']);

    $assets = AttachedAssets::createFromRenderArray(['#attached' => ['library' => ['core/drupal']]]);
    [$header, $footer] = $this->container->get('asset.resolver')->getJsAssets($assets, TRUE, $language);
    // The drupalSettings entry holds an array, not a URL.
    $urls = array_filter(array_column($header + $footer, 'data'), 'is_string');

    $file = array_filter($urls, static fn ($url) => str_ends_with($url, '/pl_test.js'));
    $this->assertCount(1, $file, 'The translation file is linked directly.');
    $single = 'libraries=' . urlencode(UrlHelper::compressQueryParameter('locale/translations'));
    foreach ($urls as $url) {
      $this->assertStringNotContainsString($single, $url);
    }
  }

}
